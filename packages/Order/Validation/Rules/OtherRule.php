<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use App\Models\Task;
use App\Models\User;
use iEXPackages\GeoIp\AntiFraud\GeoPolicy;
use iEXPackages\GeoIp\Facades\GeoIP;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

/**
 * OtherRule — прочие ограничения направления:
 * - язык
 * - устройство
 * - гео-доступ
 * - минимальное количество успешных обменов
 *
 * Перенос OtherValidator в новую систему:
 * - ошибки возвращаем через ValidationResult
 * - IP берём из Environment
 * - userId берём из DataBag('authId') или из ResourceBag(User)
 *
 * Важно:
 * - GeoIP использует IP из env, не request()
 * - Устройство: текущая реализация через Agent (UA из request), см. комментарий выше
 */
final class OtherRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        // 1) язык
        if ($this->isLanguageRestricted($context, $direction)) {
            return $result->addError(
                'sell',
                __('Обмен по выбранному направлению недоступен для вашего языка.'),
                'language_restricted'
            );
        }

        // 2) устройство
        if ($this->isDeviceRestricted($context, $direction)) {
            return $result->addError(
                'sell',
                __('Ваше устройство не поддерживается для выбранного направления обмена.'),
                'device_restricted'
            );
        }

        // 3) гео
        if ($this->isGeoAccessRestricted($context, $direction)) {
            // stopOnFail в старом коде — тут мы просто возвращаем сразу
            return $result->addError(
                'get_ip_validate',
                __('Обмен по выбранному направлению недоступен в вашей стране.'),
                'geo_restricted'
            );
        }

        // 4) минимум успешных обменов
        if (!$this->hasSufficientCompletedOrders($context, $direction)) {
            return $result->addError(
                'limit_order',
                __('Для создания заявки вам необходимо больше успешных обменов.'),
                'min_completed_orders_required'
            );
        }

        return $result;
    }

    /**
     * Ограничение по языку.
     * direction.languages: "ru,en,..." — если заполнено и текущей локали нет в списке — блок.
     */
    private function isLanguageRestricted(ValidationContext $context, DirectionExchange $direction): bool
    {
        $allowedLanguages = (string)($direction->languages ?? '');
        $allowedLanguages = trim($allowedLanguages);

        if ($allowedLanguages === '') {
            return false;
        }

        $currentLocale = $context->env->locale() ?: app()->getLocale();

        return !Str::of($allowedLanguages)
            ->explode(',')
            ->map(fn($v) => trim((string)$v))
            ->filter()
            ->contains($currentLocale);
    }

    /**
     * Ограничение по устройству.
     * direction.device: "mobile,desktop,tablet" — если заполнено и текущего устройства нет — блок.
     */
    private function isDeviceRestricted(ValidationContext $context, DirectionExchange $direction): bool
    {
        $allowedDevices = (string)($direction->device ?? '');
        $allowedDevices = trim($allowedDevices);

        if ($allowedDevices === '') {
            return false;
        }

        // Если у тебя фронт уже присылает device_type — можно использовать его:
        // $device = $context->data->getString('device_type') ?: $this->detectUserDevice();
        $device = $this->detectUserDevice();

        return !Str::of($allowedDevices)
            ->explode(',')
            ->map(fn($v) => trim((string)$v))
            ->filter()
            ->contains($device);
    }

    /**
     * Geo-ограничение:
     * - если geoip выключен — false
     * - ip берём из Environment
     * - allow/deny ISO берём из отношений направления
     */
    private function isGeoAccessRestricted(ValidationContext $context, DirectionExchange $direction): bool
    {
        if (!(bool)iEXSetting('geoip.toggles.enabled', true)) {
            return false;
        }

        $ip = $context->env->ip();
        if (!is_string($ip) || trim($ip) === '') {
            return false;
        }

        $forbiddenCountries = $direction->direction_forbidden_countries;
        $allowedCountries   = $direction->direction_allowed_countries;

        $denyIso = $forbiddenCountries?->pluck('code')
            ?->filter()
            ?->map(fn($v) => strtoupper(trim((string)$v)))
            ?->values()
            ?->all() ?? [];

        $allowIso = $allowedCountries?->pluck('code')
            ?->filter()
            ?->map(fn($v) => strtoupper(trim((string)$v)))
            ?->values()
            ?->all() ?? [];

        try {
            $loc = GeoIP::locate($ip);
        } catch (\Throwable) {
            // как у тебя: если GeoIP сломался — безопаснее ограничить
            return true;
        }

        $geoPolicy = app(GeoPolicy::class);

        $check = $geoPolicy->check(
            loc: $loc,
            allowIso: $allowIso,
            denyIso: $denyIso,
            locale: $context->env->locale() ?: 'ru',
            denyIfUnknown: false
        );

        return !$check->allowed;
    }

    /**
     * Минимальное число успешных обменов клиента.
     */
    private function hasSufficientCompletedOrders(ValidationContext $context, DirectionExchange $direction): bool
    {
        $requiredCount = (int)($direction->min_count_exchanges_client ?? 0);
        if ($requiredCount === 0) {
            return true;
        }

        $userId = $this->resolveUserId($context);
        if ($userId <= 0) {
            return false;
        }

        $completedCount = Task::query()
            ->where('id_user', $userId)
            ->where('status', 4)
            ->count();

        return $completedCount >= $requiredCount;
    }

    /**
     * Получить userId из контекста:
     * - сначала authId из data
     * - иначе из ресурса User
     */
    private function resolveUserId(ValidationContext $context): int
    {
        $authId = (int)($context->data->get('authId') ?? 0);
        if ($authId > 0) {
            return $authId;
        }

        if ($context->resources->has(User::class)) {
            /** @var User $u */
            $u = $context->resources->get(User::class);
            return (int)$u->id;
        }

        return 0;
    }

    /**
     * Определение устройства пользователя.
     *
     * ⚠️ Agent читает user-agent из request(). Если хочешь полностью убрать request:
     * - передавай device_type с фронта в data
     * - или передавай userAgent в env и парси по строке
     */
    private function detectUserDevice(): string
    {
        $agent = new Agent();

        return match (true) {
            $agent->isMobile() => 'mobile',
            $agent->isTablet() => 'tablet',
            default => 'desktop',
        };
    }
}
