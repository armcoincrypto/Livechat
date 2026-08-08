<?php

namespace iEXPackages\BestChange\Services;

use App\Models\BestChangeDirection;
use iEXPackages\BestChange\FormulaTags\BestChangeAdditionalTagHandler;
use iEXPackages\BestChange\FormulaTags\CorrectTagHandler;
use iEXPackages\BestChange\FormulaTags\CrossRateTagHandler;
use iEXPackages\BestChange\FormulaTags\ExchangerRatingTagHandler;
use iEXPackages\BestChange\FormulaTags\ExchangerTagHandler;
use iEXPackages\BestChange\FormulaTags\ExchangerTrustTagHandler;
use iEXPackages\BestChange\FormulaTags\ExternalFeeTagHandler;
use iEXPackages\BestChange\FormulaTags\ExternalMaxTagHandler;
use iEXPackages\BestChange\FormulaTags\ExternalMinTagHandler;
use iEXPackages\BestChange\FormulaTags\FallbackTagHandler;
use iEXPackages\BestChange\FormulaTags\LimitTagHandler;
use iEXPackages\BestChange\FormulaTags\LiquidityTagHandler;
use iEXPackages\BestChange\FormulaTags\PosTagHandler;
use iEXPackages\BestChange\FormulaTags\RankedRateTagHandler;
use iEXPackages\BestChange\FormulaTags\ReserveFilterTagHandler;
use iEXPackages\BestChange\FormulaTags\RoundTagHandler;
use iEXPackages\BestChange\FormulaTags\SkipExchangerTagHandler;
use iEXPackages\BestChange\FormulaTags\SpreadExternalTagHandler;
use iEXPackages\BestChange\FormulaTags\TimeTagHandler;
use iEXPackages\BestChange\FormulaTags\WeightedAvgTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\IfTagHandler;
use iEXPackages\Courses\FormulaTags\Handlers\SetVariableTagHandler;
use iEXPackages\Courses\Services\FormulaParserService;
use Illuminate\Support\Facades\Log;

class BestChangeFormulaService
{
    private string $typePosition;

    public function __construct(string $typePosition = 'rate')
    {
        $this->typePosition = $typePosition;
    }

    /**
     * Подготавливает переменные для формулы
     */
    private function prepareVariables(array $filtered, array $externalRates): array
    {
        $variables = [];

        foreach ($filtered as $index => $row) {
            // $index идёт с 0, а ты хочешь с 1
            $positionNumber = $index + 1;

            // Получаем значение по ключу $typePosition (например, rate)
            $value = $row[$this->typePosition] ?? null;

            if ($value !== null && is_scalar($value)) {
                // В формуле будет [pos:1], [pos:2], [pos:3] и т.д.
                $variables["[pos:{$positionNumber}]"] = (string) $value;
            }
        }

        foreach ($externalRates as $key => $rate) {
            $variables["{$key}"] = (string) $rate;
        }

        return $variables;
    }

    /**
     * Выполняет формулу и возвращает результат
     */
    public function evaluate(string $formula, array $filtered, array $externalRates, BestChangeDirection $item): ?string
    {
        $variables = $this->prepareVariables($filtered, $externalRates);

        // Добавляем переменные из $item под отдельным ключом item:
        foreach ($item->getAttributes() as $key => $value) {
            if (is_scalar($value)) {
                $variables["[item:{$key}]"] = (string) $value;
            }
        }

        try {
            $parser = new FormulaParserService($variables);
            $parser->allowSingleTags();
            // Регистрируем обработчик [pos:N]
            $parser->registerTagHandler('pos', new PosTagHandler($filtered, $this->typePosition));
            $parser->registerTagHandler('exchanger', new ExchangerTagHandler($filtered));
            $parser->registerTagHandler('set', new SetVariableTagHandler());
            $parser->registerTagHandler('skip_exchanger', new SkipExchangerTagHandler($filtered, $this->typePosition));
            $parser->registerTagHandler('correct', new CorrectTagHandler());
            $parser->registerTagHandler('exchanger_rating', new ExchangerRatingTagHandler($filtered));
            $parser->registerTagHandler('limit', new LimitTagHandler());
            $parser->registerTagHandler('min', new ExternalMinTagHandler());
            $parser->registerTagHandler('max', new ExternalMaxTagHandler());
            $parser->registerTagHandler('cross_rate', new CrossRateTagHandler());
            $parser->registerTagHandler('external_fee', new ExternalFeeTagHandler());
            $parser->registerTagHandler('exchanger_trust', new ExchangerTrustTagHandler($filtered));
            $parser->registerTagHandler('liquidity', new LiquidityTagHandler($filtered));
            $parser->registerTagHandler('time', new TimeTagHandler());
            $parser->registerTagHandler('reserve_filter', new ReserveFilterTagHandler($filtered, $this->typePosition));
            $parser->registerTagHandler('weighted_avg', new WeightedAvgTagHandler($filtered, $this->typePosition));
            $parser->registerTagHandler('fallback', new FallbackTagHandler());
            $parser->registerTagHandler('ranked_rate', new RankedRateTagHandler($filtered, $this->typePosition));
            $parser->registerTagHandler('round', new RoundTagHandler());
            $parser->registerTagHandler('spread_external', new SpreadExternalTagHandler());

            // Дополнительные теги
            $additionalHandler = new BestChangeAdditionalTagHandler($filtered, $this->typePosition);

            // Основные курсы
            $parser->registerTagHandler('rate', $additionalHandler);
            $parser->registerTagHandler('rankrate', $additionalHandler);

            // Теги для работы с резервами
            $parser->registerTagHandler('reserve', $additionalHandler);
            $parser->registerTagHandler('reserve_min', $additionalHandler);
            $parser->registerTagHandler('reserve_max', $additionalHandler);

            // Анализ диапазона курсов
            $parser->registerTagHandler('avg', $additionalHandler);
            $parser->registerTagHandler('diff', $additionalHandler);
            $parser->registerTagHandler('spread', $additionalHandler);
            $parser->registerTagHandler('median', $additionalHandler);

            // Мин. и Макс. курсы
            $parser->registerTagHandler('minrate', $additionalHandler);
            $parser->registerTagHandler('maxrate', $additionalHandler);

            // Лимиты обмена
            $parser->registerTagHandler('inmin', $additionalHandler);
            $parser->registerTagHandler('inmax', $additionalHandler);

            // Условный тег if (если еще не зарегистрирован)
            $parser->registerTagHandler('if', new IfTagHandler());

            $result = $parser->calculate($formula, 18);

            if (!is_numeric($result) || bccomp($result, '0', 18) <= 0) {
                Log::warning('BestChangeFormulaService: Некорректный результат формулы', [
                    'result' => $result,
                    'formula' => $formula,
                    'variables' => $variables,
                ]);
                return null;
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('Ошибка вычисления формулы BestChange: ' . $e->getMessage(), [
                'formula' => $formula,
                'variables' => $variables,
            ]);
            return null;
        }
    }
}
