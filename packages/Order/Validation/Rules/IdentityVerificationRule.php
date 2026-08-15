<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Rules;

use App\Models\DirectionExchange;
use App\Models\Task;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use iEXPackages\Order\Validation\Contracts\ValidationRuleInterface;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Result\ValidationResult;
use Illuminate\Support\Facades\Log;

/**
 * IdentityVerificationRule — проверка необходимости идентификации личности.
 *
 * Правило определяет, нужно ли требовать прохождение идентификации на этапе создания заявки.
 *
 * Источники настроек:
 * 1) currency1.identity_verification_rules (базовые правила)
 * 2) direction.identity_verification_type + direction.identity_verification_rules (если включено переопределение)
 *
 * Алгоритм:
 * - behavior === 0 => не блокируем на создании (идентификация после создания заявки)
 * - mode:
 *   - disabled => не требовать
 *   - always => требовать всегда
 *   - only_new_users => только новым (нет успешных заявок)
 *   - only_unverified => только неидентифицированным
 *   - min_amount => при сумме >= min_amount
 *
 * Если требуется идентификация и пользователь не идентифицирован — возвращаем ошибку:
 * field = identity_verification, modal = true.
 *
 * Важно:
 * - Не использует request()/auth()
 * - Возвращает ValidationResult
 */
final class IdentityVerificationRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ValidationResult
    {
        $result = ValidationResult::ok();

        /** @var DirectionExchange $direction */
        $direction = $context->resources->get(DirectionExchange::class);

        $required = $this->isIdentityVerificationRequired($context, $direction);

        if (\App\Services\Orders\PaymentDestinationRouter::isZelleInbound($direction)) {
            $required = true;
        }

        if (!$required) {
            return $result;
        }

        if ($this->isUserIdentified($context)) {
            return $result;
        }

        $code = \App\Services\Orders\PaymentDestinationRouter::isZelleInbound($direction)
            ? 'VERIFICATION_REQUIRED'
            : 'identity_verification_required';

        return $result->addError(
            field: 'identity_verification',
            message: __('Для продолжения обмена требуется пройти идентификацию личности.'),
            code: $code,
            modal: $code !== 'VERIFICATION_REQUIRED',
            meta: [
                'verification_url' => \App\Services\Orders\PaymentDestinationRouter::verificationUrl(
                    $context->env->locale()
                ),
            ]
        );
    }

    /**
     * Нужно ли требовать идентификацию по настройкам валюты/направления.
     */
    private function isIdentityVerificationRequired(ValidationContext $context, DirectionExchange $direction): bool
    {
        $currencyIn = $direction->currency1;
        if (!$currencyIn) {
            return false;
        }

        // Базовые настройки с уровня валюты
        $currencyRules = is_array($currencyIn->identity_verification_rules ?? null)
            ? $currencyIn->identity_verification_rules
            : [];

        $mode = (string)($currencyRules['mode'] ?? 'disabled');
        $minAmountThreshold = array_key_exists('min_amount', $currencyRules)
            ? $this->readMoney($currencyRules['min_amount'])
            : null;

        $behavior = (int)($currencyRules['unverified_behavior'] ?? 0);

        // Переопределение на уровне направления
        $identityType = (int)($direction->identity_verification_type ?? 0);
        if ($identityType === 1) {
            $dirRules = is_array($direction->identity_verification_rules ?? null)
                ? $direction->identity_verification_rules
                : [];

            if (!empty($dirRules['mode'])) {
                $mode = (string)$dirRules['mode'];
            }

            if (array_key_exists('min_amount', $dirRules)) {
                $minAmountThreshold = $this->readMoney($dirRules['min_amount']);
            }

            if (array_key_exists('unverified_behavior', $dirRules)) {
                $behavior = (int)$dirRules['unverified_behavior'];
            }
        }

        // behavior=0 => не блокируем на создании заявки
        if ($behavior === 0) {
            return false;
        }

        if ($mode === '' || $mode === 'disabled') {
            return false;
        }

        if ($mode === 'always') {
            return true;
        }

        if ($mode === 'only_new_users') {
            return $this->isNewUser($context);
        }

        if ($mode === 'only_unverified') {
            return !$this->isUserIdentified($context);
        }

        if ($mode === 'min_amount') {
            $amount = $this->getIncomeAmount($context); // BigDecimal|null

            if ($amount === null || $minAmountThreshold === null) {
                return false;
            }

            if ($minAmountThreshold->isLessThanOrEqualTo('0')) {
                return false;
            }

            return $amount->isGreaterThanOrEqualTo($minAmountThreshold);
        }

        return false;
    }

    /**
     * Получить сумму "Отдаю" как BigDecimal (без float).
     */
    private function getIncomeAmount(ValidationContext $context): ?BigDecimal
    {
        // Берём из данных формы
        $raw = $context->data->getString('income_amount');
        return $this->readMoney($raw);
    }

    /**
     * Проверка: пользователь идентифицирован?
     *
     * Источники:
     * - если в ResourceBag есть User — используем его
     * - иначе берём authId из data ('authId') и ищем User::find()
     */
    private function isUserIdentified(ValidationContext $context): bool
    {
        $user = $this->resolveUser($context);
        if (!$user) {
            return false;
        }

        // Основной флаг, как у тебя
        if (isset($user->is_verify_account)) {
            return (int)$user->is_verify_account === 1;
        }

        return false;
    }

    /**
     * Новый пользователь: нет успешных заявок (status=4).
     */
    private function isNewUser(ValidationContext $context): bool
    {
        $authId = (int)($context->data->get('authId') ?? 0);

        // Нет authId => гость => считаем новым
        if ($authId <= 0) {
            return true;
        }

        return !Task::query()
            ->where('id_user', $authId)
            ->where('status', 4)
            ->exists();
    }

    /**
     * Получить пользователя из контекста.
     */
    private function resolveUser(ValidationContext $context): ?User
    {
        // 1) если ресурс User положили заранее — используем
        if ($context->resources->has(User::class)) {
            /** @var User $u */
            $u = $context->resources->get(User::class);
            return $u;
        }

        // 2) иначе попробуем по authId из data
        $authId = (int)($context->data->get('authId') ?? 0);
        if ($authId <= 0) {
            return null;
        }

        try {
            return User::find($authId);
        } catch (\Throwable $e) {
            Log::warning('IdentityVerificationRule: failed to load user', [
                'auth_id' => $authId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Нормализация денежного значения в BigDecimal.
     *
     * Принимает:
     * - число
     * - строку ("1 234,50", "1234.50")
     * - null
     */
    private function readMoney(mixed $raw, int $scale = 18): ?BigDecimal
    {
        if ($raw === null) return null;

        $s = trim((string)$raw);
        if ($s === '') return null;

        $s = str_replace(["\u{00A0}", ' '], '', $s);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/\.$/', '', $s) ?? $s;

        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $s)) {
            return null;
        }

        try {
            return BigDecimal::of($s)->toScale($scale, RoundingMode::DOWN);
        } catch (\Throwable) {
            return null;
        }
    }
}
