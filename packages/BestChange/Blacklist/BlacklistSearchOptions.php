<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Blacklist;

use iEXPackages\BestChange\Blacklist\Enums\BlacklistRecordType;
use iEXPackages\BestChange\Blacklist\Enums\BlacklistWhere;

/**
 * Параметры поиска по базе BestChange.
 */
final readonly class BlacklistSearchOptions
{
    public function __construct(
        /** Где искать (контакты/описание/оба) */
        public BlacklistWhere $where = BlacklistWhere::ContactsAndDescription,
        /** Тип записей (мошенники/неадекваты/оба) */
        public BlacklistRecordType $type = BlacklistRecordType::ScamAndInadequate,
        /**
         * Разрешить пустой query.
         *
         * ВНИМАНИЕ: пустой query в BestChange выдаёт всю базу (тысячи записей).
         * Использовать только для админских задач, и обязательно с ограничениями/защитой.
         */
        public bool $allowEmptyQuery = false,
    ) {}
}
