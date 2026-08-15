<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Locales\LanguageCatalog;
use Illuminate\Contracts\Foundation\Application;

final class Language
{
    /** @var array<string, string> */
    private array $onlyCodes = [];

    /** @var array<int, array<string, mixed>> */
    private array $formOptions = [];

    public function __construct(private readonly Application $app) {}

    public function configure(): static
    {
        $catalog = $this->app->make(LanguageCatalog::class);

        $this->onlyCodes = $catalog->onlyCodes();
        $this->formOptions = $catalog->formOptions();

        return $this;
    }

    /** @return array<string, string> */
    public function getOnlyCodes(): array
    {
        return $this->onlyCodes;
    }

    /** @return array<int, array<string, mixed>> */
    public function getFormOptions(): array
    {
        return $this->formOptions;
    }
}
