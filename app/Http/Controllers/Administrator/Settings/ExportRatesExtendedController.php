<?php

namespace App\Http\Controllers\Administrator\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Settings\ExportRatesFileResponse;
use App\Models\DirectionExchange;
use App\Models\ExportRatesFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExportRatesExtendedController extends Controller
{
    /**
     * Загружаем список всех файлов курсов
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('showPage'))
        {
            if($request->get('showPage') == 'settings')
            {
                return response()->json([
                    'grates_cron_timer' => (int)iEXSetting('grates_cron_timer', 0)
                ]);
            }
        }

        $exportRates = ExportRatesFile::orderByDesc('id')->get();

        return response()->json([
            'export_rates_extended' => (bool)iEXSetting('export_rates_extended'),
            'items' => new ExportRatesFileResponse($exportRates),
            'total' => count($exportRates)
        ]);
    }

    /**
     * Добавляем новый файл
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                ExportRatesFile::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }

            if($request->get('updateField') == 'settings')
            {
                iEXSetting([
                    'grates_cron_timer' => (int) $request->grates_cron_timer,
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Настройки сохранены'
                ]);
            }
        }

        if($request->has('isExtendedVersion'))
        {
            iEXSetting([
                'export_rates_extended' => (bool) $request->isExtendedVersion,
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Настройки сохранены'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'filename' => ['required', 'regex:/^[a-zA-Z_-]+$/u'],
        ]);

        $validator->setAttributeNames([
            'filename' => __('Название файла'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $item = ExportRatesFile::create([
            'filename' => $request->filename,
            'status' => $request->status,
            'type_file' => $request->type_file ?? 0,
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->filename . ' успешно добавлен'
        ]);
    }

    /**
     * Данные для редактирования файла курсов
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $item = ExportRatesFile::find($id);

        if($request->has('is_loading_direction'))
        {
            $directionExchange = DirectionExchange::select('id', 'tech_name', 'status')
                ->lazy()->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'value' => $item->tech_name
                    ];
                });

            return response()->json([
                'items' => $directionExchange
            ]);
        }

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'filename' => $item->filename,
                'type_file' => $item->type_file,
                'status' => (bool)$item->status,
                'type_number_format' => $item->type_number_format,
                'number_format' => $item->number_format,
                'in_type_fromfee' => $item->in_type_fromfee,
                'in_type_tofee' => $item->in_type_tofee,
                'is_offline_operator' => $item->is_offline_operator,
                'is_view' => (string)$item->is_view,
                'ids_excluded_directions' => $item->ids_excluded_directions,
                'locales' => $item->getTranslations(),
                'description' => $item->description,
            ]
        ]);
    }

    /**
     * Обновляем настройки файла курсов
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => ['required', 'regex:/^[a-zA-Z_-]+$/u'],
        ]);

        $validator->setAttributeNames([
            'filename' => __('Название файла'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = ExportRatesFile::find($id);
        $item->update([
            'filename' => $request->filename,
            'type_file' => (int)$request->type_file ?? 0,
            'status' => (bool)$request->status,
            'type_number_format' => (int)$request->type_number_format ?? 0,
            'number_format' => (int)$request->number_format ?? 0,
            'in_type_fromfee' => (int)$request->in_type_fromfee ?? 0,
            'in_type_tofee' => (int)$request->in_type_tofee ?? 0,
            'is_offline_operator' => (int)$request->is_offline_operator ?? 0,
            'is_view' => (int)$request->is_view  ?? 0,
            'ids_excluded_directions' => $request->ids_excluded_directions ?? [],
            'description' => $request->description ?? null,
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->filename . ' успешно обновлен'
        ]);
    }

    /**
     * Удаляем настройки файла курсов
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $item = ExportRatesFile::findOrFail($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->filename . ' успешно удален'
        ]);
    }
}
