<?php

namespace iEXPackages\Transaction\Services;

use App\Models\Task;
use App\Models\UserWalletStories;

class UserWalletStoriesService
{
    public function storeWallets(?Task $transaction): void
    {
        $this->storeUserWallet(
            $transaction->id_user,
            $this->sanitizeWallet($transaction->from_shot),
            $transaction->direction_exchange->id_currency1,
            $transaction->task_info->dot_not_remember_data
        );

        $this->storeUserWallet(
            $transaction->id_user,
            $this->sanitizeWallet($transaction->to_shot),
            $transaction->direction_exchange->id_currency2,
            $transaction->task_info->dot_not_remember_data
        );
    }

    private function storeUserWallet(int $userId, ?string $wallet, int $currencyId, bool $doNotRememberData): void
    {
        if (empty($wallet) || $doNotRememberData || (int)iEXSetting('is_saved_user_stories') !== 0) {
            return;
        }

        $walletRecord = UserWalletStories::firstOrNew([
            'id_user' => $userId,
            'wallet' => security_xss($wallet),
            'id_currency' => $currencyId,
        ]);

        if ($walletRecord->exists) {
            $walletRecord->increment('usage_count');
        } else {
            $walletRecord->usage_count = 1;
            $walletRecord->save();
        }
    }

    /**
     * Очищает кошелёк от лишних символов и пробелов.
     *
     * @param string|null $wallet
     * @return string|null
     */
    private function sanitizeWallet(?string $wallet): ?string
    {
        if (empty($wallet)) {
            return null;
        }

        // Удаляем всё, кроме букв и цифр (включая пробелы и спецсимволы)
        return security_xss(preg_replace('/[^a-zA-Z0-9]/', '', $wallet));
    }
}
