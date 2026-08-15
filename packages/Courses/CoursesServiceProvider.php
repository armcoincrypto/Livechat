<?php

namespace iEXPackages\Courses;

use iEXPackages\Courses\Rates\Compilers\CompilerCompetitorService;
use iEXPackages\Courses\Rates\Compilers\CompilerDefaultService;
use iEXPackages\Courses\Rates\Compilers\CompilerFileParserService;
use iEXPackages\Courses\Rates\Compilers\CompilerFormulaService;
use iEXPackages\Courses\Rates\Rates;
use iEXPackages\Courses\Services\DefaultParserService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class CoursesServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрируйте поставщика услуг.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('courses', function (Application $app)
        {
            return new Courses(
                $app->make('config')->get('courses'),
                $this->app['files']
            );
        });

        $this->app->singleton(Rates::class, function ($app) {
            return new Rates(
                $app->make(CompilerDefaultService::class),
                $app->make(CompilerFormulaService::class),
                $app->make(CompilerCompetitorService::class),
                $app->make(CompilerFileParserService::class),
            );
        });

        $this->app->singleton(DefaultParserService::class, function () {
            return new DefaultParserService([
                'Rapira' => \iEXPackages\Courses\Services\DefaultSources\RapiraSource::class,
                'RussianCentralBank' => \iEXPackages\Courses\Services\DefaultSources\RussianCentralBankSource::class,
                "Binance" => \iEXPackages\Courses\Services\DefaultSources\BinanceSource::class,
                'Blockchain' => \iEXPackages\Courses\Services\DefaultSources\BlockchainSource::class,
                'CoinMarketCap' => \iEXPackages\Courses\Services\DefaultSources\CoinMarketCapSource::class,
                'ByBit' => \iEXPackages\Courses\Services\DefaultSources\ByBitSource::class,
                'Heleket' => \iEXPackages\Courses\Services\DefaultSources\HeleketSource::class,
                'EuropeanCentralBank' => \iEXPackages\Courses\Services\DefaultSources\EuropeanCentralBankSource::class,
                'NationalBankOfRomania' => \iEXPackages\Courses\Services\DefaultSources\NationalBankOfRomaniaSource::class,
                'NationalBankOfKyrgyzstan' => \iEXPackages\Courses\Services\DefaultSources\NationalBankOfKyrgyzstanSource::class,
                'NationalBankOfKazakhstan' => \iEXPackages\Courses\Services\DefaultSources\NationalBankOfKazakhstanSource::class,
                'NationalBankOfMoldova' => \iEXPackages\Courses\Services\DefaultSources\NationalBankOfMoldovaSource::class,
                'Bitfinex' => \iEXPackages\Courses\Services\DefaultSources\BitfinexSource::class,
                'HitBtc' => \iEXPackages\Courses\Services\DefaultSources\HitBtcSource::class,
                'KuCoin' => \iEXPackages\Courses\Services\DefaultSources\KuCoinSource::class,
                'BitPay' => \iEXPackages\Courses\Services\DefaultSources\BitPaySource::class,
                'FloatRates' => \iEXPackages\Courses\Services\DefaultSources\FloatRatesSource::class,
                'BitMart' => \iEXPackages\Courses\Services\DefaultSources\BitMartSource::class,
                'UzbekistanCentralBank' => \iEXPackages\Courses\Services\DefaultSources\UzbekistanCentralBankSource::class,
                'NationalBankOfIsrael' => \iEXPackages\Courses\Services\DefaultSources\NationalBankOfIsraelSource::class,
                'WmExchanger' => \iEXPackages\Courses\Services\DefaultSources\WmExchangerSource::class,
                'GateIo' => \iEXPackages\Courses\Services\DefaultSources\GateIoSource::class,
                'Coinbase' => \iEXPackages\Courses\Services\DefaultSources\CoinbaseSource::class,
                'MexcExchange' => \iEXPackages\Courses\Services\DefaultSources\MEXCSource::class,
                'WhiteBit' => \iEXPackages\Courses\Services\DefaultSources\WhiteBitSource::class,
                'Moex' => \iEXPackages\Courses\Services\DefaultSources\MoexSource::class,
                'Exmo' => \iEXPackages\Courses\Services\DefaultSources\ExmoSource::class,
                'CryptoCash' => \iEXPackages\Courses\Services\DefaultSources\CryptoCashSource::class,
            ]);
        });

        $this->commands([
            Console\UpdateCoursesConsole::class,
            Console\CompilerGeneratePricesConsole::class,
            Console\PruneRatesHistoryLogsCommand::class,
        ]);
    }
}
