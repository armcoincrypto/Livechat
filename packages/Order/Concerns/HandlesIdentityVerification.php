<?php

namespace iEXPackages\Order\Concerns;

use App\Models\Task;
use App\Models\User;

trait HandlesIdentityVerification
{
    /**
     * Обрабатывает необходимость верификации личности для указанного заказа.
     *
     * Используется ПОСЛЕ создания заявки, чтобы пометить заказ как требующий
     * прохождения KYC/идентификации, если это предусмотрено настройками валюты/направления
     * и если выбран режим: «сначала заявка → потом верификация».
     *
     * @param Task $order Экземпляр заказа
     * @return void
     */
    protected function handleIdentityVerification(Task $order): void
    {
        $direction = $order->direction_exchange;

        if (! $direction) {
            return;
        }

        if (! $this->shouldMarkOrderForIdentityVerification($order, $direction)) {
            return;
        }

        $this->markOrderAsRequiringIdentityVerification($order, $direction);
    }

    /**
     * Нужно ли пометить заказ как требующий верификации личности.
     *
     * Этот метод отвечает ТОЛЬКО за сценарий «заявка → затем верификация»,
     * то есть когда identity_unverified_behavior = 0 и режим (mode) требует проверки.
     *
     * @param Task  $order
     * @param mixed $direction
     * @return bool
     */
    protected function shouldMarkOrderForIdentityVerification(Task $order, $direction): bool
    {
        $currencyIn = optional($direction->currency1);
        if (! $currencyIn) {
            return false;
        }

        // Базовые правила с уровня валюты (JSON identity_verification_rules)
        $rules     = $currencyIn->identity_verification_rules ?? [];
        $mode      = (string) ($rules['mode'] ?? 'disabled');
        $minAmount = isset($rules['min_amount']) ? (float) $rules['min_amount'] : 0.0;
        $behavior  = (int) ($rules['unverified_behavior'] ?? 0);

        // Переопределение с уровня направления, если включён режим «от направления»
        if ((int) ($direction->identity_verification_type ?? 0) === 1) {
            $dirRules = $direction->identity_verification_rules ?? [];

            if (!empty($dirRules['mode'])) {
                $mode = (string) $dirRules['mode'];
            }

            if (array_key_exists('min_amount', $dirRules)) {
                $minAmount = $dirRules['min_amount'] !== null
                    ? (float) $dirRules['min_amount']
                    : 0.0;
            }

            if (array_key_exists('unverified_behavior', $dirRules)) {
                $behavior = (int) $dirRules['unverified_behavior'];
            }
        }

        // Если режим отключен — верификация личности в принципе не используется
        if ($mode === 'disabled') {
            return false;
        }

        $user   = $order->user ?? null;
        $amount = $order->give_price !== null ? (float) $order->give_price : null;

        // Сначала проверяем, вообще ли выбранный mode требует KYC (always, only_new_users и т.п.)
        if (! $this->modeRequiresIdentityVerification($mode, $user, $amount, $minAmount)) {
            return false;
        }

        // Этот трейт нужен ТОЛЬКО для кейса «сначала заявка → потом верификация».
        // Это как раз unverified_behavior = 0.
        //
        // Если behavior != 0 → тут ничего не делаем (случай "сначала KYC → потом заявка"
        // должен обрабатываться Main-валидатором ДО создания заявки).
        if ($behavior !== 0) {
            return false;
        }

        return true;
    }

    /**
     * Вспомогательная логика: нужно ли вообще запрашивать верификацию по выбранному mode.
     *
     * @param string      $mode
     * @param User|null   $user
     * @param float|null  $amount
     * @param float       $minAmountThreshold
     * @return bool
     */
    protected function modeRequiresIdentityVerification(
        string $mode,
        ?User $user,
        ?float $amount,
        float $minAmountThreshold
    ): bool {
        // always — всегда
        if ($mode === 'always') {
            return true;
        }

        // only_new_users — только новые клиенты
        if ($mode === 'only_new_users') {
            if ($user === null) {
                return true; // гость/неизвестный → лучше перестраховаться
            }

            return $this->isNewUserByTasks($user);
        }

        // only_unverified — только не верифицированные
        if ($mode === 'only_unverified') {
            if ($user === null) {
                return true;
            }

            return ! $this->isUserIdentityVerified($user);
        }

        // min_amount — по сумме
        if ($mode === 'min_amount') {
            if ($amount === null) {
                return true;
            }

            return $minAmountThreshold > 0 && $amount >= $minAmountThreshold;
        }

        return false;
    }

    /**
     * Помечает заказ как требующий верификации личности:
     * - identity_verification_required = 1
     * - identity_verification_type = 0 (валюта) или 1 (направление)
     *
     * @param Task  $order
     * @param mixed $direction
     * @return void
     */
    protected function markOrderAsRequiringIdentityVerification(Task $order, $direction): void
    {
        if (! $order->meta) {
            return;
        }

        $meta = $order->meta;

        // Всегда ставим метку в meta, что заявке требуется KYC
        $meta->identity_verification_required = true;

        // Определяем источник правил (валюта или направление)
        $identityType = 0;
        if ((int) ($direction->identity_verification_type ?? 0) === 1) {
            $identityType = 1;
        }

        $meta->identity_verification_type = $identityType;
        $meta->save();

        // --- Новая логика: установка флага is_from_identity_verification на Task ---

        // Если пользователь НЕ прошёл верификацию личности — помечаем заявку как созданную для KYC
        if (! $this->isUserIdentityVerified($order->user)) {
            if (! $order->is_from_identity_verification) {
                $order->forceFill(['is_from_identity_verification' => 1])->save();
            }
        } else {
            // Если пользователь уже верифицирован — гарантируем флаг 0
            if ($order->is_from_identity_verification !== 0) {
                $order->forceFill(['is_from_identity_verification' => 0])->save();
            }
        }
    }

    /**
     * Проверка: пользователь уже прошёл идентификацию личности?
     *
     * Использует базовый флаг is_verify_account = 1.
     *
     * @param User|null $user
     * @return bool
     */
    protected function isUserIdentityVerified(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (property_exists($user, 'is_verify_account') && (int) $user->is_verify_account === 1) {
            return true;
        }

        return false;
    }

    /**
     * Новый клиент по задачам: нет ещё успешных обменов (status = 4).
     *
     * @param User|null $user
     * @return bool
     */
    protected function isNewUserByTasks(?User $user): bool
    {
        if (! $user || ! $user->id) {
            return true;
        }

        $completedCount = Task::where('id_user', $user->id)
            ->where('status', 4)
            ->count();

        return $completedCount === 0;
    }
}
