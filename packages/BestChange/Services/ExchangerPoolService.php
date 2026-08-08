<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use App\Settings\BestChangeConfig;

/**
 * ExchangerPoolService
 *
 * Управляет пулом предпочтительных/исключённых обменников.
 *
 * Режимы:
 * - off   : не влияет
 * - soft  : preferred помечаем, excluded исключаем
 * - strict: используем только preferred (как whitelist), excluded исключаем
 */
final class ExchangerPoolService
{
    public function __construct(private readonly BestChangeConfig $settings) {}

    /**
     * @return array{
     *   mode:string,
     *   preferred:array<string,true>,
     *   excluded:array<string,true>
     * }
     */
    public function resolve(): array
    {
        $mode = $this->settings->exchangerPoolMode();

        $preferred = [];
        foreach ($this->settings->exchangerPoolPreferred() as $id) {
            if ($id > 0) $preferred[(string)$id] = true;
        }

        $excluded = [];
        foreach ($this->settings->exchangerPoolExcluded() as $id) {
            if ($id > 0) $excluded[(string)$id] = true;
        }

        return [
            'mode' => $mode,
            'preferred' => $preferred,
            'excluded' => $excluded,
        ];
    }
}
