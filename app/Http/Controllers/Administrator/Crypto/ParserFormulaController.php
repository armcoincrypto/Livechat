<?php

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ParserRates\FormulaParserResources;
use App\Jobs\ParserFormulaUpdateJob;
use App\Models\ParserFormulaRates;
use App\Models\RatesHistoryLog;
use Carbon\Carbon;
use iEXPackages\Courses\Rates\Compilers\CompilerFormulaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParserFormulaController extends Controller
{
    /**
     * Ключи для настройки автовыплат
     *
     * @var array
     */
    protected array $settingOptions = [
        'settings' => [
            'record_course_formula_history'
        ]
    ];

    /**
     * Список всех формул
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $parsers = ParserFormulaRates::with([
            'direction_exchange' => function ($q) {
                $q->select('id', 'tech_name', 'status', 'id_parser_formula_rate');
            },
            'ratesHistoryLogs' => function ($q) {
                $q->where('created_at', '>=', Carbon::now()->subDay())
                    ->orderBy('created_at');
            },
        ])->filter($request->all());

        if(!$request->has('sorting_order')) {
            $parsers = $parsers->orderBy('id', 'desc');
        }

        $parsers = $parsers->paginate(iEXSetting('admin_parser_formula_paginate', 20));


        return response()->json([
            'items' => new FormulaParserResources($parsers),
            'per_page' => (int)iEXSetting('admin_parser_formula_paginate', 20),
        ]);
    }

    /**
     * Обработка и добавление формулы
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(Request $request)
    {
        // Быстрая проверка спец. операций обновления, чтобы не смешивать с добавлением
        if ($request->is_update == 1 && $request->has('showPage')) {
            $textMessage = '';
            $filterInputs = collect($request->all())->filter();

            if (
                $filterInputs->has('parser_formula_code_from')
                && $filterInputs->has('parser_formula_code_to')
            ) {
                $delay = now()->addSeconds(3);

                cache()->set('parser_formula_code_from', $request->parser_formula_code_from);
                cache()->set('parser_formula_code_to', $request->parser_formula_code_to);

                dispatch(new ParserFormulaUpdateJob())->delay($delay);

                $textMessage = __('Задача поставлена в очередь, обновите страницу через несколько секунд...');
            } else {
                $textMessage = __('Настройки успешно сохранены');
            }

            return response()->json([
                'status' => 0,
                'message' => $textMessage ?? '',
            ]);
        }


        // Обновление статуса через быстрый update
        if ($request->has('updateField') && $request->get('updateField') === 'status') {
            $model = ParserFormulaRates::find((int)$request->id);
            if ($model) {
                $model->update([
                    'status' => (int)$request->status,
                ]);
            }
            return response()->json(['status' => 0]);
        }

        // Основная валидация входных данных
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'title' => ['required', 'string', 'max:255'],
            'number_format' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Приведение title к верхнему регистру с поддержкой всех языков
        $title = $request->has('title')
            ? Str::upper(trim($request->get('title')))
            : null;

        $options = [
            'title' => $title,
            'name' => $request->has('name') ? trim($request->get('name')) : null,
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'number_format' => $request->has('number_format') ? (int)$request->get('number_format') : 0
        ];


        try {
            $rate = ParserFormulaRates::create($options);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 2,
                'message' => 'Ошибка при сохранении: ' . $e->getMessage(),
            ], 500);
        }


        return response()->json([
            'status' => 0,
            'message' => "{$rate->name} успешно добавлена",
            'id' => $rate->id,
        ]);
    }

    /**
     * Форма редактирования курсов конкурентов
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $item = ParserFormulaRates::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'title' => $item->title,
                'name' => html_entity_decode($item->name),
                'status' => (bool)$item->status,
                'number_format' => (int)$item->number_format
            ],
        ]);
    }

    /**
     * Обработка и обновление курсов конкурентов
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $rates = ParserFormulaRates::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
        ]);

        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        // Приведение title к верхнему регистру с поддержкой всех языков
        $title = $request->has('title')
            ? Str::upper(trim($request->get('title')))
            : null;

        $options = [
            'title' => $title,
            'name' => $request->has('name') ? trim($request->get('name')) : null,
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'number_format' => $request->has('number_format') ? (int)$request->get('number_format') : 0
        ];

        $rates->update($options);

        return response()->json([
            'status' => 0,
            'message' => "{$rates->name} успешно обновлен"
        ]);
    }

    public function query(Request $request)
    {
        // Создание экземпляра через контейнер Laravel
        $compilerService = app(CompilerFormulaService::class);

        // Вызов метода для получения результата формулы
        $response = $compilerService->getFormulaResult($request->formulaValue);


        return response()->json($response);
    }


    /**
     * Удаление курсов
     *
     * @param int $id
     * @return JsonResponse
     *
     */
    public function destroy(int $id): JsonResponse
    {
        $rate = ParserFormulaRates::findOrFail($id);
        $oldItem = $rate;
        if ($rate->direction_exchange->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => "'.$oldItem->title.' удалить невозможно, К ней привязаны направления"
            ]);

        }
        $rate->delete();
        return response()->json([
            'status' => 0,
            'message' => "'.$oldItem->title.' успешно удален"
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
        /** @var ParserFormulaRates|null $parser */
        $parser = ParserFormulaRates::find($id);

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
            ->where('source', 'formula')            // тот же source, что и в RatesLoggerService
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

    public function tagsCatalog()
    {
        $compilerService = app(CompilerFormulaService::class);

        return response()->json([
            'success' => true,
            'catalog' => $compilerService->getTagsCatalog(true),
        ]);
    }
}
