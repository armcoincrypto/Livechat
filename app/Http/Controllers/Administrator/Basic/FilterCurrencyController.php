<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FilterCurrency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FilterCurrencyController extends Controller
{
    /**
     * Список фильтров
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        // Подгрузка валют для формы (мультиселект)
        if ($request->boolean('is_loading_currencies')) {
            $currenciesForSelect = Currency::query()
                ->select('id', 'tech_name')
                ->orderBy('tech_name')
                ->get()
                ->map(fn (Currency $currency) => [
                    'id'    => $currency->id,
                    'value' => $currency->tech_name,
                ])
                ->values()
                ->all();

            return response()->json([
                'items' => $currenciesForSelect,
            ]);
        }

        // Основной список фильтров c привязанными валютами
        $filterCollection = FilterCurrency::with([
            'currencies' => fn ($q) => $q->select('currencies.id', 'tech_name'),
        ])->orderBy('sorting')->get();

        $filtersResponse = $filterCollection->map(function (FilterCurrency $filter) {
            return [
                'id' => $filter->id,
                'attributes' => [
                    'name'       => $filter->name,
                    'icon'       => $filter->icon,
                    'icon_path'  => $filter->icon ? '/storage/filters/'.$filter->icon : null,
                    'locales'    => $filter->getTranslations(),
                    'currencies' => $filter->currencies->map(fn (Currency $currency) => [
                        'id'   => $currency->id,
                        'name' => $currency->tech_name,
                    ]),
                ],
            ];
        });

        return response()->json([
            'data'  => $filtersResponse,
            'total' => $filterCollection->count(),
        ]);
    }

    /**
     * Обработка и добавление фильтра
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required',
            'currency_ids'   => 'nullable',
            'currency_ids.*' => 'integer|exists:currencies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Загрузка иконки (если есть)
        $iconFilename = null;
        if ($request->hasFile('icon')) {
            $iconFile = $request->file('icon');
            $iconFilename = sprintf('%s.%s', Str::random(10), $iconFile->getClientOriginalExtension());

            if (! \File::isDirectory(public_path('storage/filters/'))) {
                \File::makeDirectory(public_path('storage/filters/'), 0777, true, true);
            }

            $destinationPath = public_path('/storage/filters');
            $iconFile->move($destinationPath, $iconFilename);
        }

        $filterAttributes = [
            'icon' => $iconFilename ?? '',
            'name' => $request->input('name'),
        ];

        $createdFilter = null;

        DB::transaction(function () use (&$createdFilter, $filterAttributes, $request) {
            $createdFilter = FilterCurrency::create($filterAttributes);

            $currencyIds = $this->normalizeCurrencyIds($request->input('currency_ids'));

            // Если ничего не пришло — просто не трогаем связи
            if ($currencyIds !== []) {
                $createdFilter->currencies()->sync($currencyIds);
            }
        });

        return response()->json([
            'status'  => 0,
            'message' => 'Фильтр '.$createdFilter->name.' успешно добавлен',
        ]);
    }

    /**
     * Форма редактирования фильтра.
     * Флаг is_delete_image=1 удалит иконку.
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $filter = FilterCurrency::with(['currencies:id,tech_name'])->findOrFail($id);

        if ($request->boolean('is_delete_image')) {
            if ($filter->icon) {
                iex_file_delete(public_path('storage/filters/'.$filter->icon));
                $filter->icon = null;
                $filter->save();
            }

            return response()->json([
                'status'  => 0,
                'message' => 'Иконка удалена',
            ]);
        }

        return response()->json([
            'id' => $filter->id,
            'attributes' => [
                'name'       => $filter->name,
                'icon'       => $filter->icon,
                'icon_path'  => $filter->icon ? '/storage/filters/'.$filter->icon : null,
                'locales'    => $filter->getTranslations(),
                'currencies' => $filter->currencies->map(fn (Currency $currency) => [
                    'id'   => $currency->id,
                    'name' => $currency->tech_name,
                ]),
            ],
        ]);
    }

    /**
     * Обновление фильтра (иконка + sync валют).
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $filter = FilterCurrency::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required',
            'currency_ids'   => 'nullable',
            'currency_ids.*' => 'integer|exists:currencies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $iconFilename = $filter->icon;

        if ($request->hasFile('icon')) {
            if ($iconFilename) {
                iex_file_delete(public_path('storage/filters/'.$iconFilename));
            }

            $iconFile = $request->file('icon');
            $iconFilename = sprintf('%s.%s', Str::random(10), $iconFile->getClientOriginalExtension());

            if (! \File::isDirectory(public_path('storage/filters/'))) {
                \File::makeDirectory(public_path('storage/filters/'), 0777, true, true);
            }

            $destinationPath = public_path('/storage/filters');
            $iconFile->move($destinationPath, $iconFilename);
        }

        $updateAttributes = [
            'icon' => $iconFilename ?? '',
            'name' => $request->input('name'),
        ];

        DB::transaction(function () use ($filter, $updateAttributes, $request) {
            $filter->update($updateAttributes);

            // Если ключ currency_ids присутствует в запросе — считаем это явным намерением изменить привязки.
            if ($request->exists('currency_ids')) {
                $currencyIds = $this->normalizeCurrencyIds($request->input('currency_ids'));
                // Пустой массив = очистить все связи
                $filter->currencies()->sync($currencyIds);
            }
        });

        return response()->json([
            'status'  => 0,
            'message' => 'Фильтр '.$filter->name.' успешно обновлен',
        ]);
    }


    /**
     * Удаление фильтра вместе с отвязкой всех валют.
     */
    public function destroy(int $id): JsonResponse
    {
        $filter = FilterCurrency::with('currencies:id')->findOrFail($id);

        DB::transaction(function () use ($filter) {
            // Удаляем иконку, если хранится на диске
            if (!empty($filter->icon)) {
                iex_file_delete(public_path('storage/filters/' . $filter->icon));
            }

            // Отвязываем все валюты из pivot-таблицы currency_filter
            $filter->currencies()->detach();

            // Удаляем сам фильтр
            $filter->delete();
        });

        return response()->json([
            'status'  => 0,
            'message' => 'Фильтр успешно удалён, все привязки валют удалены',
        ]);
    }

    /**
     * Нормализация входа currency_ids в массив int.
     * Примеры:
     *  - null / ''           -> []
     *  - 5 / '5'             -> [5]
     *  - ['1','2','x']       -> [1,2]
     *  - ['']                -> []
     */
    private function normalizeCurrencyIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(static fn ($v) => (int) $v)
            ->filter(static fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }
}
