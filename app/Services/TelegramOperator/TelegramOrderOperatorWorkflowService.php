<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use App\Models\Task;
use App\Models\User;
use App\Services\Orders\ManualCompletion\ManualCompletionException;
use App\Services\Orders\ManualCompletion\ManualCompletionGuard;
use App\Services\Orders\ManualCompletion\ManualOrderCompletionService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramOrderOperatorWorkflowService
{
    public const CB_TAKE = 'ot:t:';

    public const CB_COMPLETE = 'ot:c:';

    public const CB_CONFIRM = 'ot:y:';

    public const CB_CANCEL = 'ot:n:';

    public function __construct(
        private readonly TelegramOperatorAuthService $auth,
        private readonly TelegramOrderClaimService $claims,
        private readonly ManualOrderCompletionService $completion,
        private readonly TelegramOperatorPendingFlow $pending,
        private readonly TelegramOperatorBotClient $bot,
        private readonly TelegramOperatorAuditLogger $audit,
        private readonly TelegramCompletionNoticePublisher $completionNotice,
    ) {
    }

    public function actionsEnabled(): bool
    {
        return (bool) config('telegram_operator.actions_enabled', false);
    }

    public function dryRun(): bool
    {
        return (bool) config('telegram_operator.dry_run', true);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        if (! $this->actionsEnabled()) {
            return;
        }

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message']) && is_array($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];
        $telegramUserId = (int) ($from['id'] ?? 0);
        $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
        $chatId = $message['chat']['id'] ?? null;
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : null;
        $originalText = (string) ($message['text'] ?? $message['caption'] ?? '');

        if ($callbackId === '' || $telegramUserId <= 0 || $data === '') {
            return;
        }

        $parsed = $this->parseCallback($data);
        if ($parsed === null) {
            $this->bot->answerCallback($callbackId, 'Неизвестное действие', true);
            $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', null, null, $telegramUserId, [
                'result' => 'rejected',
                'reason' => 'malformed_callback',
            ]);

            return;
        }

        [$action, $taskId] = $parsed;

        if ($action === 'take') {
            $operator = $this->auth->authorize(
                $telegramUserId,
                (string) config('telegram_operator.claim_permission', 'admin_orders_execute')
            );
            if ($operator === null) {
                $this->bot->answerCallback($callbackId, 'Нет доступа', true);
                $this->audit->log('ORDER_TELEGRAM_TAKE', $taskId, null, $telegramUserId, [
                    'result' => 'rejected',
                    'reason' => 'unauthorized',
                ]);

                return;
            }

            $result = $this->claims->claim($taskId, $operator, $telegramUserId);
            $this->bot->answerCallback($callbackId, $result['message'], ! $result['ok']);

            if ($result['ok'] && $chatId !== null && $messageId !== null) {
                $this->refreshClaimedMessage($taskId, $operator, $chatId, $messageId, $originalText);
            }

            return;
        }

        if ($action === 'complete') {
            $operator = $this->auth->authorize(
                $telegramUserId,
                (string) config('telegram_operator.complete_permission', 'admin_orders_execute')
            );
            if ($operator === null) {
                $this->bot->answerCallback($callbackId, 'Нет доступа', true);
                $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', $taskId, null, $telegramUserId, [
                    'result' => 'rejected',
                    'reason' => 'unauthorized',
                ]);

                return;
            }

            $task = Task::query()->find($taskId);
            if ($task === null) {
                $this->bot->answerCallback($callbackId, 'Заявка не найдена', true);

                return;
            }

            if ((int) $task->status === ManualCompletionGuard::COMPLETED) {
                $this->bot->answerCallback($callbackId, '✅ Заявка уже выполнена', false);
                $this->audit->log('ORDER_TELEGRAM_COMPLETE_SUCCESS', $taskId, $operator, $telegramUserId, [
                    'result' => 'idempotent',
                    'reason' => 'already_completed',
                ]);

                return;
            }

            if (! in_array((int) $task->status, ManualCompletionGuard::MANUAL_FROM_STATUSES, true)) {
                $status = (int) $task->status;
                $this->bot->answerCallback(
                    $callbackId,
                    $this->completeBlockedByStatusMessage($status),
                    true
                );
                $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', $taskId, $operator, $telegramUserId, [
                    'result' => 'rejected',
                    'reason' => 'invalid_status',
                    'old_status' => $status,
                ]);

                return;
            }

            $this->pending->put($telegramUserId, [
                'stage' => 'awaiting_confirm',
                'task_id' => $taskId,
                'chat_id' => $chatId ?? $telegramUserId,
                'message_id' => $messageId,
                'operator_user_id' => (int) $operator->id,
            ]);

            $this->audit->log('ORDER_TELEGRAM_COMPLETE_STARTED', $taskId, $operator, $telegramUserId, [
                'result' => 'awaiting_confirm',
                'old_status' => (int) $task->status,
            ]);

            $this->bot->answerCallback($callbackId, 'Подтвердите завершение', false);
            $promptChat = $telegramUserId;
            $prompt = $this->completionPrompt($task);
            $this->bot->sendMessage($promptChat, $prompt, [
                [
                    ['text' => '✅ Да, завершить', 'callback_data' => self::CB_CONFIRM.$taskId],
                ],
                [
                    ['text' => '❌ Отмена', 'callback_data' => self::CB_CANCEL.$taskId],
                ],
            ]);

            return;
        }

        if ($action === 'confirm') {
            $this->handleConfirm($callbackId, $telegramUserId, $taskId, $chatId, $messageId);

            return;
        }

        if ($action === 'cancel') {
            $operator = $this->auth->resolveOperator($telegramUserId);
            $this->pending->clear($telegramUserId);
            $this->audit->log('ORDER_TELEGRAM_COMPLETE_CANCELLED', $taskId, $operator, $telegramUserId, [
                'result' => 'cancelled',
            ]);
            $this->bot->answerCallback($callbackId, 'Отменено', false);
            if ($chatId !== null && $messageId !== null) {
                $this->bot->editMessage($chatId, $messageId, '❌ Завершение отменено', null);
            }

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message): void
    {
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $telegramUserId = (int) ($from['id'] ?? 0);
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = $message['chat']['id'] ?? $telegramUserId;

        if ($telegramUserId <= 0 || $text === '' || str_starts_with($text, '/')) {
            return;
        }

        $flow = $this->pending->get($telegramUserId);
        if ($flow === null || ! in_array(($flow['stage'] ?? ''), ['awaiting_evidence', 'awaiting_confirm'], true)) {
            return;
        }
        $this->bot->sendMessage($chatId, 'Для завершения используйте кнопки подтверждения в сообщении бота.');
    }

    private function handleConfirm(
        string $callbackId,
        int $telegramUserId,
        int $taskId,
        int|string|null $chatId,
        ?int $messageId
    ): void {
        $operator = $this->auth->authorize(
            $telegramUserId,
            (string) config('telegram_operator.complete_permission', 'admin_orders_execute')
        );
        if ($operator === null) {
            $this->bot->answerCallback($callbackId, 'Нет доступа', true);
            $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', $taskId, null, $telegramUserId, [
                'result' => 'rejected',
                'reason' => 'unauthorized',
            ]);

            return;
        }

        $flow = $this->pending->get($telegramUserId);
        if ($flow === null
            || ($flow['stage'] ?? '') !== 'awaiting_confirm'
            || (int) ($flow['task_id'] ?? 0) !== $taskId
        ) {
            $this->bot->answerCallback($callbackId, 'Сессия подтверждения устарела', true);
            $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', $taskId, $operator, $telegramUserId, [
                'result' => 'rejected',
                'reason' => 'stale_confirm',
            ]);

            return;
        }

        $dryRun = $this->dryRun();
        $taskBefore = Task::query()->find($taskId);
        $note = 'Telegram operator confirmed payout for order '.$this->orderLabel($taskBefore ?? new Task());

        try {
            $oldStatus = $taskBefore ? (int) $taskBefore->status : null;

            $result = $this->completion->complete($taskId, $operator, [
                'message' => $note,
            ], $dryRun);

            $this->pending->clear($telegramUserId);

            if (! ($result['ok'] ?? false)) {
                $this->bot->answerCallback($callbackId, $result['message'] ?? 'Ошибка', true);
                $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', $taskId, $operator, $telegramUserId, [
                    'result' => 'rejected',
                    'reason' => $result['code'] ?? 'failed',
                    'old_status' => $oldStatus,
                    'dry_run' => $dryRun,
                ]);

                return;
            }

            $this->bot->answerCallback($callbackId, $result['message'] ?? 'OK', false);
            $taskAfter = Task::query()->find($taskId);
            $newStatus = $taskAfter ? (int) $taskAfter->status : $oldStatus;

            $this->audit->log('ORDER_TELEGRAM_COMPLETE_SUCCESS', $taskId, $operator, $telegramUserId, [
                'result' => $result['code'] ?? 'ok',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'dry_run' => $dryRun,
                'settlement_reference_len' => mb_strlen($note),
            ]);

            $presenter = app(TelegramOrderMessagePresenter::class);
            $cardTask = $taskAfter ?? $taskBefore ?? new Task();
            $cardText = $presenter->renderText(
                $presenter->present($cardTask, TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED)
            );
            if ($dryRun) {
                $cardText = "🧪 DRY-RUN (без изменения статуса)\n\n".$cardText;
            }

            $this->completionNotice->publish(
                $taskId,
                $telegramUserId,
                $chatId,
                $messageId,
                $flow,
                $cardText,
                self::completedKeyboard($taskId)
            );
        } catch (ManualCompletionException $e) {
            $this->bot->answerCallback($callbackId, $e->getMessage(), true);
            $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', $taskId, $operator, $telegramUserId, [
                'result' => 'rejected',
                'reason' => $e->errorCode,
                'dry_run' => $dryRun,
            ]);
        } catch (Throwable $e) {
            Log::error('telegram_operator_confirm_failed', [
                'task_id' => $taskId,
                'exception' => $e::class,
            ]);
            $this->bot->answerCallback($callbackId, 'Ошибка завершения', true);
            $this->audit->log('ORDER_TELEGRAM_COMPLETE_REJECTED', $taskId, $operator, $telegramUserId, [
                'result' => 'rejected',
                'reason' => 'exception',
                'dry_run' => $dryRun,
            ]);
        }
    }

    private function refreshClaimedMessage(
        int $taskId,
        User $operator,
        int|string $chatId,
        int $messageId,
        string $originalText
    ): void {
        $text = '📋 Заявка #'.$taskId;
        try {
            $task = Task::query()->find($taskId);
            if ($task !== null) {
                $presenter = app(TelegramOrderMessagePresenter::class);
                $text = $presenter->renderText($presenter->present($task, TelegramOrderMessagePresenter::LIFECYCLE_CLAIMED));
            } elseif (trim($originalText) !== '') {
                $text = trim($originalText);
            }
        } catch (Throwable) {
            $text = trim($originalText) !== '' ? trim($originalText) : $text;
        }
        $this->bot->editMessage($chatId, $messageId, $text, self::claimedKeyboard($taskId));
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function parseCallback(string $data): ?array
    {
        foreach ([
            'take' => self::CB_TAKE,
            'complete' => self::CB_COMPLETE,
            'confirm' => self::CB_CONFIRM,
            'cancel' => self::CB_CANCEL,
        ] as $action => $prefix) {
            if (str_starts_with($data, $prefix)) {
                $idPart = substr($data, strlen($prefix));
                if ($idPart === '' || ! ctype_digit($idPart)) {
                    return null;
                }

                return [$action, (int) $idPart];
            }
        }

        return null;
    }

    /**
     * Operator-facing Complete rejection when status is outside MANUAL_FROM_STATUSES.
     * Does not change eligibility — only clarifies the toast.
     */
    private function completeBlockedByStatusMessage(int $status): string
    {
        return match ($status) {
            2 => "Заявка ещё ожидает оплату.\nЗавершить можно после подтверждения оплаты.",
            5 => 'Заявка отменена — завершение недоступно.',
            6 => 'Заявка отклонена — завершение недоступно.',
            15, 16 => "Заявка в процессе выплаты.\nДождитесь завершения автовыплаты или ошибки.",
            default => 'Статус не позволяет завершить.',
        };
    }

    private function completionPrompt(Task $task): string
    {
        try {
            $presenter = app(TelegramOrderMessagePresenter::class);

            return $presenter->renderCompletionPrompt($presenter->present($task));
        } catch (Throwable) {
            return 'Подтвердить завершение заявки №'.$this->orderLabel($task).'?';
        }
    }

    private function orderLabel(Task $task): string
    {
        $public = trim((string) ($task->public_id ?? ''));

        return $public !== '' ? $public : (string) $task->id;
    }

    private function abbreviate(string $value, int $keep = 8): string
    {
        $value = trim(strip_tags($value));
        if (mb_strlen($value) <= ($keep * 2 + 3)) {
            return $value;
        }

        return mb_substr($value, 0, $keep).'...'.mb_substr($value, -$keep);
    }

    private function adminUrl(int $taskId): ?string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = (string) config('telegram_operator.admin_order_path', '/iexadmin/#/orders/');
        if ($base === '') {
            return null;
        }

        return $base.$path.$taskId;
    }

    /**
     * Keyboard rows for new-order notifications when actions are enabled.
     *
     * @return list<list<array{text: string, callback_data?: string, url?: string}>>
     */
    public static function notificationKeyboard(int $taskId): array
    {
        $rows = [
            [['text' => '👤 Принять заявку', 'callback_data' => self::CB_TAKE.$taskId]],
            [['text' => '✅ Выполнить', 'callback_data' => self::CB_COMPLETE.$taskId]],
        ];

        $base = rtrim((string) config('app.url', ''), '/');
        $path = (string) config('telegram_operator.admin_order_path', '/iexadmin/#/orders/');
        if ($base !== '') {
            $rows[] = [['text' => '🔗 Открыть в админке', 'url' => $base.$path.$taskId]];
        }

        return $rows;
    }

    /**
     * @return list<list<array{text: string, callback_data?: string, url?: string}>>
     */
    public static function claimedKeyboard(int $taskId): array
    {
        $rows = [
            [['text' => '✅ Выполнить', 'callback_data' => self::CB_COMPLETE.$taskId]],
        ];
        $admin = self::adminLink($taskId);
        if ($admin !== null) {
            $rows[] = [$admin];
        }

        return $rows;
    }

    /**
     * @return list<list<array{text: string, callback_data?: string, url?: string}>>
     */
    public static function completedKeyboard(int $taskId): array
    {
        $admin = self::adminLink($taskId);
        if ($admin === null) {
            return [];
        }

        return [[$admin]];
    }

    /**
     * @return array{text: string, url: string}|null
     */
    private static function adminLink(int $taskId): ?array
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = (string) config('telegram_operator.admin_order_path', '/iexadmin/#/orders/');
        if ($base === '') {
            return null;
        }

        return ['text' => '🔗 Открыть в админке', 'url' => $base.$path.$taskId];
    }
}
