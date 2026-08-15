<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation;

use App\Models\DirectionExchange;
use iEXPackages\Order\Validation\Engine\ValidationPipeline;
use iEXPackages\Order\Validation\Engine\ValidationStep;
use iEXPackages\Order\Validation\Rules\AmlRule;
use iEXPackages\Order\Validation\Rules\AmountRule;
use iEXPackages\Order\Validation\Rules\BinInspectorRule;
use iEXPackages\Order\Validation\Rules\BlacklistRule;
use iEXPackages\Order\Validation\Rules\CurrencyAccountRule;
use iEXPackages\Order\Validation\Rules\CurrencyFieldsRule;
use iEXPackages\Order\Validation\Rules\DirectionFieldsRule;
use iEXPackages\Order\Validation\Rules\IdentityVerificationRule;
use iEXPackages\Order\Validation\Rules\LimitRule;
use iEXPackages\Order\Validation\Rules\MainRule;
use iEXPackages\Order\Validation\Rules\OtherRule;
use iEXPackages\Order\Validation\Rules\ReserveLimitRule;
use iEXPackages\Order\Validation\Rules\UserExtraFieldsRule;
use iEXPackages\Order\Validation\Rules\UserRule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;

/**
 * ValidationRegistry
 *
 * Централизованный реестр правил валидации заявки.
 *
 * Задачи класса:
 * - собрать ValidationPipeline в одном месте (без размазывания по trait'ам)
 * - зафиксировать порядок выполнения правил (priority)
 * - определить stopOnError для каждого шага
 * - создать scoped-пары шагов (in/out) без копипасты
 *
 * Важно:
 * - условия включения правил по направлению/валютам здесь намеренно НЕ используются
 *   (как договорено), кроме права unlimited.
 * - unlimited используется только для включения/выключения финансовых правил:
 *   AmountRule и ReserveLimitRule.
 */
final class ValidationRegistry
{
    /**
     * @param Container $container Laravel DI container для резолва правил (app()->make()).
     */
    public function __construct(
        private readonly Container $container
    ) {}

    /**
     * Собрать пайплайн правил валидации.
     *
     * @param DirectionExchange $direction Направление обмена (сейчас используется только
     *                                     как сигнатура/контекст, логика условий по нему не применяется).
     * @return ValidationPipeline
     */
    public function build(DirectionExchange $direction): ValidationPipeline
    {
        $pipeline = new ValidationPipeline();


        $this->add($pipeline, 'main', MainRule::class, 10, false);
        $this->add($pipeline, 'aml', AmlRule::class, 20, true);
        $this->add($pipeline, 'bin', BinInspectorRule::class, 30, false);
        $this->add($pipeline, 'direction', DirectionFieldsRule::class, 40, true);
        $this->add($pipeline, 'blacklist', BlacklistRule::class, 45, true);
        $this->add($pipeline, 'user', UserRule::class, 55, true);
        $this->add($pipeline, 'identity', IdentityVerificationRule::class, 60, true);
        $this->add($pipeline, 'other', OtherRule::class, 70, true);
        $this->add($pipeline, 'limits', LimitRule::class, 80, true);

        $this->addScopedPair($pipeline, 'currency_account', CurrencyAccountRule::class, 90, true);
        $this->addScopedPair($pipeline, 'currency_fields', CurrencyFieldsRule::class, 100, true);

        $this->add($pipeline, 'user_extra_fields', UserExtraFieldsRule::class, 56, true);

        if (!Gate::allows('admin_unlimited_order_creation')) {
            $this->add($pipeline, 'amount', AmountRule::class, 110, true);
            $this->add($pipeline, 'reserve', ReserveLimitRule::class, 120, true);
        }

        return $pipeline;
    }

    /**
     * Добавить одиночный шаг в пайплайн.
     *
     * @param ValidationPipeline $pipeline
     * @param string $id Уникальный ID шага (используется также selector'ом)
     * @param class-string $ruleClass Класс правила (реализует ValidationRuleInterface)
     * @param int $priority Приоритет (меньше — раньше)
     * @param bool $stopOnError Остановить пайплайн, если правило вернуло ошибку
     * @param array<string,mixed> $options Опции шага (передаются в ValidationContext->options)
     */
    private function add(
        ValidationPipeline $pipeline,
        string $id,
        string $ruleClass,
        int $priority,
        bool $stopOnError,
        array $options = []
    ): void {
        $pipeline->add(new ValidationStep(
            id: $id,
            rule: $this->container->make($ruleClass),
            priority: $priority,
            stopOnError: $stopOnError,
            options: $options
        ));
    }

    /**
     * Добавить пару шагов _in/_out с options.scope.
     *
     * Пример:
     * - currency_account_in  -> options: ['scope' => 'in']
     * - currency_account_out -> options: ['scope' => 'out']
     *
     * @param ValidationPipeline $pipeline
     * @param string $baseId Базовый ID (без суффикса)
     * @param class-string $ruleClass Класс правила
     * @param int $priorityBase Приоритет для *_in, для *_out будет priorityBase + 1
     * @param bool $stopOnError
     */
    private function addScopedPair(
        ValidationPipeline $pipeline,
        string $baseId,
        string $ruleClass,
        int $priorityBase,
        bool $stopOnError
    ): void {
        $this->add($pipeline, $baseId . '_in', $ruleClass, $priorityBase, $stopOnError, ['scope' => 'in']);
        $this->add($pipeline, $baseId . '_out', $ruleClass, $priorityBase + 1, $stopOnError, ['scope' => 'out']);
    }
}
