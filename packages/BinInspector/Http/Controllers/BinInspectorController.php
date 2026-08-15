<?php

namespace iEXPackages\BinInspector\Http\Controllers;

use App\Models\Currency;
use App\Models\CurrencyBinBankRule;
use App\Settings\BinInspectorConfig;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Контроллер настроек модуля проверки BIN (BinInspector).
 *
 * Здесь настраивается:
 *  - какой драйвер использовать (binlist / bincodes / mrbin);
 *  - нужно ли сохранять данные в кэш;
 *  - API-ключ (если он нужен для выбранного сервиса);
 *  - список валют, для которых модуль вообще активен.
 *
 * Отдельно настройки разрешённых/запрещённых банков
 * для каждой валюты управляются через таблицу currency_bin_bank_rules.
 */
class BinInspectorController extends Controller
{
    /**
     * Список полей, которые разрешено сохранять в настройках.
     *
     * @var array<int, string>
     */
    protected array $allowFiltered = [
        'driver',
        'save_data',
        'api_key',
        'ids_currencies',
    ];

    /**
     * Получить настройки модуля и справочники для формы в админке.
     *
     * @param  BinInspectorConfig  $settings
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(BinInspectorConfig $settings)
    {
        $currencies = Currency::query()
            ->where('status', 0)
            ->select('id', 'tech_name')
            ->cursor();
        $selectedCurrencies = $settings->idsCurrenciesArray();

        return response()->json([
            'currencies'      => $currencies,
            'ids_currencies'  => $selectedCurrencies,

            // Доступные сервисы проверки BIN
            'drivers' => [
                [
                    'id'   => 'binlist',
                    'name' => 'binlist.net (без API ключа)',
                ],
                [
                    'id'   => 'bincodes',
                    'name' => 'bincodes.com (требуется API ключ)',
                ],
                [
                    'id'   => 'mrbin',
                    'name' => 'mrbin.io (требуется API ключ)',
                ],
            ],

            'save_data' => $settings->shouldSaveData(),
            'driver'    => $settings->driver(),
            'api_key'   => $settings->apiKey(),
        ]);
    }

    /**
     * Сохранить настройки модуля.
     *
     * Важно:
     *  - если валюту убрали из списка ids_currencies,
     *    все её правила по банкам (currency_bin_bank_rules) удаляются.
     *
     * @param  Request          $request
     * @param  BinInspectorConfig $settings
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, BinInspectorConfig $settings)
    {
        // Список валют до обновления
        $oldIds = $settings->idsCurrenciesArray();

        // Обновляем настройки
        $update = [];
        foreach ($this->allowFiltered as $item) {
            if ($request->has($item) && is_array($request->get($item))) {
                // Массивы (например, ids_currencies) сохраняем через запятую
                $update[$item] = implode(',', $request->get($item));
            } else {
                // Прочие поля сохраняем как есть или null
                $update[$item] = $request->has($item)
                    ? $request->get($item)
                    : null;
            }
        }

        $settings->update($update);

        // Список валют после обновления
        $newIds = $settings->idsCurrenciesArray();

        // Валюты, которые были убраны из настроек
        $removedIds = array_diff($oldIds, $newIds);

        // Для убранных валют удаляем все правила по банкам
        if (! empty($removedIds)) {
            CurrencyBinBankRule::whereIn('currency_id', $removedIds)->delete();
        }

        return response()->json([
            'status'  => 0,
            'message' => 'Настройки успешно сохранены',
        ]);
    }
}
