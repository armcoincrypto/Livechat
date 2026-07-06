<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only order/task lookup for operator-assist AI drafts.
 * Never mutates orders, payments, queues, or notifications.
 */
final class SupportAiOrderContextService
{
    /**
     * @return array{
     *     order_lookup_attempted: bool,
     *     order_lookup_found: bool,
     *     verified_order_status: bool,
     *     order_public_id: string|null,
     *     order_status_code: int|null,
     *     order_status: string|null,
     *     order_status_human: string|null,
     *     order_direction: string|null,
     *     payment_received: bool|null,
     *     payout_completed: bool|null,
     *     order_created_at: string|null,
     *     order_updated_at: string|null,
     *     order_safe_summary: string|null,
     *     order_context_error: string|null
     * }
     */
    public function lookupForDraft(?string $rawOrderId, string $language = 'ru'): array
    {
        $context = $this->emptyContext();
        $normalized = $this->normalizeOrderIdentifier($rawOrderId);

        if ($normalized === null) {
            return $context;
        }

        $context['order_lookup_attempted'] = true;
        $context['order_public_id'] = $normalized;

        if (! Schema::hasTable('tasks')) {
            $context['order_context_error'] = 'tasks_table_unavailable';

            return $context;
        }

        try {
            $task = $this->findTaskReadOnly($normalized);

            if ($task === null) {
                $context['order_lookup_found'] = false;
                $context['order_safe_summary'] = $this->notFoundSummary($normalized, $language);

                return $context;
            }

            return $this->mapTaskToContext($task, $normalized, $language);
        } catch (Throwable) {
            $context['order_context_error'] = 'lookup_failed';

            return $context;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function buildPromptBlock(array $context): string
    {
        if (empty($context['order_lookup_attempted'])) {
            return '';
        }

        $lines = ['--- verified order lookup (read-only admin DB) ---'];

        if (! empty($context['verified_order_status'])) {
            $lines[] = 'verified_order_status: true';
            $lines[] = 'order_found: true';
        } else {
            $lines[] = 'verified_order_status: false';
            $lines[] = 'order_found: '.(! empty($context['order_lookup_found']) ? 'true' : 'false');
        }

        foreach ([
            'order_public_id',
            'order_status',
            'order_status_human',
            'order_direction',
            'order_safe_summary',
        ] as $key) {
            $value = $context[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $lines[] = $key.': '.$value;
            }
        }

        if (isset($context['order_status_code']) && $context['order_status_code'] !== null) {
            $lines[] = 'order_status_code: '.(int) $context['order_status_code'];
        }

        if (array_key_exists('payment_received', $context) && $context['payment_received'] !== null) {
            $lines[] = 'payment_received: '.($context['payment_received'] ? 'true' : 'false');
        }

        if (array_key_exists('payout_completed', $context) && $context['payout_completed'] !== null) {
            $lines[] = 'payout_completed: '.($context['payout_completed'] ? 'true' : 'false');
        }

        if (! empty($context['order_created_at'])) {
            $lines[] = 'order_created_at: '.$context['order_created_at'];
        }

        if (! empty($context['order_updated_at'])) {
            $lines[] = 'order_updated_at: '.$context['order_updated_at'];
        }

        if (! empty($context['order_context_error'])) {
            $lines[] = 'order_context_error: '.$context['order_context_error'];
        }

        if (! empty($context['verified_order_status'])) {
            $lines[] = 'RULE: verified_order_status=true — LEAD with verified status; do NOT open with "Проверим заявку", "Проверяем заявку", "We will check", or empty deferral.';
            $lines[] = 'RULE: Structure each draft as: (1) verified status in plain language, (2) what it means for the visitor, (3) next useful action.';
            $lines[] = 'RULE: Use order_status_human and order_safe_summary as facts. Do NOT say "по системе и ответим с актуальным статусом" when status is already verified.';
            $lines[] = 'RULE: Do NOT claim completed/paid/sent unless payout_completed/payment_received flags support it.';
        } elseif (empty($context['order_lookup_found']) && ! empty($context['order_public_id'])) {
            $lines[] = 'RULE: order not found — ask visitor to verify the order number; do not invent status.';
        }

        return implode("\n", $lines);
    }

    public function normalizeOrderIdentifier(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('#/order/(\d{4,20})#i', $raw, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^\d{10,20}$/', $raw) === 1) {
            return $raw;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContext(): array
    {
        return [
            'order_lookup_attempted' => false,
            'order_lookup_found' => false,
            'verified_order_status' => false,
            'order_public_id' => null,
            'order_status_code' => null,
            'order_status' => null,
            'order_status_human' => null,
            'order_direction' => null,
            'payment_received' => null,
            'payout_completed' => null,
            'order_created_at' => null,
            'order_updated_at' => null,
            'order_safe_summary' => null,
            'order_context_error' => null,
        ];
    }

    private function findTaskReadOnly(string $normalized): ?Task
    {
        $baseQuery = Task::query()
            ->select([
                'id',
                'public_id',
                'status',
                'created_at',
                'updated_at',
                'completed_at',
                'give_price',
                'receiving_price',
                'id_direction_exchange',
            ])
            ->with([
                'task_status:id,name',
                'direction_exchange:id,id_currency1,id_currency2',
                'direction_exchange.currency1:id,id_payment',
                'direction_exchange.currency1.payment:id,name',
                'direction_exchange.currency2:id,id_payment',
                'direction_exchange.currency2.payment:id,name',
            ]);

        $byPublicId = (clone $baseQuery)->where('public_id', $normalized)->first();
        if ($byPublicId !== null) {
            return $byPublicId;
        }

        if (ctype_digit($normalized) && strlen($normalized) <= 10) {
            return (clone $baseQuery)->find((int) $normalized);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTaskToContext(Task $task, string $visitorOrderId, string $language): array
    {
        $statusCode = (int) $task->status;
        $statusEnum = TaskStatusEnum::tryFromId($statusCode);
        $locale = $this->normalizeLocale($language);
        $publicId = (string) ($task->public_id ?: $visitorOrderId);
        $statusHuman = $this->resolveStatusHuman($task, $locale);
        $direction = $this->resolveDirectionLabel($task);
        $paymentReceived = $statusEnum !== null && $this->isPaymentReceived($statusEnum);
        $payoutCompleted = $statusEnum === TaskStatusEnum::COMPLETED;

        return [
            'order_lookup_attempted' => true,
            'order_lookup_found' => true,
            'verified_order_status' => true,
            'order_public_id' => $publicId,
            'order_status_code' => $statusCode,
            'order_status' => $statusEnum !== null ? $this->statusSlug($statusEnum) : 'unknown',
            'order_status_human' => $statusHuman,
            'order_direction' => $direction,
            'payment_received' => $paymentReceived,
            'payout_completed' => $payoutCompleted,
            'order_created_at' => $task->created_at?->format('Y-m-d H:i:s'),
            'order_updated_at' => $task->updated_at?->format('Y-m-d H:i:s'),
            'order_safe_summary' => $this->buildSafeSummary(
                $publicId,
                $statusEnum,
                $statusHuman,
                $direction,
                $paymentReceived,
                $payoutCompleted,
                $locale,
            ),
            'order_context_error' => null,
        ];
    }

    private function resolveStatusHuman(Task $task, string $locale): string
    {
        $name = $task->task_status?->getTranslation('name', $locale)
            ?? $task->task_status?->getTranslation('name', 'ru')
            ?? $task->task_status?->getTranslation('name', 'en');

        return is_string($name) && $name !== '' ? $name : 'unknown';
    }

    private function resolveDirectionLabel(Task $task): ?string
    {
        $from = trim((string) ($task->direction_exchange?->currency1?->payment?->name ?? ''));
        $to = trim((string) ($task->direction_exchange?->currency2?->payment?->name ?? ''));

        if ($from === '' && $to === '') {
            return null;
        }

        if ($from === '') {
            return $to;
        }

        if ($to === '') {
            return $from;
        }

        return $from.' → '.$to;
    }

    private function statusSlug(TaskStatusEnum $status): string
    {
        return match ($status) {
            TaskStatusEnum::COMPLETED => 'completed',
            TaskStatusEnum::PENDING_PAYMENT => 'pending_payment',
            TaskStatusEnum::WAITING_HANDLE => 'processing',
            TaskStatusEnum::PAID => 'paid',
            TaskStatusEnum::PROCESSING_PAYMENT => 'processing_payment',
            TaskStatusEnum::CHECK_PAYMENT => 'checking_payment',
            TaskStatusEnum::MERCHANT_CONFIRMATION => 'merchant_confirmation',
            TaskStatusEnum::PAYOUT_QUEUE => 'payout_queue',
            TaskStatusEnum::PAYOUT_IN_PROGRESS => 'payout_in_progress',
            TaskStatusEnum::AUTO_PAYOUT_ERROR => 'payout_error',
            TaskStatusEnum::REJECTED => 'rejected',
            TaskStatusEnum::CANCELED_BY_USER => 'canceled_by_user',
            TaskStatusEnum::EXPIRED => 'expired',
            TaskStatusEnum::FROZEN => 'frozen',
            TaskStatusEnum::INVALID => 'invalid',
            TaskStatusEnum::DELETED => 'deleted',
            default => 'unknown',
        };
    }

    private function isPaymentReceived(TaskStatusEnum $status): bool
    {
        return $status->in(
            TaskStatusEnum::WAITING_HANDLE,
            TaskStatusEnum::COMPLETED,
            TaskStatusEnum::PAID,
            TaskStatusEnum::PROCESSING_PAYMENT,
            TaskStatusEnum::CHECK_PAYMENT,
            TaskStatusEnum::MERCHANT_CONFIRMATION,
            TaskStatusEnum::PAYOUT_QUEUE,
            TaskStatusEnum::PAYOUT_IN_PROGRESS,
            TaskStatusEnum::AUTO_PAYOUT_ERROR,
            TaskStatusEnum::FROZEN,
        );
    }

    private function buildSafeSummary(
        string $publicId,
        ?TaskStatusEnum $statusEnum,
        string $statusHuman,
        ?string $direction,
        bool $paymentReceived,
        bool $payoutCompleted,
        string $locale,
    ): string {
        if ($payoutCompleted) {
            return $locale === 'en'
                ? "Order #{$publicId} is completed. Ask the visitor to verify receipt on their side."
                : "Заявка №{$publicId} успешно выполнена. Попросите клиента проверить поступление средств.";
        }

        if ($statusEnum === TaskStatusEnum::PENDING_PAYMENT) {
            return $locale === 'en'
                ? "Order #{$publicId} is waiting for incoming payment."
                : "Заявка №{$publicId} ожидает поступления оплаты.";
        }

        if ($statusEnum !== null && $statusEnum->in(
            TaskStatusEnum::EXPIRED,
            TaskStatusEnum::CANCELED_BY_USER,
            TaskStatusEnum::REJECTED,
            TaskStatusEnum::INVALID,
            TaskStatusEnum::DELETED,
        )) {
            return $locale === 'en'
                ? "Order #{$publicId} is closed with status: {$statusHuman}. Explain safely and offer operator follow-up if needed."
                : "Заявка №{$publicId} закрыта со статусом: {$statusHuman}. Объясните безопасно и при необходимости предложите помощь оператора.";
        }

        if ($paymentReceived) {
            $dir = $direction !== null ? " ({$direction})" : '';

            return $locale === 'en'
                ? "Order #{$publicId} is in the system with status \"{$statusHuman}\"{$dir}. Payment appears received; payout/processing is ongoing."
                : "Заявка №{$publicId} в системе со статусом «{$statusHuman}»{$dir}. Оплата получена, заявка в обработке.";
        }

        return $locale === 'en'
            ? "Order #{$publicId} status in system: {$statusHuman}."
            : "Статус заявки №{$publicId} в системе: {$statusHuman}.";
    }

    private function notFoundSummary(string $orderId, string $language): string
    {
        $locale = $this->normalizeLocale($language);

        return $locale === 'en'
            ? "Order #{$orderId} was not found. Ask the visitor to verify the order number."
            : "Заявка №{$orderId} не найдена. Попросите клиента проверить номер заявки.";
    }

    private function normalizeLocale(string $language): string
    {
        $language = strtolower(trim($language));

        return match ($language) {
            'en', 'uk', 'ka' => $language,
            default => 'ru',
        };
    }
}
