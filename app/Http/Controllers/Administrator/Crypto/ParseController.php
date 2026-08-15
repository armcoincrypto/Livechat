<?php

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ParserRates\ParserResources;
use App\Models\GroupParserExchange;
use App\Models\ParserExchange;
use iEXPackages\Proxy\Models\Proxy;
use App\Models\RatesHistoryLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParseController extends Controller
{

    public function index(Request $request)
    {
        $groupParsers = GroupParserExchange::withCount('parserExchange')
            ->orderBy('status', 'desc')
            ->orderBy('sorting')
            ->get();

        return response()->json([
            'data' => $groupParsers->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'attributes' => [
                        'name' => (string) $item->name,
                        'sorting' => (int) $item->sorting,
                        'alias' => (string) $item->alias,
                        'status' => (int) $item->status,

                        'rates_count' => iex_number_format((int) $item->parser_exchange_count, 0, true),

                        'last_updated_at' => $item->last_updated_at !== null
                            ? Carbon::parse($item->last_updated_at)->format('c')
                            : '',

                        // Новые поля статистики по последнему обновлению группы
                        'last_duration_ms' => (int) ($item->last_duration_ms ?? 0),
                        'last_total' => iex_number_format((int) ($item->last_total ?? 0), 0, true),
                        'last_updated' => iex_number_format((int) ($item->last_updated ?? 0), 0, true),
                        'last_errors' => iex_number_format((int) ($item->last_errors ?? 0), 0, true),

                        // accessor из модели: last_success_percent (0..100)
                        'last_success_percent' => iex_number_format((int) ($item->last_success_percent ?? 0), 0, true),

                        'proxy_id' => (int) ($item->proxy_id ?? 0),
                        'image' => url('/images/parsers/' . \Str::lower((string) $item->alias) . '.png'),
                    ],
                ];
            }),
        ]);
    }

    /**
     * Показать все курсы по источнику
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id)
    {
        $group_parser = GroupParserExchange::find($id);

        $parser_exchange = ParserExchange::filter($request->all())
            ->with([
                'direction_exchange' => function ($q) {
                    $q->select('id', 'tech_name', 'status', 'id_crypto_parser');
                },
                'ratesHistoryLogs' => function ($q) {
                    $q->where('created_at', '>=', \Carbon\Carbon::now()->subDay())
                      ->orderBy('created_at');
                },
            ])
            ->where('id_group', $group_parser->id)
            ->orderByRaw('status DESC')
            ->paginate((int) iEXSetting('admin_crypto_parser_pagination', 20));


        if($request->has('isLoadingData'))
        {
            $admin_hidden_columns = explode(',', iEXSetting('admin_crypto_parser_hidden_columns'));
            $allowedColumns = ['name', 'course', 'last_updated', 'status'];


            // Загружаем данные Proxy (для выбора в UI). Важно: пароль не отдаём.
            $proxies = Proxy::query()
                ->orderByDesc('id')
                ->get(['id', 'host', 'port', 'type', 'status', 'fail_count', 'last_checked_at'])
                ->map(static function (Proxy $item): array {
                    $host = trim((string) ($item->host ?? ''));
                    $port = (int) ($item->port ?? 0);
                    $type = strtoupper((string) ($item->type ?? 'HTTP'));
                    $isActive = (bool) ($item->status ?? false);

                    return [
                        'id' => (int) $item->id,
                        'status' => $isActive ? 1 : 0,
                        'host' => $host,
                        'port' => $port,
                        'type' => $type,
                        'fail_count' => (int) ($item->fail_count ?? 0),
                        'last_checked_at' => $item->last_checked_at?->format('Y-m-d H:i:s'),
                    ];
                })
                ->values();


            return response()->json([
                'is_action' => file_exists( storage_path('templates/rates/'. $group_parser->alias.'.json')),
                'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                    return $item;
                })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
                'per_page' => (int)iEXSetting('admin_crypto_parser_pagination', 20),
                'proxies' => $proxies,
                'selectedProxy' => $group_parser->proxy_id,
            ]);
        }



        return response()->json(new ParserResources($parser_exchange));
    }

    /**
     * Обработка и добавление новой пары
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name_from' => 'required',
            'name_to' => 'required',
            'id_group' => 'required',
            'number_format' => 'required',
        ]);

        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $filter_name = sprintf('%s - %s', $request->get('name_from'), $request->get('name_to'));

        $main_parser = ParserExchange::create([
            'name' => $filter_name,
            'code_in' => $request->get('name_from'),
            'code_out' => $request->get('name_to'),
            'id_group' => $request->get('id_group'),
            'number_format' => $request->get('number_format'),
            'type' => $request->has('type') ? $request->get('type') : 0,
            'status' => $request->get('status') ? $request->get('status') : 0,
            'value' => 1,
        ]);

        $code_view = sprintf(
            '[%s_%s%s]',
            Str::lower($main_parser->group_parse_exchange->alias),
            Str::lower($main_parser->code_in . '-' . $main_parser->code_out),
            !empty($main_parser->type_price) ? '_' . Str::lower($main_parser->type_price) : ''
        );

        $main_parser->update([
            'code' => $code_view,
        ]);

        if ($request->has('is_allow_to_backspace') and $request->get('is_allow_to_backspace') == 1) {
            $filter_name = sprintf('%s - %s', $request->get('name_to'), $request->get('name_from'));
            $type_parser = ($main_parser->type == 0 ? 1 : 0);
            $parser_backspace = ParserExchange::create([
                'name' => $filter_name,
                'code_in' => $request->get('name_to'),
                'code_out' => $request->get('name_from'),
                'id_group' => $main_parser->id_group,
                'number_format' => $main_parser->number_format,
                'type' => $type_parser,
                'status' => $main_parser->status,
                'value' => 1,
            ]);

            $code_view2 = sprintf(
                '[%s_%s%s]',
                Str::lower($parser_backspace->group_parse_exchange->alias),
                Str::lower($parser_backspace->code_in.'-'.$parser_backspace->code_out),
                empty($parser_backspace->type_price) ? '' : '_'.Str::lower($parser_backspace->type_price)
            );


            $parser_backspace->update([
                'code' => $code_view2,
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => sprintf('Пара %s успешно добавлена', $main_parser->name)
        ]);
    }

    /**
     * Форма, редактирования пары
     */
    public function edit(int $id)
    {
        $item = ParserExchange::find($id);
        $group_parser = GroupParserExchange::with('parser_exchange')
            ->where('status', '=', 1)->orderBy('sorting')->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name
                ];
            });

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name_from' => $item->code_in,
                'name_to' => $item->code_out,
                'status' => (bool)$item->status,
                'number_format' => $item->number_format,
                'id_group' => $item->id_group,
                'type' => $item->type,
                'type_price' => $item->type_price
            ],

            'groups' => $group_parser
        ]);
    }

    /**
     * Обработка и обновление пары
     */
    public function update(int $id, Request $request): JsonResponse
    {
        if ($request->has('updateField')) {
            if ($request->get('updateField') == 'status') {
                ParserExchange::where('id_group', $id)->where('id', '=', $request->id_parser)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }

            if ($request->get('updateField') == 'proxy') {
                $group = GroupParserExchange::find($id);
                if (!$group) {
                    return response()->json(['status' => 1, 'message' => 'Источник не найден']);
                }
                $proxyId = $request->input('proxy_id');

                // null или 0 = без прокси
                if ($proxyId === null || (string) $proxyId === '0') {
                    $group->update(['proxy_id' => null]);
                    return response()->json(['status' => 0]);
                }

                $proxyId = (int) $proxyId;
                if ($proxyId <= 0 || !Proxy::query()->whereKey($proxyId)->exists()) {
                    return response()->json(['status' => 1, 'message' => 'Прокси не найден']);
                }

                $group->update([
                    'proxy_id' => $proxyId,
                ]);

                return response()->json(['status' => 0]);
            }
        }

        if ($request->has('action_update') and $request->get('action_update') == 1)
        {
            if ($request->get('action_type') == 'disabled') {
                $item = GroupParserExchange::find($id);
                $item->update(['status' => 0]);

                return response()->json([
                    'status' => 0,
                    'message' => sprintf('Источник %s отключен', $item->name)
                ]);
            }

            if ($request->get('action_type') == 'enabled') {
                $item = GroupParserExchange::find($id);
                $item->update(['status' => 1]);

                return response()->json([
                    'status' => 0,
                    'message' => sprintf('Источник %s включен', $item->name)
                ]);
            }


            // Загрузить курсы
            if ($request->get('action_type') == 'clear-courses')
            {
                $item = GroupParserExchange::find($id);

                foreach ($item->parserExchange as $parser) {
                    if ($parser->direction_exchange->count() > 0) {
                        continue;
                    }
                    $parser->delete();
                }

                $item->update([
                    'is_import_rates' => 0,
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => sprintf('Курсы из источника %s удалены', $item->name)
                ]);
            }

            if ($request->get('action_type') == 'loading-courses')
            {
                $item = GroupParserExchange::find($id);

                if (file_exists(storage_path('templates/rates/'.$item->alias.'.json'))) {
                    $get_file = json_decode(
                        file_get_contents(
                            storage_path('templates/rates/'.$item->alias.'.json')
                        ), true
                    );
                } else {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Файл для импорта курсов не найден'
                    ]);
                }

                try {
                    foreach ($get_file as $import) {
                        $get_name = explode(' - ', $import['name']);

                        $parser_update = ParserExchange::updateOrCreate([
                            'name' => $import['name'],
                            'id_group' => $item->id,
                            'type' => $import['type'],
                            'type_price' => $import['type_amount'] ?? null,
                        ], [
                            'name' => $import['name'],
                            'id_group' => $item->id,
                            'type' => $import['type'],
                            'code_in' => trim($get_name[0]),
                            'code_out' => trim($get_name[1]),
                            'value' => 1,
                            'value_default' => 1,
                            'number_format' => 10,
                            'summa_default' => $import['amount'] ?? 0,
                            'summa' => $import['amount'] ?? 0,
                            'type_price' => $import['type_amount'] ?? null,
                        ]);

                        if (isset($parser_update->group_parse_exchange)) {
                            $code_view = sprintf(
                                '[%s_%s%s]',
                                Str::lower($parser_update->group_parse_exchange->alias),
                                Str::lower($parser_update->code_in.'-'.$parser_update->code_out),
                                empty($parser_update->type_price) ? '' : '_'.Str::lower($parser_update->type_price)
                            );

                            $parser_update->update([
                                'code' => $code_view,
                            ]);
                        }
                    }
                }catch (\Exception $exception) {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Ошибка загрузки данных'
                    ]);
                }

                $item->update([
                    'is_import_rates' => 1,
                    'last_imported_at' => Carbon::now()->format('c'),
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => sprintf('Курсы из источника %s выгружены', $item->name)
                ]);
            }
        }


        $validator = Validator::make($request->all(), [
            'name_from' => 'required',
            'name_to' => 'required',
            'id_group' => 'required',
            'number_format' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = ParserExchange::find($id);
        $filter_name = sprintf('%s - %s', $request->get('name_from'), $request->get('name_to'));

        $item->update([
            'name' => $filter_name,
            'code_in' => $request->get('name_from'),
            'code_out' => $request->get('name_to'),
            'id_group' => $request->get('id_group'),
            'number_format' => $request->get('number_format'),
            'type' => $request->has('type') ? $request->get('type') : 0,
            'status' => $request->get('status') ? $request->get('status') : 0,
            'type_price' => $request->get('type_price') ? $request->get('type_price') : '',
            'value' => 1,
        ]);

        $code_view = '[' . \Str::lower($item->group_parse_exchange->alias) . '_' . \Str::lower($item->code_in . '-' . $item->code_out) . ']';
        if (!empty($item->type_price)) {
            $code_view = '[' . \Str::lower($item->group_parse_exchange->alias) . '_' . \Str::lower($item->code_in . '-' . $item->code_out) . '_' . \Str::lower($item->type_price) . ']';
        }

        $item->update([
            'code' => $code_view,
        ]);


        return response()->json([
            'status' => 0,
            'message' => sprintf('%s успешно обновлена', $item->name)
        ]);
    }

    /**
     * Массовое обновление статуса пар по источнику
     *
     * @param Request $request
     * @param int $id  ID группы (источника)
     * @return JsonResponse
     */
    public function bulkUpdateStatus(Request $request, int $id): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status'  => 1,
                'message' => 'В demo версии данная функция недоступна',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer', 'exists:parser_exchange,id'],
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $ids    = $request->input('ids', []);
        $status = (int)$request->input('status');

        ParserExchange::where('id_group', $id)
            ->whereIn('id', $ids)
            ->update([
                'status' => $status,
            ]);

        return response()->json([
            'status'  => 0,
            'message' => $status === 1
                ? 'Пары успешно включены'
                : 'Пары успешно отключены',
        ]);
    }

    /**
     * Удаление курсов
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $item = ParserExchange::find($id);
        if ($item->direction_exchange->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => "{$item->name} удалить невозможно, к ней привязаны направления"
            ]);
        }
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $item->name.' успешно удален'
        ]);
    }

    /**
     * История курса для графика.
     *
     * URL: GET /frontend-api/crypto/parser/{id}/chart?days=1
     *
     * Параметры:
     *  - days (int) — период в днях, по умолчанию 1, максимум 365.
     *
     * Ответ:
     *  status = 0 — всё ок
     *  data = {
     *      id: int,
     *      name: string,
     *      code: string,
     *      from: string (ISO8601),
     *      to: string (ISO8601),
     *      days: int,
     *      points_count: int,
     *      points: [
     *          { time: string (ISO8601), rate: float },
     *          ...
     *      ]
     *  }
     */
    public function chart(Request $request, int $id): JsonResponse
    {
        /** @var ParserExchange|null $parser */
        $parser = ParserExchange::find($id);

        if (!$parser) {
            return response()->json([
                'status'  => 1,
                'message' => 'Парсер не найден',
            ], 404);
        }

        // Валидируем период (в днях)
        $validator = Validator::make($request->all(), [
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $days = (int) $request->input('days', 1);

        $to   = Carbon::now();
        $from = (clone $to)->subDays($days);

        // История курса из логов
        $logs = RatesHistoryLog::query()
            ->where('source', 'source')            // тот же source, что и в RatesLoggerService
            ->where('id_source', $parser->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['new_value', 'created_at']);

        // Преобразуем к массиву точек для графика
        $points = $logs
            ->map(function (RatesHistoryLog $log) {
                $rate = $this->toNumericOrNull($log->new_value ?? null);

                if ($rate === null) {
                    return null;
                }

                return [
                    'time' => $log->created_at?->toIso8601String(),
                    // Отдаём курс как строку, чтобы избежать научной нотации в JSON
                    'rate' => $rate,
                ];
            })
            ->filter() // убираем null
            ->values();

        return response()->json([
            'status' => 0,
            'data'   => [
                'id'           => $parser->id,
                'name'         => $parser->name,
                'code'         => $parser->code,
                'from'         => $from->toIso8601String(),
                'to'           => $to->toIso8601String(),
                'days'         => $days,
                'points_count' => $points->count(),
                'points'       => $points,
            ],
        ]);
    }

    /**
     * Приводит значение к числовой строке или возвращает null, если это не число.
     */
    protected function toNumericOrNull(?string $value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }
}
