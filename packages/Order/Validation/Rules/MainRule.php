<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use App\Models\Task;
use Carbon\Carbon;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;

/**
 * MainRule — базовые запреты/проверки перед созданием заявки.
 *
 * Перенос MainValidator в новую систему:
 * - не использует request()/auth()
 * - возвращает ValidationResult
 *
 * Проверяет:
 * 1) Если у направления есть города — требуем city_id и country
 * 2) Если валюта "Отдаю" запрещает создание (is_allow_order == 1) — блокируем
 * 3) Если у пользователя слишком много заявок в статусах pending/process за последний час — блокируем
 * 4) Если направление на холдинге — блокируем
 * 5) Если IP клиента в списке not_ip направления — блокируем
 */
final class MainRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        $authId = (int)($context->data->get('authId') ?? 0);
        $ip = $context->env->ip();

        $currencyIn = $direction->currency1;

        // 1) город/страна
        if ($this->isMissingLocationData($context, $direction)) {
            $result->addError('sell', __('Укажите город и страну'), 'location_required');
            // здесь не return, т.к. у тебя валидатор собирал все ошибки
        }

        // 2) запрет на создание по валюте "Отдаю" (оставил как в твоём коде)
        if ($currencyIn && (int)($currencyIn->is_allow_order ?? 0) === 1) {
            $result->addError('buy', __('На данный момент прием платежей по этой системе приостановлен'), 'currency_order_disabled');
        }

        // 3) неоплаченные/в процессе (только если есть пользователь)
        if ($authId > 0 && $currencyIn) {
            $pendingLimit = (int)($currencyIn->hour_limit_order_pending ?? 0);
            $processLimit = (int)($currencyIn->hour_limit_order_process ?? 0);

            if (
                $this->hasExceededTaskLimit($authId, 2, $pendingLimit, 1) ||
                $this->hasExceededTaskLimit($authId, 9, $processLimit, 1)
            ) {
                $result->addError('sell', __('Невозможно создать новую заявку, так как у вас есть неоплаченные заявки'), 'unpaid_orders_exist');
            }
        }

        // 4) холдинг направления
        if ((int)($direction->is_holding_direction ?? 0) === 1) {
            $result->addError('sell', __('Обмен по этому направлению временно недоступен'), 'direction_holding');
        }

        // 5) not_ip
        if ($this->isIpBlockedByDirection($direction, $ip)) {
            $result->addError('limit_order', __('Вы не можете создать заявку, обратитесь к оператору'), 'ip_blocked_by_direction');
        }

        return $result;
    }

    /**
     * Проверка: требуется ли город и страна.
     */
    private function isMissingLocationData(ValidationContext $context, DirectionExchange $direction): bool
    {
        // Если у направления есть список городов — требуем city_id и country
        $cities = $direction->direction_exchange_cities ?? null;

        $hasCities = is_iterable($cities) && count($cities) > 0;

        if (!$hasCities) {
            return false;
        }

        $cityId = $context->data->get('city_id');
        $country = $context->data->getString('country');

        return empty($cityId) || $country === null;
    }

    /**
     * Проверка IP по списку not_ip (каждый IP с новой строки).
     */
    private function isIpBlockedByDirection(DirectionExchange $direction, string $clientIp): bool
    {
        $raw = (string)($direction->not_ip ?? '');
        $raw = trim($raw);

        if ($raw === '') {
            return false;
        }

        $list = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $list = array_values(array_filter(array_map('trim', $list)));

        if ($list === []) {
            return false;
        }

        return in_array($clientIp, $list, true);
    }

    /**
     * Проверка лимита заявок пользователя по статусу за последние N часов.
     *
     * @param int $userId
     * @param int $status
     * @param int $limit
     * @param int $hours
     */
    private function hasExceededTaskLimit(int $userId, int $status, int $limit, int $hours = 1): bool
    {
        if ($limit <= 0) {
            return false;
        }

        $count = Task::query()
            ->where('id_user', $userId)
            ->where('status', $status)
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->count();

        return $count > $limit;
    }
}
