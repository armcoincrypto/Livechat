<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\RequisitesArchiveResources;
use App\Http\Resources\Admin\Basic\RequisitesResources;
use App\Models\Currency;
use App\Models\RequisiteLogs;
use App\Models\Requisites;
use App\Models\RequisitesGroup;
use App\Models\User;
use App\Support\Facades\iEXApp;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RequisitesController extends Controller
{
    /**
     * Фильтры
     *
     * @var array
    */
    protected array $allowFilteredPage = [
        'admin_requisites_pagination',
        'admin_requisites_hidden_columns',
    ];

    /**
     * Список реквизитов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {

        if($request->has('loadingFilters'))
        {
            $groups = RequisitesGroup::orderBy('sorting')->get()->pluck('name', 'id')->map(function ($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value
                ];
            })->values();

            $currencies = Currency::active()->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->tech_name . ' (' . __(($item->status == 0 ? 'активен' : 'не активен')) . ')'
                ];
            })->values();


            return response()->json([
                'groups' => $groups,
                'currencies' => $currencies
            ]);
        }

        $requisites = Requisites::isNotHistory()->filter($request->all())->withCount([
            'tasks_many as total_orders' => function ($query) {
                $query->select(DB::raw('COUNT(id) as total_id'));
            },
        ])
            ->withSum(['tasks_many as total_summa_day' => function ($query) {
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfDay(),
                    Carbon::now()->endOfDay(),
                ])->where('status', '=', 4);
            }],
                'give_price')
            ->withSum(['tasks_many as total_summa_month' => function ($query) {
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])->where('status', '=', 4);
            }],
                'give_price')->with(['currency' => function ($q) {
                    $q->select('id', 'id_code_currency','tech_name', 'id_payment', 'number_format', 'method_request_payment');
                }]);


        if(!$request->has('sorting_order')) {
            $requisites = $requisites->orderBy('id', 'desc');
        }
        $requisites = $requisites->paginate((int) iEXSetting('admin_requisites_pagination', 20));

        $admin_hidden_columns = explode(',', iEXSetting('admin_requisites_hidden_columns'));
        $allowedColumns = ['currency', 'account_number', 'id_group', 'views', 'exchange_today', 'exchange_month', 'status'];


        return response()->json([
            'items' => new RequisitesResources($requisites),
            'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                return $item;
            })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
            'per_page' => (int)iEXSetting('admin_requisites_pagination', 20),
        ]);
    }

    /**
     * Обработчик добавления реквизитов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                Requisites::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'id_currency' => 'required|integer|exists:currencies,id',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' =>1,
                'message' => $validator->messages()->first()
            ]);

        }
        $item = Requisites::create([
            'name' => ($request->has('name') ? $request->get('name') : ''),
            'id_currency' => ($request->has('id_currency') ? $request->get('id_currency') : 0),
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name .' успешно добавлен'
        ]);
    }

    /**
     * Форма редактирования реквизитов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id, Request $request)
    {
        $item = Requisites::findOrFail($id);

        // Удаление фото
        if ($request->has('is_delete_image')) {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/'.$item->photo_name));
            $item->photo_name = null;
            $item->save();

            return response()->json([
                'status' => 0,
                'message' => 'Фото удалена'
            ]);
        }


        $groups = RequisitesGroup::orderBy('sorting')->get()->pluck('name', 'id')->map(function ($value, $id) {
            return [
                'id' => $id,
                'value' => $value
            ];
        })->values();

        $currencies = Currency::active()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'value' => $item->tech_name . ' (' . __(($item->status == 0 ? 'активен' : 'не активен')) . ')'
            ];
        })->values();


        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'limit_views' => $item->limit_views,
                'view' => $item->view,
                'account_number' => $item->account_number,
                'name' => $item->name,
                'id_currency' => $item->id_currency,
                'id_group' => $item->id_group,
                'limit_day' => $item->limit_day,
                'limit_month' => $item->limit_month,
                'is_unique_shot' => (bool)$item->is_unique_shot,
                'status' => (bool)$item->status,
                'photo_status' => (bool)$item->photo_status,
                'photo_name' => $item->photo_name,
                'photo_name_path' => '/storage/'. $item->photo_name,


                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
            ],
            'currencies' => $currencies,
            'groups' => $groups,
        ]);
    }

    /**
     * Обработчик обновления реквизитов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $item = Requisites::findOrFail($id);

        if($request->has('is_update') and $request->get('is_update') == 1)
        {
            if($request->has('type'))
            {
                if($request->get('type') == 'wallet')
                {
                    $item->update([
                        'account_number' => ($request->has('account_number') ? $request->get('account_number') : ''),
                    ]);

                    $message = $item->name .' обновлен';
                } elseif($request->get('type') == 'view') {
                    $item->update([
                        'view' => 0,
                    ]);

                    $message = $item->name .' счетчик аннулирован';
                } elseif($request->get('type') == 'unique') {
                    $item->update([
                        'is_already_used' => 0,
                    ]);

                    $message = $item->name .' снова уникален';
                }

            }

            return response()->json([
                'status' => 0,
                'message' => $message ?? ''
            ]);
        }


        $validator = Validator::make($request->all(), [
            'id_currency' => 'required',
            'id_group' => 'required',
            'limit_day' => 'required',
            'limit_month' => 'required',
            'limit_views' => 'required',
        ]);

        // Перед добавлением резквизитов проверяем
        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

            if ($request->hasFile('photo_name')) {
                $logo = $request->file('photo_name');
                $filename = sprintf('iex-requisites-%s.%s', Str::random(10), $logo->getClientOriginalExtension());

                // Удаление предыдущих фото
                iex_file_delete(public_path('storage/'.$item->photo_name));

                $destinationPath = public_path('/storage');
                $logo->move($destinationPath, $filename);
                $item->update(['photo_name' => $filename]);
            }

            $options = [
                'name' => ($request->has('name') ? $request->get('name') : null),
                'account_number' => ($request->has('account_number') ? $request->get('account_number') : ''),
                'id_currency' => ($request->has('id_currency') ? (int)$request->get('id_currency') : 0),
                'limit_day' => ($request->has('limit_day') ? (float)$request->get('limit_day') : 0),
                'limit_month' => ($request->has('limit_month') ? (float)$request->get('limit_month') : 0),
                'status' => ($request->has('status') ? (int)$request->get('status') : 0),
                'id_group' => ($request->has('id_group') ? (int)$request->get('id_group') : 0),
                'type_shot' => ($request->has('type_shot') ? $request->get('type_shot') : 0),
                'limit_views' => ($request->has('limit_views') ? (int)$request->get('limit_views') : 0),
                'is_unique_shot' => $request->has('is_unique_shot') ? (int)$request->get('is_unique_shot') : 0,
                'photo_status' => $request->has('photo_status') ? (int)$request->get('photo_status') : 0,
            ];

            // Записываем в лог изменение счета (если таковы есть)
            if($item->account_number != $options['account_number'])
            {
                RequisiteLogs::create([
                    'id_requisite' => $item->id,
                    'id_user' => auth()->id(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_account' => $item->account_number,
                    'account' => $options['account_number']
                ]);
            }

            $item->update($options);

            return response()->json([
                'status' => 0,
                'message' => $item->name. ' успешно обновлен'
            ]);
    }

    /**
     * Удалить реквизиты (Но в историях оставляем)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $item = Requisites::find($id);
        $item->update([
            'is_history' => 1,
            'history_at' => Carbon::now()->toDateTimeString(),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Реквизит успешно добавлен в архив'
        ]);
    }

    /**
     * Лог реквизитов
     *
     * @param int $id
     * @param Request $request
     * @return Application|Factory|View|\Illuminate\Foundation\Application|\Illuminate\View\View
     */
    public function logs(int $id, Request $request)
    {
        $item = Requisites::find($id);
        $logs = RequisiteLogs::where('id_requisite', $item->id)->orderBy('id', 'desc')->paginate(20);

        return view('admin.basic.requisites.logs', [
            'logs' => $logs,
            'item' => $item
        ]);
    }

    /**
     * Архив реквизитов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function archive(Request $request)
    {
        $archives = Requisites::isHistory()->orderByDesc('history_at')->paginate(5);

        return response()->json([
            'items' => new RequisitesArchiveResources($archives)
        ]);
    }

    /**
     * Номер счета восстановлен
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restoreArchive(int $id): JsonResponse
    {
        $update = Requisites::find($id);
        $update->is_history = 0;
        $update->history_at = null;
        $update->save();


        return response()->json([
            'status' => 0,
            'message' => sprintf('Номер счета %s успешно восстановлен', $update->account_number)
        ]);
    }
}
