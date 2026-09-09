<?php

declare(strict_types=1);

namespace iEXPackages\SmartMailer;

use App\Enums\TaskStatusEnum;
use App\Models\Task;

/**
 * Resolve customer-facing order status labels for transactional mail locales.
 */
trait LocalizesOrderMailStatus
{
    protected function localizedOrderStatusName(Task $order, ?string $locale = null): string
    {
        $statusId = (int) ($order->status ?? 0);
        $key = 'smart-mailer::messages.status_'.$statusId;
        $translated = __($key);

        if (is_string($translated) && $translated !== $key) {
            return $translated;
        }

        $status = $order->task_status;
        if ($status === null) {
            return (string) ($order->status ?? '');
        }

        $locale = $locale ?: ($this->locale ?? app()->getLocale());
        $fromDb = $status->getTranslation('name', $locale, false);
        if (is_string($fromDb) && $fromDb !== '') {
            return $fromDb;
        }

        if ($statusId === TaskStatusEnum::PENDING_PAYMENT->value) {
            return __('smart-mailer::messages.status_2');
        }

        return (string) $status->name;
    }
}
