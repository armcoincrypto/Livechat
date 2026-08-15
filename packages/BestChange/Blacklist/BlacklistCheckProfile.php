<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist;

/**
 * Профиль проверки (какие поля проверять и как их маппить в "field" результата).
 */
final readonly class BlacklistCheckProfile
{
    public function __construct(
        /** Проверять email */
        public bool $checkEmail = true,
        /** Проверять "кошелёк отдаю" */
        public bool $checkWalletFrom = true,
        /** Проверять "кошелёк получаю" */
        public bool $checkWalletTo = true,

        /**
         * Какие имена полей возвращать наружу (под твой валидатор/формат).
         * Например: email/email, wallet_from/sell, wallet_to/buy.
         */
        public string $fieldEmail = 'email',
        public string $fieldWalletFrom = 'sell',
        public string $fieldWalletTo = 'buy',
    ) {}
}
