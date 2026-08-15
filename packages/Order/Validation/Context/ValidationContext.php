<?php
declare(strict_types=1);

namespace iEXPackages\Order\Validation\Context;

use iEXPackages\Order\Validation\Enums\CurrencyScope;

/**
 * ValidationContext — единый объект, который передаётся во все правила.
 */
final class ValidationContext
{
    public function __construct(
        public readonly DataBag $data,
        public readonly Environment $env,
        public readonly ResourceBag $resources,
        public readonly array $options = []
    ) {}

    /**
     * Опции только для текущего шага (НЕ копятся).
     */
    public function withOptions(array $options): self
    {
        return new self($this->data, $this->env, $this->resources, $options);
    }

    /**
     * Если где-то реально нужен merge — оставим как отдельный метод.
     */
    public function withMergedOptions(array $options): self
    {
        return new self(
            $this->data,
            $this->env,
            $this->resources,
            array_merge($this->options, $options)
        );
    }

    /**
     * Получить опцию с дефолтом.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function currencyScope(CurrencyScope $default = CurrencyScope::IN): CurrencyScope
    {
        return CurrencyScope::resolve($this->option('scope'), $default);
    }
}
