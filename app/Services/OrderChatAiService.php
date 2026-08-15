<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Support\Collection;

final class OrderChatAiService
{
    /**
     * Сформировать ответ AI на сообщение клиента.
     *
     * ВАЖНО:
     * - Здесь не должно быть broadcast/DB create сообщения — это делает Job.
     * - Сервис только "думает" и возвращает текст.
     *
     * @return array{text:string, meta?:array}|null
     */
    public function reply(Task $task, TaskMessage $trigger): ?array
    {
        // 1) Подготовка контекста (последние 20 сообщений)
        $messages = TaskMessage::query()
            ->where('id_task', $task->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        // 2) Системный промпт (правила саппорта)
        $system = $this->systemPrompt($task);

        // 3) Преобразуем историю в формат провайдера
        $payload = $this->buildPayload($system, $messages);

        // 4) Вызов провайдера (сюда ты подключишь Laravel/AI или любой SDK)
        // Ниже псевдо-вызов: заменишь на реальный клиент.
        $text = $this->callProvider($payload);

        $text = trim((string)$text);
        if ($text === '') {
            return null;
        }

        return [
            'text' => $text,
            'meta' => [
                'provider' => 'laravel-ai',
                'model' => 'support-agent',
            ],
        ];
    }

    private function systemPrompt(Task $task): string
    {
        return implode("\n", [
            'Ты — AI-оператор службы поддержки обменного сервиса.',
            'Отвечай коротко, по делу, дружелюбно.',
            'Если не хватает данных — задавай уточняющие вопросы.',
            'Если вопрос про платежи/риски/возвраты/юридическое — попроси подключить живого оператора.',
            'Не выдумывай факты. Если не уверен — говори, что нужно уточнить.',
        ]);
    }

    /**
     * @return array<int, array{role:string, content:string}>
     */
    private function buildPayload(string $system, Collection $messages): array
    {
        $result = [
            ['role' => 'system', 'content' => $system],
        ];

        foreach ($messages as $m) {
            $role = match ((int)$m->type_user) {
                0 => 'user',
                1 => 'assistant', // менеджер
                2 => 'assistant', // AI (как assistant)
                default => 'user',
            };

            $content = (string)($m->message ?? '');
            if ($content === '') {
                continue;
            }

            $result[] = ['role' => $role, 'content' => $content];
        }

        return $result;
    }

    /**
     * Здесь подключишь Laravel/AI.
     * Сейчас — заглушка.
     */
    private function callProvider(array $payload): string
    {
        // TODO: заменить на реальный вызов:
        // return $this->ai->chat()->create([...]);
        return 'Я понял. Уточните, пожалуйста, вы уже отправили оплату по заявке или ещё нет?';
    }
}
