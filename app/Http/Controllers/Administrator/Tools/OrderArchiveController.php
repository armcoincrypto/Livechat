<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Exports\OrderUserExport;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @deprecated
*/
class OrderArchiveController extends Controller
{
    /**
     * Дополнительные фильтры
     *
     * @var array
     */
    protected $allowFiltered = [
        'orderarchive_enable_upload_history',
        'orderarchive_count_order',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return Renderable
     */
    public function index()
    {
        return view('admin.tools.order-archive.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Renderable
     */
    public function store(Request $request)
    {
        // Обновление конфига
        $array = [];
        foreach ($this->allowFiltered as $item) {
            if ($request->has($item) and is_array($request->get($item))) {
                $array[$item] = implode(',', $request->get($item));
            } else {
                $array[$item] = ($request->has($item) ? $request->get($item) : null);
            }
        }

        iEXSetting($array);

       // flash('Настройки успешно сохранены', ['alert alert-success']);

        return redirect()->back();
    }

    /**
     * Скачиваем заявки клиента
     *
     * @throws \Exception
     */
    public function download()
    {
        // Если отключена скачивание, перекидываем на главную
        if ((int) iEXSetting('orderarchive_enable_upload_history') == 0) {
            return redirect()->to('/');
        }

        $first = Carbon::now()->format('Y_m_d').'_'.random_int(0, 999999);

        return Excel::download(new OrderUserExport(auth()->id(), true), $first.'.xls', excel_type('XLS'))->send();
    }
}
