<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Exports\OrdersExport;
use App\Jobs\OrdersExportJob;
use App\Models\OrderExport;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrdersExportController
{
    /**
     * Получение списка доступных статусов заявок.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $orderStatuses = TaskStatus::pluck('name', 'id')->toArray();

        return response()->json([
            'statuses' => $orderStatuses,
        ]);
    }

    /**
     * Запуск экспорта заявок (формирование файла в очереди).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status'  => 1,
                'message' => 'Функция недоступна в демо-версии',
            ]);
        }

        // Валидация данных
        $validator = Validator::make($request->all(), [
            'created_from' => 'nullable|date',
            'created_to'   => 'nullable|date',
            'updated_from' => 'nullable|date',
            'updated_to'   => 'nullable|date',
            'statuses'     => 'nullable|array',
            'statuses.*'   => 'integer',
            'fields'       => 'required|array|min:1',
            'format'       => 'nullable|string|in:xlsx,csv,ods',
        ], [
            'fields.required' => 'Необходимо выбрать хотя бы одно поле для экспорта.',
            'fields.min'      => 'Необходимо выбрать хотя бы одно поле для экспорта.',
            'format.in'       => 'Неверный формат экспорта.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->errors()->first(),
            ]);
        }

        // Формат файла по умолчанию (xlsx)
        $allowedFormats = ['xlsx', 'csv', 'ods'];
        $format = in_array($request->input('format'), $allowedFormats, true)
            ? $request->input('format')
            : 'xlsx';

        // Собираем фильтры и настройки экспорта
        $filters = $request->only([
            'created_from',
            'created_to',
            'updated_from',
            'updated_to',
            'statuses',
            'fields',
        ]);

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        // Создаём запись экспорта в базе
        $export = OrderExport::create([
            'user_id' => $user?->id ?? 0,
            'format'  => $format,
            'status'  => 'pending',
            'filters' => $filters,
        ]);

        // Запускаем очередь на формирование файла экспорта
        OrdersExportJob::dispatch($export->id);

        return response()->json([
            'status'    => 0,
            'message'   => 'Экспорт запущен. Файл будет доступен после завершения обработки.',
            'export_id' => $export->id,
        ]);
    }

    /**
     * Проверка статуса экспорта и получение ссылки на файл (если готов).
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $export = OrderExport::query()
            ->where('id', $id)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$export) {
            return response()->json([
                'status'  => 1,
                'message' => 'Экспорт не найден.',
            ], 404);
        }

        $canDownload = $export->status === 'done'
            && $export->file_name
            && $export->finished_at
            && $export->finished_at->gt(now()->subDay()); // 24 часа не прошло

        return response()->json([
            'status' => 0,
            'export' => [
                'id'          => $export->id,
                'state'       => $export->status, // pending, processing, done, failed
                'rows_count'  => $export->rows_count,
                'error'       => $export->status === 'failed' ? $export->error_message : null,
                'created_at'  => $export->created_at,
                'started_at'  => $export->started_at,
                'finished_at' => $export->finished_at,
                'can_download'=> $canDownload,
                'file'        => $canDownload && $export->file_name
                    ? route('orders.export.download', ['file' => $export->file_name])
                    : null,
            ],
        ]);
    }

    /**
     * Последние 5 экспортов текущего пользователя (с учётом 24 часов).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $query = OrderExport::query()
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(5);

        $items = $query->get()->map(function (OrderExport $export) {
            $canDownload = $export->status === 'done'
                && $export->file_name
                && $export->finished_at
                && $export->finished_at->gt(now()->subDay());

            return [
                'id'          => $export->id,
                'state'       => $export->status,
                'format'      => $export->format,
                'rows_count'  => $export->rows_count,
                'created_at'  => $export->created_at,
                'finished_at' => $export->finished_at,
                'can_download'=> $canDownload,
                'file'        => $canDownload && $export->file_name
                    ? route('orders.export.download', ['file' => $export->file_name])
                    : null,
            ];
        });

        return response()->json([
            'status' => 0,
            'items'  => $items,
        ]);
    }

    /**
     * История экспортов (с пагинацией и фильтром по статусу).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $perPage = (int) $request->input('per_page', 20);
        $state   = $request->input('state'); // optional: pending|processing|done|failed

        $query = OrderExport::query()
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->when($state, fn ($q) => $q->where('status', $state))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (OrderExport $export) {
            $canDownload = $export->status === 'done'
                && $export->file_name
                && $export->finished_at
                && $export->finished_at->gt(now()->subDay());

            return [
                'id'          => $export->id,
                'state'       => $export->status,
                'format'      => $export->format,
                'rows_count'  => $export->rows_count,
                'created_at'  => $export->created_at,
                'finished_at' => $export->finished_at,
                'can_download'=> $canDownload,
                'file'        => $canDownload && $export->file_name
                    ? route('orders.export.download', ['file' => $export->file_name])
                    : null,
            ];
        });

        return response()->json([
            'status' => 0,
            'data'   => $items,
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Отдаёт сформированный файл экспорта заявок (с ограничением 24 часа).
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\Response|BinaryFileResponse
     */
    public function downloadExportFile(Request $request)
    {
        // Правила валидации запроса
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'regex:/^[\w,\s-]+\.(xlsx|csv|ods)$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $filename = $validator->validated()['file'];

        // Находим запись экспорта по имени файла
        $export = OrderExport::where('file_name', $filename)->first();

        if (!$export) {
            return response()->json([
                'success' => false,
                'message' => 'Файл не найден или срок действия истёк.',
            ], 404);
        }

        // Проверяем ограничение в 24 часа
        if (
            $export->status !== 'done' ||
            !$export->finished_at ||
            $export->finished_at->lte(now()->subDay())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Срок действия ссылки на файл истёк. Сформируйте новый экспорт.',
            ], 410); // 410 Gone — ресурс устарел
        }

        // По умолчанию ищем файл в корне диска local
        $disk = Storage::disk('local');
        $relativePath = $filename;

        // Если не найден — пробуем поддиректорию exports/
        if (!$disk->exists($relativePath)) {
            $altPath = 'exports/' . $filename;

            if ($disk->exists($altPath)) {
                $relativePath = $altPath;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Файл не найден.',
                ], 404);
            }
        }

        return response()->download(
            $disk->path($relativePath),
            $filename,
            [
                'Content-Type' => $disk->mimeType($relativePath),
            ]
        );
    }
}
