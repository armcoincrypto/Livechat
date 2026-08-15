<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem;

use App\Settings\ReferralConfig;
use iEXPackages\ReferralSystem\Models\ReferralProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * ReferralCookieManager
 *
 * Отвечает за:
 *  - извлечение реферального кода из запроса (ref/partnerId)
 *  - безопасную нормализацию кода
 *  - создание cookie `ref` с учётом настроек ReferralConfig
 *
 * Cookie содержит:
 *  - ref-code (строка)
 *  - или служебный формат "partnerId:{id}"
 */
final class ReferralCookieManager
{
    public const string COOKIE_NAME = 'ref';

    private const int MAX_REF_LENGTH = 64;
    private const string PARTNER_PREFIX = 'partnerId:';

    public function __construct(
        private readonly ReferralConfig $config,
    ) {}

    /**
     * Создать cookie с реферальным значением.
     *
     * Правила хранения:
     *  - storagePeriods() === 0 => cookie forever
     *  - иначе => lifetimeDay() * 1440 минут (минимум 1 день)
     *
     * @param string $refValue ref-code или "partnerId:{id}"
     * @return SymfonyCookie
     */
    public function makeCookie(string $refValue, ?ReferralProgram $program = null): SymfonyCookie
    {
        $refValue = $this->normalizeRefValue($refValue);
        if ($refValue === '') {
            return Cookie::make(self::COOKIE_NAME, '', 0);
        }

        $minutes = $this->resolveLifetimeMinutes($program);

        // forever
        if ($minutes === 0) {
            return Cookie::forever(self::COOKIE_NAME, $refValue);
        }

        return Cookie::make(self::COOKIE_NAME, $refValue, $minutes);
    }

    /**
     * Извлечь реферальное значение из запроса.
     *
     * Поддерживает:
     *  - ?ref=CODE
     *  - ?partnerId=123 -> "partnerId:123"
     *
     * @return string|null null если параметров нет или они невалидны
     */
    public function extractRef(Request $request): ?string
    {
        if ($request->filled('ref')) {
            $ref = $this->normalizeRefCode((string) $request->input('ref'));
            return $ref !== '' ? $ref : null;
        }

        if ($request->filled('partnerId')) {
            $id = $this->normalizePartnerId((string) $request->input('partnerId'));
            return $id !== null ? self::PARTNER_PREFIX . $id : null;
        }

        return null;
    }

    /**
     * Нормализует значение для хранения в cookie:
     *  - ref-code
     *  - partnerId:{id}
     */
    public function normalizeRefValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, self::PARTNER_PREFIX)) {
            $idRaw = substr($value, strlen(self::PARTNER_PREFIX));
            $id = $this->normalizePartnerId($idRaw);

            return $id !== null ? self::PARTNER_PREFIX . $id : '';
        }

        return $this->normalizeRefCode($value);
    }

    /**
     * ref-code:
     *  - uppercase
     *  - ограничение длины
     *  - whitelist символов: A-Z 0-9 _ -
     */
    private function normalizeRefCode(string $raw): string
    {
        $raw = security_xss($raw);
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        // ограничение длины
        if (mb_strlen($raw) > self::MAX_REF_LENGTH) {
            $raw = mb_substr($raw, 0, self::MAX_REF_LENGTH);
        }

        /**
         * ВАЖНО:
         * ref может быть hashids и быть регистрозависимым,
         * поэтому НЕ делаем strtoupper().
         *
         * Разрешаем безопасные символы:
         *  - буквы любого регистра
         *  - цифры
         *  - _ -
         */
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $raw)) {
            return '';
        }

        return $raw;
    }

    /**
     * partnerId:
     *  - только цифры
     *  - > 0
     */
    private function normalizePartnerId(string $raw): ?int
    {
        $raw = security_xss($raw);
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (!preg_match('/^\d+$/', $raw)) {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * Определить lifetime cookie (в минутах):
     *  1) если у программы задан lifetime_minutes > 0 — используем его
     *  2) иначе используем глобальный lifetimeDay (в днях) -> минуты
     *  3) если storagePeriods() === 0 — cookie forever (вернём 0)
     */
    public function resolveLifetimeMinutes(?ReferralProgram $program = null): int
    {
        // 1) индивидуально для программы (в минутах)
        if ($program && (int) $program->lifetime_minutes > 0) {
            return (int) $program->lifetime_minutes;
        }

        // 2) глобально: либо forever, либо days -> minutes
        if ($this->config->storagePeriods() === 0) {
            return 0; // forever
        }

        $days = max(1, $this->config->lifetimeDay());
        return $days * 1440;
    }
}
