<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\DashboardUserWidgets;
use App\Support\Widgets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardWidgetController extends Controller
{
    /**
     * Получаем список всех виджетов
     *
     * @return JsonResponse
     */
    public function index()
    {
        $allWidgets = DashboardUserWidgets::where('id_user', auth()->id())->orderBy('sorting')->get()->map(function ($item) {
            return [
                'row' => $item->row,
                'col' => $item->col,
                'widgetID' => $item->alias,
            ];
        });

        return response()->json($allWidgets);
    }

    /**
     * Добавляем виджет новый
    */
    public function store(Request $request)
    {
        if($request->isUpdate)
        {
            // Удалить - если нет в массиве
            DashboardUserWidgets::whereNotIn('alias', collect($request->data)->map->widgetID)->delete();

            foreach ($request->data as $key => $value)
            {
                DashboardUserWidgets::where('alias', $value['widgetID'])->update([
                    'row' => $value['row'],
                    'col' => $value['col'],
                    'sorting' => $key
                ]);
            }

            return;
        }


        DashboardUserWidgets::create([
            'id_user' => auth()->id(),
            'sorting' => 0,
            'row' => $request->row,
            'col' => $request->col,
            'alias' => $request->widgetID,
        ]);
    }

    /**
     * Получаем детали по виджету
     *
     * @param string $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(string $id, Request $request)
    {
        $widgets = new Widgets();
        return response()->json(
            $widgets->getWidgetsById('get'. \Str::studly($id), $request)
        );
    }
}
