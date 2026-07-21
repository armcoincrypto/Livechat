<?php

use iEXPackages\Calculator\SourceChecks;
use iEXPackages\Calculator\Strategies\BestChangeStrategy;
use iEXPackages\Calculator\Strategies\CompetitorStrategy;
use iEXPackages\Calculator\Strategies\DefaultStrategy;
use iEXPackages\Calculator\Strategies\FileStrategy;
use iEXPackages\Calculator\Strategies\FormulaStrategy;
use iEXPackages\Calculator\Strategies\IndependentRubStrategy;
use iEXPackages\Calculator\Strategies\ManualStrategy;

return [

    'priority' => array_values(array_filter(array_map(
        static fn ($v) => trim((string) $v),
        explode(',', env('CALCULATOR_SOURCE_PRIORITY', 'bestchange,crypto,formula,manual,file,competitor'))
    ))),


    'sources' => [
        'independent_rub' => [
            'strategy' => IndependentRubStrategy::class,
            'check' => [SourceChecks::class, 'independentRub'],
            'name' => [SourceChecks::class, 'independentRubName'],
        ],
        'bestchange' => [
            'strategy' => BestChangeStrategy::class,
            'check' => [SourceChecks::class, 'bestchange'],
            'name' => 'Bestchange',
        ],
        'file' => [
            'strategy' => FileStrategy::class,
            'check' => [SourceChecks::class, 'file'],
            'name' => [SourceChecks::class, 'fileName'],
        ],
        'competitor' => [
            'strategy' => CompetitorStrategy::class,
            'check' => [SourceChecks::class, 'competitor'],
            'name' => [SourceChecks::class, 'competitorName'],
        ],
        'formula' => [
            'strategy' => FormulaStrategy::class,
            'check' => [SourceChecks::class, 'formula'],
            'name' => [SourceChecks::class, 'formulaName'],
        ],
        'manual' => [
            'strategy' => ManualStrategy::class,
            'check' => [SourceChecks::class, 'manual'],
            'name' => [SourceChecks::class, 'manualName'],
        ],
        'crypto' => [
            'strategy' => DefaultStrategy::class,
            'check' => [SourceChecks::class, 'crypto'],
            'name' => [SourceChecks::class, 'cryptoName'],
        ],
    ],
];
