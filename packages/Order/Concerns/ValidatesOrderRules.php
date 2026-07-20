<?php
declare(strict_types=1);

namespace iEXPackages\Order\Concerns;

use App\Models\Task;
use App\Models\User;
use iEXPackages\Order\Validation\Context\DataBag;
use iEXPackages\Order\Validation\Context\Environment;
use iEXPackages\Order\Validation\Context\ResourceBag;
use iEXPackages\Order\Validation\Context\ValidationContext;
use iEXPackages\Order\Validation\Effects\EffectsApplier;
use iEXPackages\Order\Validation\Engine\ValidationEngine;
use iEXPackages\Order\Validation\Engine\ValidationPipeline;
use iEXPackages\Order\Validation\Enums\EffectType;
use iEXPackages\Order\Validation\ValidationRegistry;

/**
 * Trait ValidatesOrderRules
 *
 * Обеспечивает:
 * - сбор ValidationContext (data/env/resources)
 * - запуск пайплайна валидации через Registry + Engine
 * - сохранение эффector'ов (effects) для применения после создания заявки
 *
 * Важно:
 * - validateOrder() НЕ делает побочных действий (не пишет в БД)
 * - applyValidationEffects() применяется строго ПОСЛЕ создания Task
 */
trait ValidatesOrderRules
{
    /**
     * Эффекты, собранные во время валидации.
     * Применяются после создания заявки через EffectsApplier.
     *
     * @var array<int, array{type:EffectType,payload:array<string,mixed>}>
     */
    protected array $validationEffects = [];

    /**
     * Запустить валидацию заявки.
     *
     * Возвращает:
     * - [] если ошибок нет
     * - массив ошибок в legacy-формате (field/message/...) если есть ошибки
     *
     * @return array<int, array<string,mixed>>|array
     */
    protected function validateOrder(): array
    {
        /**
         * Подгружаем нужные отношения заранее, чтобы:
         * - правила не вызывали lazy-loading (N+1)
         * - доступ к currency/payment/code был быстрым и стабильным
         */
        $this->directionId->loadMissing([
            'currency1.code_currency',
            'currency1.payment',
            'currency1.designation_xml',
            'currency2.code_currency',
            'currency2.payment',
            'currency2.designation_xml',

            'direction_field',
            'direction_exchange_cities',
            'direction_allowed_countries',
            'direction_forbidden_countries',
        ]);

        // Canonical RUB/family + baseline gate at the final order mutation boundary.
        // Do not trust frontend/XML/prior quote caches.
        try {
            $surface = \App\Services\Rates\RateDirectionEligibility::make()
                ->evaluateDirection($this->directionId);
            if (empty($surface['order_allowed'])) {
                return [[
                    'field' => 'direction',
                    'message' => __('Направление временно недоступно'),
                    'code' => \App\Services\Rates\RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE,
                    'modal' => false,
                    'meta' => [],
                ]];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('rate_public_surface_order_gate_failed', [
                'direction_id' => $this->directionId->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return [[
                'field' => 'direction',
                'message' => __('Направление временно недоступно'),
                'code' => \App\Services\Rates\RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE,
                'modal' => false,
                'meta' => [],
            ]];
        }

        $context = $this->buildValidationContext();
        $pipeline = $this->buildValidationPipeline();

        /** @var ValidationEngine $engine */
        $engine = app(ValidationEngine::class);

        // selector можно использовать в тестах/отладке, например исключить правило по id
        $selector = null;

        $validation = $engine->run($context, $pipeline, $selector);

        if ($validation->hasErrors()) {
            return $validation->toLegacyErrorsArray();
        }

        // сохраняем эффекты для применения после addToDatabase()
        $this->validationEffects = $validation->effects();

        return [];
    }

    /**
     * Применить эффекты валидации к созданной заявке.
     *
     * Важно: вызывать строго ПОСЛЕ addToDatabase().
     */
    protected function applyValidationEffects(Task $order): void
    {
        if ($this->validationEffects === []) {
            return;
        }

        /** @var EffectsApplier $applier */
        $applier = app(EffectsApplier::class);
        $applier->apply($order, $this->validationEffects);
    }

    /**
     * Собрать ValidationContext (DataBag + Environment + ResourceBag).
     *
     * @return ValidationContext
     */
    protected function buildValidationContext(): ValidationContext
    {
        $resources = new ResourceBag();

        // DirectionExchange обязателен для всех правил
        $resources->set($this->directionId);

        // Пользователь — опционально (если уже известен)
        if ($this->authInfo instanceof User) {
            $resources->set($this->authInfo);
        }

        // Email: берем из auth, иначе из входных опций
        $email = null;
        if (auth()->check()) {
            $email = (string) auth()->user()->email;
        } elseif (!empty($this->options['email'])) {
            $email = (string) $this->options['email'];
        }

        $env = new Environment(
            ip: (string) request()->ip(),
            email: $email,
            locale: app()->getLocale(),
            userAgent: request()->userAgent(),
        );

        // DataBag — входные параметры формы/запроса
        $data = new DataBag([
            ...$this->options,
            'authId' => (int) $this->authId,
        ]);

        // options пустые — так как scoped параметры задаются в ValidationStep
        return new ValidationContext($data, $env, $resources);
    }

    /**
     * Построить пайплайн правил через ValidationRegistry.
     *
     * @return ValidationPipeline
     */
    protected function buildValidationPipeline(): ValidationPipeline
    {
        /** @var ValidationRegistry $registry */
        $registry = app(ValidationRegistry::class);

        return $registry->build($this->directionId);
    }
}
