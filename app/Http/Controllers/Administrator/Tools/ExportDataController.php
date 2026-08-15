<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Models\ExportData;
use App\Models\TaskStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportDataController extends Controller
{
    /**
     * Дополнительные фильтры
     *
     * @var array
     */
    protected $allowFiltered = [
        'exportdata_driver',
        'exportdata_is_cron',
    ];

    protected $list = [
        'XLSX',
        'CSV',
        'TSV',
        'ODS',
        'XLS',
        'HTML',
        'MPDF',
        'DOMPDF',
        'TCPDF',
    ];

    /**
     * Модуль экпорта данных
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $typeExport = $request->get('typeExport');

        switch ($typeExport) {
            case 'orders':
                $orderStatuses = TaskStatus::pluck('name', 'id')->toArray();

                return response()->json([
                    'statuses' => $orderStatuses,
                ]);

            default:
                return response()->json([
                    'error' => 'Unsupported export type.',
                    'supported_types' => ['orders'],
                ], 400);
        }
    }

    /**
     * Обработка данных
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function store(Request $request)
    {
        if (config('iexexchanger.is_reading_mode'))
        {
            return response()->json([
                'status' => 1,
                'message' => 'Данная функция недоступна в демо версии'
            ]);
        }

        // Сохранение данные
        foreach ($request->export as $key => $value) {
            // Если нет значений, пропускаем
            if (empty($value)) {
                continue;
            }

            $item = ExportData::find($key);

            $class = '\\App\\Exports\\'.$item->export_value;
            $class_collection = $class.'Collection';
            $first = Carbon::now()->format('Y_m_d').'_'.random_int(0, 999999);
            $ext = $first.'_'.Str::studly($item->export_value).'.'.mb_strtolower($value);

            // Если существует класс экспорта
            if (class_exists($class) and class_exists($class_collection)) {
                if ($request->get('actions') == 'save') {
                    if ($item->is_filter == 1) {
                        Excel::store(new $class(true), $ext, iEXSetting('exportdata_driver', 'local'), excel_type($value));
                    } else {
                        Excel::store(new $class_collection(), $ext, iEXSetting('exportdata_driver', 'local'), excel_type($value));
                    }
                } else {
                    if ($item->is_filter == 1) {
                        export_filter_data($class, $ext, $value);
                    } else {
                        export_data($class_collection, $ext, $value);
                    }
                }

                // Обновляем счетчик
                $item->update(['count' => $item->count + 1]);
            }
        }

        return redirect()->back();
    }

    /**
     * Редактирование данных
     */
    public function edit(int $id): Renderable
    {
        $find = ExportData::find($id);

        return view('admin.tools.export-data.edit', [
            'find' => $find,
            'list' => $this->list,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $find = ExportData::find($id);
        $find->update([
            'is_filter' => $request->is_filter,
            'is_cron' => $request->is_cron,
            'format_export' => $request->format_export,
        ]);

        return redirect()->back();
    }
}
