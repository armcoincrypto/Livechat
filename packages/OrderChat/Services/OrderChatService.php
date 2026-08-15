<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Services;

use App\Events\OrderChatMessagesEvent;
use App\Events\OrderChatSentEvent;
use App\Jobs\OrderChatAiReplyJob;
use App\Jobs\Telegram\TelegramNewChatJob;
use App\Models\NotificationEvent;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use Carbon\Carbon;
use iEXPackages\OrderChat\Contracts\OrderChatServiceInterface;
use iEXPackages\OrderChat\Repositories\TaskMessageRepository;
use iEXPackages\OrderChat\Support\OrderChatFileStorage;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Сервис управления онлайн-чатом заявки.
 *
 * Архитектурные принципы:
 * - Вся бизнес-логика чата находится здесь.
 * - Контроллеры остаются тонкими.
 * - AI подключается централизованно.
 * - Инфраструктура (Events, Jobs) не размазывается по проекту.
 */
final class OrderChatService implements OrderChatServiceInterface
{
    public function __construct(
        private readonly TaskMessageRepository $messages,
        private readonly OrderChatFileStorage $files
    ) {}

    public function isChatEnabled(): bool
    {
        return (int) iEXSetting('is_enable_order_online_chat') === 1;
    }

    /**
     * Получить сообщения клиента.
     */
    public function getClientMessages(int $publicId, string $requestIp)
    {
        $this->assertChatEnabled();

        $task = $this->findTaskByPublicIdOrFail($publicId);
        $this->assertIpAccess($task, $requestIp);

        return $task->task_messages;
    }

    /**
     * Клиент отправляет сообщение.
     *
     * Логика:
     * - Валидация
     * - Сохранение сообщения
     * - Broadcast
     * - Notification
     * - Telegram (если включён)
     * - AI автоответ (если включён бот)
     */
    public function clientSendMessage(
        int $publicId,
        string $requestIp,
        ?string $message,
        ?UploadedFile $file
    ): TaskMessage {
        $this->assertChatEnabled();

        $task = $this->findTaskByPublicIdOrFail($publicId);
        $this->assertIpAccess($task, $requestIp);

        if ((int)($task->task_info?->is_blocked_chat ?? 0) === 1) {
            abort(422, 'Chat is blocked for this order');
        }

        $user = User::find($task->id_user);
        if (!$user) {
            Log::error('OrderChat: user not found', [
                'task_id' => $task->id,
                'public_id' => $publicId
            ]);
            abort(404, 'User not found');
        }

        $text = trim((string) $message);
        $fileName = $file ? $this->files->storeOrderChatFile($file) : null;

        if ($text === '' && !$fileName) {
            abort(422, 'Message or file required');
        }

        $created = $this->messages->createClientMessage(
            taskId: (int)$task->id,
            userId: (int)$user->id,
            message: $text !== '' ? strip_tags(security_xss($text)) : null,
            fileName: $fileName
        );

        $this->broadcastClientMessage($task, $created, $fileName);
        $this->createAdminNotification($task, $created);
        $this->broadcastSentMarkerToAdmin($task);
        $this->notifyTelegramIfEnabled($task, (string)($created->message ?? ''));

        $this->dispatchAiReplyIfEnabled($task, $created);

        return $created;
    }

    public function getAdminMessages(int $orderId)
    {
        return $this->messages->getMessagesByTaskId($orderId);
    }

