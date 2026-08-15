<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\DirectionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DirectionNotificationController extends Controller
{
    /**
     * Список уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $notifications = DirectionNotification::orderBy('sorting')->get();

        return response()->json([
            'data' => $notifications->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'description' => $item->description,
                        'sorting' => $item->sorting,
                        'direction' => [
                            'id' => $item->direction_exchange?->id,
                            'name' => $item->direction_exchange?->tech_name
                        ],
                        'status' => (bool)$item->status,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            'total' => count($notifications)
        ]);
    }

    /**
     * Форма добавления уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        $direction = DirectionExchange::select('id', 'status','tech_name')->isEnabled()->get()->map(function($item) {
            return [
                'id' => $item->id,
                'value' => $item->tech_name
            ];
        });

        return response()->json([
            'items' => $direction
        ]);
    }

    /**
     * Обработка и добавление уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                DirectionNotification::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }


        $validator = Validator::make($request->all(), [
            'id_direction_exchange' => 'required',
            'status' => 'required',
            'description.'.config('iexexchanger.default_locale') => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'id_direction_exchange' => $request->get('id_direction_exchange'),
            'status' => $request->has('status') ? $request->get('status') : 0,
            'description' => $request->description
        ];

        DirectionNotification::create($options);
        return response()->json([
            'status' => 0,
            'message' => 'Уведомление успешно добавлена'
        ]);
    }

    /**
     * Форма редактирования уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id, Request $request)
    {
        if($request->has('is_loading_direction'))
        {
            $direction = DirectionExchange::select('id', 'status','tech_name')->isEnabled()->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->tech_name
                ];
            });

            return response()->json([
                'items' => $direction
            ]);
        }

        $item = DirectionNotification::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'description' => $item->getTranslations('description'),
                'id_direction_exchange' => $item->id_direction_exchange,
                'status' => (bool)$item->status,
                'text_color' => $item->text_color,
                'bg_color' => $item->bg_color,
                'is_enabled_schedule' => (bool)$item->is_enabled_schedule,
                'from_time' => $item->from_time,
                'to_time' => $item->to_time,
                'is_order_detail' => (bool)$item->is_order_detail,
                'title' => $item->getTranslations('title')
            ],
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $notification = DirectionNotification::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'id_direction_exchange' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'id_direction_exchange' => $request->get('id_direction_exchange'),
            'bg_color' => $request->has('bg_color') ? $request->get('bg_color') : null,
            'text_color' => $request->has('text_color') ? $request->get('text_color') : null,
            'status' => $request->has('status') ? $request->get('status') : 0,
            'is_enabled_schedule' => $request->has('is_enabled_schedule') ? $request->get('is_enabled_schedule') : 0,
            'from_time' => $request->has('from_time') ? $request->get('from_time') : null,
            'to_time' => $request->has('to_time') ? $request->get('to_time') : null,
            'is_order_detail' => $request->has('is_order_detail') ? $request->get('is_order_detail') : 0,
            'description' => $request->description,
            'title' => $request->title,
        ];

        $notification->update($options);


        return response()->json([
            'status' => 0,
            'message' => 'Уведомление успешно обновлена'
        ]);
    }

    /**
     * Удаление уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $notify = DirectionNotification::findOrFail($id);
        $notify->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Уведомление успешно удалено'
        ]);
    }
}
