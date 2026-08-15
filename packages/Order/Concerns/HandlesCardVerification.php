<?php

namespace iEXPackages\Order\Concerns;

use App\Models\Task;
use App\Models\VerificationCard;
use Brick\Math\BigDecimal;

trait HandlesCardVerification
{
    /**
     * Обрабатывает процесс верификации карты для указанного заказа.
     *
     * @param Task $order Экземпляр заказа
     * @return void
     */
    protected function handleCardVerification(Task $order): void
    {
        $direction = $order->direction_exchange;

        if ($this->shouldOrderBeVerified($order, $direction)) {
            $this->markOrderForVerificationIfNeeded($order);
        }
    }

    protected function shouldOrderBeVerified(Task $order, $direction): bool
    {
        // Если направление отсутствует — проверяем настройки валюты
        if (!$direction) {
            return $this->isCardVerificationRequired($order, null);
        }

        // card_verification_type:
        // 0 или null — использовать валюту
        // 1 — использовать настройки направления (direction)
        // 2 — индивидуальный конструктор (расчёт на фронте, результат кладётся в meta->card_verification_required)
        $cardType = (int) ($direction->card_verification_type ?? 0);

        // Тип 2 — индивидуальный конструктор: доверяем флагу из meta
        if ($cardType === 2 && isset($order->meta)) {
            return (bool) ($order->meta->card_verification_required ?? false);
        }

        // Во всех остальных случаях — проверяем настройки валюты/направления
        return $this->isCardVerificationRequired($order, $direction);
    }

    /**
     * Проверяет, требуется ли верификация карты, исходя из настроек валюты и/или направления.
     *
     * Режимы (для валюты и направления едины):
     * 0 — не требовать
     * 1 — всегда требовать
     * 2 — требовать, если сумма >= min_amount_verification
     * 3 — только для клиентов без идентификации
     * 4 — только для новой / не верифицированной карты
     * 5 — для клиентов с подозрительной историей
     *
     * @param Task       $order     Экземпляр заказа
     * @param mixed|null $direction Связанное направление (может быть null)
     * @return bool Возвращает true, если верификация нужна
     */
    protected function isCardVerificationRequired(Task $order, $direction = null): bool
    {
        $currency = $this->getInCurrency();
        if (!$currency) {
            return false;
        }

        // Базовый режим и минимальная сумма — с уровня валюты
        $mode      = (int) ($currency->is_enabled_verification ?? 0);
        $minAmount = (string) ($currency->min_amount_verification ?? '0');

        // При необходимости учитываем настройки направления (режим "Из настроек направления")
        if ($direction && !empty($direction->card_verification_type)) {
            $cardType = (int) ($direction->card_verification_type ?? 0);
            $rules    = $direction->card_verification_rules ?? [];
            $dirCfg   = $rules['direction'] ?? [];

            // card_verification_type = 1 — используем режим и порог с уровня направления
            if ($cardType === 1) {
                if (array_key_exists('mode', $dirCfg)) {
                    $mode = (int) $dirCfg['mode'];
                }
                if (array_key_exists('min_amount', $dirCfg)) {
                    $minAmount = (string) $dirCfg['min_amount'];
                }
            }
        }

        // 0 — выключено
        if ($mode === 0) {
            return false;
        }

        // 1 — всегда требуем
        if ($mode === 1) {
            return true;
        }

        // 2 — по сумме (>= minAmount)
        if ($mode === 2) {
            try {
                $give = BigDecimal::of((string) $order->give_price);
                $min  = BigDecimal::of($minAmount);
                return $give->compareTo($min) >= 0;
            } catch (\Throwable $e) {
                // На некорректных данных — безопасно не требуем
                return false;
            }
        }

        // 3 — только для клиентов без идентификации
        if ($mode === 3) {
            return !$this->isUserIdentifiedForOrder($order);
        }

        // 4 — только для новой/не верифицированной карты
        if ($mode === 4) {
            return !$this->isCardAlreadyVerified($order);
        }

        // 5 — для клиентов с подозрительной историей
        if ($mode === 5) {
            return $this->hasRiskHistory($order);
        }

        return false;
    }

    /**
     * Помечает заказ как требующий верификации карты, если карта ранее не была верифицирована.
     *
     * @param Task $order Экземпляр заказа
     * @return void
     */
    protected function markOrderForVerificationIfNeeded(Task $order): void
    {
        // Normalized identifier (spaces removed; alnum preserved)
        $accountNumber = app(\App\Services\Verification\VerificationIdentifierVault::class)
            ->normalize((string) $order->from_shot);
        $emailNorm     = mb_strtolower(trim((string) $order->email));
        $currencyId    = $order->direction_exchange->id_currency1;

        if ($currencyId === null || $accountNumber === '') {
            return;
        }

        $alreadyVerified = VerificationCard::query()
            ->where('status', 1)
            ->where('id_currency', $currencyId)
            ->whereIdentifier($accountNumber)
            ->when($emailNorm !== '', fn($q) => $q->whereRaw('LOWER(email) = ?', [$emailNorm]))
            ->exists();

        if (!$alreadyVerified && !$order->is_from_verification_card) {
            // Обновляем только если ещё не проставлено
            $order->forceFill(['is_from_verification_card' => 1])->save();
        }
    }

    /**
     * Определяет, является ли пользователь заказа идентифицированным.
     *
     * Использует базовые признаки:
     * - is_verify_account = 1
     */
    protected function isUserIdentifiedForOrder(Task $order): bool
    {
        $user = $order->user ?? null;

        if (! $user) {
            return false;
        }

        // Проверяем Eloquent-атрибут is_verify_account без использования property_exists,
        // так как это динамический атрибут модели, а не объявленное свойство класса.
        return (int) ($user->is_verify_account ?? 0) === 1;
    }

    /**
     * Проверяет, была ли эта карта уже верифицирована ранее.
     *
     * Логика аналогична markOrderForVerificationIfNeeded(), но без установки флагов.
     */
    protected function isCardAlreadyVerified(Task $order): bool
    {
        $accountNumber = app(\App\Services\Verification\VerificationIdentifierVault::class)
            ->normalize((string) $order->from_shot);
        $emailNorm     = mb_strtolower(trim((string) $order->email));
        $currencyId    = $order->direction_exchange->id_currency1 ?? null;

        if ($currencyId === null || $accountNumber === '') {
            return false;
        }

        return VerificationCard::query()
            ->where('status', 1)
            ->where('id_currency', $currencyId)
            ->whereIdentifier($accountNumber)
            ->when($emailNorm !== '', fn($q) => $q->whereRaw('LOWER(email) = ?', [$emailNorm]))
            ->exists();
    }

    /**
     * Проверяет, есть ли у пользователя подозрительная история заявок.
     *
     * Здесь можно завязаться на собственную бизнес-логику:
     * - флаги в профиле пользователя
     * - количество отклонённых/заблокированных заявок
     * - результаты антифрода и т.д.
     *
     * Пока реализовано в виде простого примера: считаем рискованным,
     * если есть хотя бы одна заявка со статусом "мошенничество" (status = 9, пример).
     */
    protected function hasRiskHistory(Task $order): bool
    {
        $userId = $order->id_user ?? null;
        if (!$userId) {
            return false;
        }

        return Task::query()
            ->where('id_user', $userId)
            ->where('status', 9) // пример статуса "подозрительная / мошенническая заявка"
            ->exists();
    }
}
