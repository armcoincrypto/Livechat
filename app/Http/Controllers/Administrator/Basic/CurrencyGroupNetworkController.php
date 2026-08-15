<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\CurrencyGroupNetwork;
use App\Models\Currency;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CurrencyGroupNetworkController extends Controller
{
    /**
     * Список фильтров
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Вспомогательная загрузка валют для списка выбора (create/edit)
        // GET /basic/currency-group-networks?is_loading_currencies=1
        if ($request->boolean('is_loading_currencies')) {
            $items = Currency::query()
                ->select(['id', 'small_code', 'tech_name', 'tech_currency_name', 'status', 'id_group_network'])
                ->orderBy('tech_name')
                ->get()
                ->map(function ($item) {
                    $parts = array_values(array_filter([
                        (string) $item->small_code,
                        (string) $item->tech_name,
                        (string) $item->tech_currency_name,
                    ]));

                    return [
                        'id' => (int) $item->id,
                        'value' => $parts !== [] ? implode(' — ', $parts) : ('#' . (int) $item->id),
                        'small_code' => (string) $item->small_code,
                        'status' => (int) $item->status,
                        'id_group_network' => $item->id_group_network !== null ? (int) $item->id_group_network : null,
                    ];
                })
                ->values()
                ->all();

            return response()->json(['items' => $items]);
        }

        $query = CurrencyGroupNetwork::query()
            ->orderBy('id', 'desc')
            ->withCount('currencies');

        // Optional include currencies list (lightweight)
        if ($request->boolean('with_currencies')) {
            $query->with(['currencies' => function ($q) {
                $q->select(['id', 'id_group_network', 'small_code', 'tech_name', 'tech_currency_name', 'status'])
                  ->orderBy('id', 'desc');
            }]);
        }

        $items = $query->get();

        return response()->json([
            'data' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'title_locale' => $item->getTranslations('title'),
                        'title' => $item->title,
                        'icon' => $item->icon,
                        'icon_path'  => '/storage/currencies-icon/' . $item->icon,
                        'currency_count' => $item->currencies_count,
                        'display_type' => (int) $item->display_type,
                        'currencies' => $item->relationLoaded('currencies')
                            ? $item->currencies->map(function ($c) {
                                return [
                                    'id' => $c->id,
                                    'small_code' => $c->small_code,
                                    'tech_name' => $c->tech_name,
                                    'tech_currency_name' => $c->tech_currency_name,
                                    'status' => (int) $c->status,
                                ];
                            })->values()
                            : null,
                        'created_at' => $item->created_at->format('c'),
                        'updated_at' => $item->updated_at->format('c')
                    ]
                ];
            }),
            'total' => count($items)
        ]);
    }


    /**
     * Детальная информация по сети + список валют для привязки.
     *
     * Возвращает:
     * - network: сама сеть
     * - currencies_attached: валюты, уже привязанные к этой сети
     * - currencies_available: валюты, которые можно выбрать (не привязаны ни к одной сети)
     */
    public function show(int $id): JsonResponse
    {
        $network = CurrencyGroupNetwork::query()
            ->with(['currencies' => function ($q) {
                $q->select(['id', 'id_group_network', 'small_code', 'tech_name', 'tech_currency_name', 'status'])
                  ->orderBy('id', 'desc');
            }])
            ->withCount('currencies')
            ->findOrFail($id);

        $attached = $network->currencies;

        // Доступные валюты: не привязаны ни к одной сети (id_group_network is NULL or 0)
        $available = Currency::query()
            ->where(function ($q) {
                $q->whereNull('id_group_network')->orWhere('id_group_network', '=', 0);
            })
            ->select(['id', 'id_group_network', 'small_code', 'tech_name', 'tech_currency_name', 'status'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 0,
            'data' => [
                'id' => $network->id,
                'attributes' => [
                    'title_locale' => $network->getTranslations('title'),
                    'title' => $network->title,
                    'icon' => $network->icon,
                    'icon_path' => '/storage/currencies-icon/' . $network->icon,
                    'currency_count' => $network->currencies_count,
                    'display_type' => (int) $network->display_type,
                    'created_at' => $network->created_at->format('c'),
                    'updated_at' => $network->updated_at->format('c'),
                ],
            ],
            'currencies_attached' => $attached->map(function ($c) {
                return [
                    'id' => $c->id,
                    'small_code' => $c->small_code,
                    'tech_name' => $c->tech_name,
                    'tech_currency_name' => $c->tech_currency_name,
                    'status' => (int) $c->status,
                ];
            })->values(),
            'currencies_available' => $available->map(function ($c) {
                return [
                    'id' => $c->id,
                    'small_code' => $c->small_code,
                    'tech_name' => $c->tech_name,
                    'tech_currency_name' => $c->tech_currency_name,
                    'status' => (int) $c->status,
                ];
            })->values(),
        ]);
    }


    /**
     * Синхронизация валют, привязанных к сети.
     *
     * Ожидает:
     * - currency_ids: int[]
     * - force: 0/1 (если 1 — переносит валюты из других сетей в эту)
     */
    public function syncCurrencies(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency_ids' => 'nullable|array',
            'currency_ids.*' => 'integer|min:1',
            'force' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $network = CurrencyGroupNetwork::findOrFail($id);

        $ids = (array) $request->input('currency_ids', []);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        $force = (int) $request->input('force', 0) === 1;

        // Валюты, которые уже привязаны к другим сетям
        if ($ids !== []) {
            $conflicts = Currency::query()
                ->whereIn('id', $ids)
                ->where('id_group_network', '>', 0)
                ->where('id_group_network', '!=', $network->id)
                ->select(['id', 'id_group_network', 'small_code', 'tech_name', 'tech_currency_name'])
                ->get();

            if ($conflicts->isNotEmpty() && ! $force) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Некоторые валюты уже привязаны к другим сетям. Включите force=1, чтобы перенести.',
                    'conflicts' => $conflicts->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'id_group_network' => (int) $c->id_group_network,
                            'small_code' => $c->small_code,
                            'tech_name' => $c->tech_name,
                            'tech_currency_name' => $c->tech_currency_name,
                        ];
                    })->values(),
                ]);
            }
        }

        DB::transaction(function () use ($network, $ids) {
            // 1) Снимаем привязку у валют, которые были в этой сети, но теперь отсутствуют в списке
            Currency::query()
                ->where('id_group_network', '=', $network->id)
                ->when($ids !== [], function ($q) use ($ids) {
                    $q->whereNotIn('id', $ids);
                }, function ($q) {
                    // если список пустой — отвязываем все
                })
                ->update(['id_group_network' => 0]);

            // 2) Привязываем выбранные валюты (и переносим, если были в другой сети)
            if ($ids !== []) {
                Currency::query()
                    ->whereIn('id', $ids)
                    ->update(['id_group_network' => $network->id]);
            }
        });

        $count = Currency::query()->where('id_group_network', '=', $network->id)->count();

        return response()->json([
            'status' => 0,
            'message' => 'Привязка валют к сети обновлена',
            'currency_count' => (int) $count,
        ]);
    }

    /**
     * Обработка и добавление фильтра
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required|max:255',
        ]);

        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        if ($request->hasFile('icon')) {
            $logo_icon = $request->file('icon');
            $filename = sprintf('network-%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

            if (! \File::isDirectory(public_path('storage/currencies-icon/'))) {
                \File::makeDirectory(public_path('storage/currencies-icon/'), 0777, true, true);
            }

            $destinationPath = public_path('/storage/currencies-icon');
            $logo_icon->move($destinationPath, $filename);
        }

        $options = [
            'title' => $request->title,
            'icon' => $filename ?? '',
            'display_type' => (int) $request->input('display_type', 0),
        ];

        $item = CurrencyGroupNetwork::create($options);

        return response()->json([
            'status' => 0,
            'message' => $item->title . ' успешно добавлен',
            'data' => [
                'id' => (int) $item->id,
                'currency_count' => 0,
            ],
        ]);
    }

    /**
     * Форма изменения метки
     *
     * @return JsonResponse
     */
    public function edit(Request $request, int $id)
    {
        $item = CurrencyGroupNetwork::findOrFail($id);

        // Удаление фото
        if ($request->has('is_delete_image')) {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/currencies-icon/'.$item->icon));
            $item->icon = null;
            $item->save();

            return response()->json([
                'status' => 0,
                'message' => 'Иконка удалена'
            ]);
        }

        return response()->json();
    }



    /**
     * Обработка и обновление фильтров
     */
    public function update($id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required|max:255',
            'display_type' => 'nullable|in:0,1',
        ]);

        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $network = CurrencyGroupNetwork::find($id);
        $filename = $network->icon;

        if ($request->hasFile('icon')) {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/currencies-icon/'.$filename));

            $logo_icon = $request->file('icon');
            $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

            $destinationPath = public_path('/storage/currencies-icon');
            $logo_icon->move($destinationPath, $filename);
        }

        $options = [
            'title' => $request->title,
            'icon' => $filename,
            'display_type' => (int) $request->input('display_type', 0),
        ];

        $network->update($options);


        return response()->json([
            'status' => 0,
            'message' => $network->title .' успешно обновлен'
        ]);
    }

    /**
     * Удаление группы для сетей
     *
     * @throws \Exception
     */
    public function destroy(int $id): JsonResponse
    {
        $item = CurrencyGroupNetwork::query()->findOrFail($id);

        // На всякий — сохраняем название для ответа
        $title = (string) $item->title;

        \DB::transaction(function () use ($item) {
            // 1) Отвязываем все валюты от этой сети
            // У тебя по проекту уже используется 0 как "не привязано"
            Currency::query()
                ->where('id_group_network', '=', $item->id)
                ->update([
                    'id_group_network' => 0,
                    'small_code' => ''
                ]);

            // 2) Удаляем саму сеть
            $item->delete();
        });

        return response()->json([
            'status' => 0,
            'message' => $title . ' успешно удален',
        ]);
    }
}
