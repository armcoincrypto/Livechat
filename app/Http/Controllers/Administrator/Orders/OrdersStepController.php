<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Models\OrderStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrdersStepController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'is_enabled_module_order_step',
    ];

    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $lists = OrderStep::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'status' => (bool)$item->status,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });



        return response()->json([
            'data' => $lists,
            'total' => count($lists)
        ]);
    }

    /**
     * Обработка и добавление записи
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                OrderStep::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }


        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'id_manager' => auth()->id(),
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'name' => $request->name
        ];

        $item = OrderStep::create($options);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' '. __('успешно добавлен')
        ]);
    }

    /**
     * Форма редактирования записи
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $order_step = OrderStep::findOrFail($id);

        return response()->json([
            'id' => $order_step->id,
            'attributes' => [
                'name' => $order_step->getTranslations('name'),
                'status' => (bool)$order_step->status
            ]
        ]);
    }

    /**
     * Обработка и обновления записи
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $order_step = OrderStep::findOrFail($id);
        $filename = $order_step->icon;


        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'id_manager' => auth()->id(),
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'name' => $request->name
        ];


        $order_step->update($options);

        return response()->json([
            'status' => 0,
            'message' => $order_step->name . ' '. __('успешно обновлен')
        ]);
    }

    /**
     * Удаление записи
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $order_step = OrderStep::findOrFail($id);
        $oldItem = $order_step;
        $order_step->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . ' '. __('успешно удален')
        ]);
    }
}