    public function adminSendMessage(int $orderId, int $managerId, string $message): TaskMessage
    {
        $text = trim($message);
        if ($text === '') {
            abort(422, 'Message is empty');
        }

        /** @var Task $task */
        $task = Task::query()
            ->with('meta') // meta гарантированно есть, но подгружаем чтобы не было лишних запросов
            ->select(['id', 'id_user', 'public_id'])
            ->findOrFail($orderId);

        // 1) Создаём сообщение менеджера
        $created = $this->messages->createManagerMessage(
            taskId: (int)$task->id,
            userId: (int)$task->id_user,
            managerId: (int)$managerId,
            message: security_xss($text),
        );

        // 2) Переключаем чат на оператора: AI должен замолчать
        // meta всегда есть → без try/catch
        $task->meta->update([
            'chat_handoff_to_human' => true,
        ]);

        // 3) Мгновенно отправляем сообщение клиенту по сокетам
        try {
            broadcast(new OrderChatMessagesEvent(
                (int)$task->public_id,
                (int)$task->id_user,
                [
                    'message'   => $created->message,
                    'date'      => $created->created_at?->diffForHumans() ?? 'только что',
                    'type_user' => (int)$created->type_user,
                    'file_path' => $created->file_path ?? null,
                ]
            ))->toOthers();
        } catch (BroadcastException $e) {
            Log::warning('OrderChat: broadcast manager message failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 4) Маркер “менеджер отправил” (как у тебя уже используется)
        try {
            broadcast(new OrderChatSentEvent((int)$task->public_id, 'user'))->toOthers();
        } catch (BroadcastException $e) {
            Log::warning('OrderChat: broadcast sent marker failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $created;
    }

    public function getChatsList(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->messages->getChatsList($filters, $perPage);
    }

    public function markChatRead(int $orderId): void
    {
        $this->messages->markChatRead($orderId);
    }

    public function markAllChatsRead(): void
    {
        $this->messages->markAllRead();
    }

    private function dispatchAiReplyIfEnabled(Task $task, TaskMessage $trigger): void
    {
        // 0) Глобальный переключатель AI для онлайн-чата (scope ai:1)
        $aiScope = Scope::fromString('ai', 2);
        $aiEnabled = (int) iEXSetting('ai_online_chat_enabled', 1, scope: $aiScope, schemaProfile: 'ai') === 1;

        if (!$aiEnabled) {
            return;
        }

        // 1) Только на сообщения клиента
        if ((int)$trigger->type_user !== 0) {
            return;
        }

        // 2) Пока AI работает только по тексту (если только файл — не отвечаем)
        $text = trim((string)($trigger->message ?? ''));
        if ($text === '') {
            return;
        }

        // 3) Если чат уже передан оператору — AI молчит
        if ((bool)$task->meta?->chat_handoff_to_human === true) {
            return;
        }

        // 4) Если клиент просит оператора — включаем handoff и AI не запускаем
        if ($this->wantsHumanOperator($text)) {
            $task->meta->update([
                'chat_handoff_to_human' => true,
            ]);

            return;
        }

        // 5) Запускаем AI job
        dispatch(new OrderChatAiReplyJob(
            taskId: (int)$task->id,
            triggerMessageId: (int)$trigger->id
        ))->onQueue('ai');
    }

    /**
     * Определяем, что пользователь хочет человека/оператора.
     */
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


    private function assertChatEnabled(): void
    {
        if (!$this->isChatEnabled()) {
            abort(404, 'Chat is disabled');
        }
    }

    private function findTaskByPublicIdOrFail(int $publicId): Task
    {
        $task = Task::query()->where('public_id', $publicId)->first();
        if (!$task) {
            abort(404, 'Order not found');
        }
        return $task;
    }

    private function assertIpAccess(Task $task, string $requestIp): void
    {
        if ($requestIp !== (string)$task->ip) {
            abort(403, 'No access');
        }
    }

    private function broadcastClientMessage(Task $task, TaskMessage $message, ?string $fileName): void
    {
        try {
            broadcast(new OrderChatMessagesEvent(
                (int)$task->public_id,
                (int)$task->id_user,
                [
                    'message'   => $message->message,
                    'date'      => Carbon::parse($message->created_at)->diffForHumans(),
                    'type_user' => (int)$message->type_user,
                    'file_path' => $fileName,
                ]
            ))->toOthers();
        } catch (BroadcastException $e) {
            Log::warning('OrderChat broadcast failed', ['error' => $e->getMessage()]);
        }
    }

    private function broadcastSentMarkerToAdmin(Task $task): void
    {
        try {
            broadcast(new OrderChatSentEvent((int)$task->public_id, 'admin'))->toOthers();
        } catch (BroadcastException $e) {
            Log::warning('OrderChat sent marker failed', ['error' => $e->getMessage()]);
        }
    }

    private function createAdminNotification(Task $task, TaskMessage $message): void
    {
        try {
            NotificationEvent::create([
                'is_read' => 0,
                'type_event' => 4,
                'title' => 'Поступило новое сообщение',
                'id_value' => $task->id,
                'message' => [
                    'Номер заявки: №' . current_order_id($task),
                    'Сообщение: ' . (string)($message->message ?? ''),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('OrderChat notification error', ['error' => $e->getMessage()]);
        }
    }

    private function notifyTelegramIfEnabled(Task $task, string $text): void
    {
        if ((int) iEXSetting('is_enabled_online_chat_tg_notify') !== 1) {
            return;
        }

        try {
            dispatch(new TelegramNewChatJob($task, $text))
                ->delay(now()->addSeconds(5))
                ->onQueue('high');
        } catch (Throwable $e) {
            Log::warning('Telegram notify failed', ['error' => $e->getMessage()]);
        }
    }
}
