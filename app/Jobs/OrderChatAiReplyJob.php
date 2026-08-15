<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\OrderSupportAgent;
use App\Events\OrderChatMessagesEvent;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OrderChatAiReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TYPE_CLIENT = 0;
    private const TYPE_MANAGER = 1;
    private const TYPE_AI = 2;

    private const CONTEXT_LIMIT = 20;
    private const MAX_PROMPT_CHARS = 12_000;
    private const LOCK_TTL_SECONDS = 45;

    public function __construct(
        public readonly int $taskId,
        public readonly int $triggerMessageId,
    ) {
        $this->onQueue('ai');
    }

    /**
     * Генерация AI-ответа для чата заявки.
     *
     * Правила:
     * - Отвечаем только на сообщения клиента.
     * - Если чат уже передан оператору (tasks_meta.chat_handoff_to_human=true) — не отвечаем.
     * - Если клиент просит оператора — выставляем handoff и отвечаем одной фразой.
     * - Если после trigger уже ответил менеджер — выставляем handoff и не отвечаем.
     * - Защита от дублей: lock + проверка наличия AI-сообщения после trigger.
     */
    public function handle(): void
    {
        $lock = Cache::lock($this->lockKey(), self::LOCK_TTL_SECONDS);
        if (!$lock->get()) {
            return;
        }

        try {
            /** @var Task|null $task */
            $task = Task::query()
                ->select(['id', 'public_id', 'id_user', 'status'])
                ->with([
                    'meta:id,task_id,chat_handoff_to_human',
                    'task_status:id,name',
                    'task_info:id_task,is_blocked_chat',
                ])
                ->find($this->taskId);

            if (!$task) {
                return;
            }

            // meta "по договорённости" всегда есть, но на всякий случай — безопасный fallback
            $meta = $task->meta;
            if (!$meta) {
                Log::warning('OrderChatAiReplyJob: task meta not loaded', [
                    'task_id' => $task->id,
                ]);
                return;
            }

            // Уже передано оператору — AI молчит
            if ((bool)$meta->chat_handoff_to_human === true) {
                return;
            }

            /** @var TaskMessage|null $trigger */
            $trigger = TaskMessage::query()
                ->select(['id', 'id_task', 'type_user', 'message'])
                ->where('id', $this->triggerMessageId)
                ->where('id_task', $task->id)
                ->first();

            if (!$trigger) {
                return;
            }

            // Отвечаем только на сообщения клиента
            if ((int)$trigger->type_user !== self::TYPE_CLIENT) {
                return;
            }

            $triggerText = trim((string)($trigger->message ?? ''));
            if ($triggerText === '') {
                // Пока не обрабатываем “только файл”
                return;
            }

            // Клиент просит оператора → handoff + короткий ответ + стоп
            if ($this->wantsHumanOperator($triggerText)) {
                $meta->update(['chat_handoff_to_human' => true]);

                /** @var TaskMessage $handoffMsg */
                $handoffMsg = TaskMessage::query()->create([
                    'id_task' => (int)$task->id,
                    'user_id' => (int)$task->id_user,
                    'id_manager' => 0,
                    'type_user' => self::TYPE_AI,
                    'message' => 'Подключаю оператора.',
                    'is_view' => 1,
                    'is_read' => 0,
                    'ai_meta' => ['handoff' => true],
                ]);

                broadcast(new OrderChatMessagesEvent(
                    (int)$task->public_id,
                    (int)$task->id_user,
                    [
                        'message' => $handoffMsg->message,
                        'date' => $handoffMsg->created_at?->diffForHumans(),
                        'type_user' => (int)$handoffMsg->type_user,
                        'file_path' => null,
                    ]
                ));

                return;
            }

            // Если менеджер уже ответил после trigger — handoff и AI не вмешивается
            $managerAnswered = TaskMessage::query()
                ->where('id_task', $task->id)
                ->where('type_user', self::TYPE_MANAGER)
                ->where('id', '>', $trigger->id)
                ->exists();

            if ($managerAnswered) {
                $meta->update(['chat_handoff_to_human' => true]);
                return;
            }

            // Идемпотентность: если AI уже ответил после trigger — выходим
            $alreadyAnswered = TaskMessage::query()
                ->where('id_task', $task->id)
                ->where('type_user', self::TYPE_AI)
                ->where('id', '>', $trigger->id)
                ->exists();

            if ($alreadyAnswered) {
                return;
            }

            // На случай гонки: если handoff включился прямо сейчас — выходим
            if ((bool)$task->meta?->fresh()?->chat_handoff_to_human === true) {
                return;
            }

            $history = $this->loadHistory($task->id);

            // Snapshot: минимум, но полезно. Можно расширять позже.
            $snapshot = $this->buildSnapshotText($task, (bool)$meta->chat_handoff_to_human);
            $dialog = $this->historyToDialog($history);

            $prompt = $this->trimPrompt(implode("\n\n", [
                $snapshot,
                "CHAT_HISTORY:\n{$dialog}",
                "Ответь пользователю по данным заявки. Не проси номер заявки/email/телефон.",
            ]));

            $agent = new OrderSupportAgent();

            $startedAt = microtime(true);
            $response = $agent->prompt($prompt);
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            $text = trim((string)($response->text ?? ''));
            if ($text === '') {
                return;
            }

            // На всякий случай: если handoff уже включили пока AI думал — не пишем ответ
            if ((bool)$task->meta?->fresh()?->chat_handoff_to_human === true) {
                return;
            }

            /** @var TaskMessage $aiMessage */
            $aiMessage = TaskMessage::query()->create([
                'id_task' => (int)$task->id,
                'user_id' => (int)$task->id_user,
                'id_manager' => 0,
                'type_user' => self::TYPE_AI,
                'message' => $text,
                'is_view' => 1,
                'is_read' => 0,
                'ai_meta' => [
                    'agent' => OrderSupportAgent::class,
                    'ms' => $elapsedMs,
                    'trigger_message_id' => (int)$trigger->id,
                ],
            ]);

            broadcast(new OrderChatMessagesEvent(
                (int)$task->public_id,
                (int)$task->id_user,
                [
                    'message' => $aiMessage->message,
                    'date' => $aiMessage->created_at?->diffForHumans(),
                    'type_user' => (int)$aiMessage->type_user,
                    'file_path' => null,
                ]
            ));
        } catch (Throwable $e) {
            Log::error('OrderChatAiReplyJob failed', [
                'task_id' => $this->taskId,
                'trigger_message_id' => $this->triggerMessageId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }
    }

    private function lockKey(): string
    {
        return "lock:order-chat-ai:task:{$this->taskId}";
    }

    /**
     * @return Collection<int, TaskMessage>
     */
    private function loadHistory(int $taskId): Collection
    {
        /** @var Collection<int, TaskMessage> $history */
        $history = TaskMessage::query()
            ->select(['id', 'type_user', 'message', 'created_at'])
            ->where('id_task', $taskId)
            ->orderByDesc('id')
            ->limit(self::CONTEXT_LIMIT)
            ->get()
            ->reverse()
            ->values();

        return $history;
    }

    private function historyToDialog(Collection $history): string
    {
        $lines = [];

        foreach ($history as $m) {
            $content = trim((string)($m->message ?? ''));
            if ($content === '') {
                continue;
            }

            $role = ((int)$m->type_user === self::TYPE_CLIENT) ? 'user' : 'assistant';
            $content = preg_replace('/\s+/', ' ', $content) ?? $content;
            $lines[] = "{$role}: {$content}";
        }

        return implode("\n", $lines);
    }

    private function trimPrompt(string $prompt): string
    {
        if (mb_strlen($prompt) <= self::MAX_PROMPT_CHARS) {
            return $prompt;
        }

        return mb_substr($prompt, -self::MAX_PROMPT_CHARS);
    }

    private function wantsHumanOperator(string $text): bool
    {
        $t = mb_strtolower($text);

        foreach ([
                     'оператор',
                     'менеджер',
                     'человек',
                     'живой оператор',
                     'live agent',
                     'human',
                 ] as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function buildSnapshotText(Task $task, bool $handoff): string
    {
        // В snapshot кладём то, что реально помогает отвечать без просьбы “ID/почта”
        $statusName = (string)($task->task_status?->name ?? '');
        $statusCode = (int)($task->status ?? 0);

        return implode("\n", [
            'HANDOFF_TO_HUMAN: ' . ($handoff ? 'true' : 'false'),
            '',
            'ORDER_SNAPSHOT_SAFE:',
            json_encode([
                'public_id' => (int)$task->public_id,
                'status' => ['code' => $statusCode, 'name' => $statusName !== '' ? $statusName : null],
                'blocked_chat' => (int)($task->task_info?->is_blocked_chat ?? 0) === 1,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
