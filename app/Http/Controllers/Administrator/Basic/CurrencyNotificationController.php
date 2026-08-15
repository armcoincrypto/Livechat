<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 06.11.2019
 * Time: 20:23
 */

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyNotification as CurrencyNotificationModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrencyNotificationController extends Controller
{
    /**
     * Список уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $notifications = CurrencyNotificationModel::orderBy('sorting')->get();

        return response()->json([
            'data' => $notifications->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'description' => $item->description,
                        'sorting' => $item->sorting,
                        'currency' => [
                            'id' => $item->currency?->id,
                            'name' => $item->currency?->tech_name
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
        $currencies = Currency::active()
            ->where([
                ['is_archive', '=', 0],
            ])->pluck('tech_name', 'id')->map(function ($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value,
                ];
            })->values();

        return response()->json([
            'currencies' => $currencies
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
                CurrencyNotificationModel::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }


        $validator = Validator::make($request->all(), [
            'id_currency' => 'required',
            'status' => 'required',
            'description.'.config('iexexchanger.default_locale') => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = CurrencyNotificationModel::create([
            'id_currency' => $request->id_currency,
            'status' => $request->has('status') ? $request->get('status') : 0,
            'description' => $request->description,
        ]);

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
    public function edit(int $id)
    {
        $item = CurrencyNotificationModel::findOrFail($id);
        $currencies = Currency::active()
            ->where([
                ['is_archive', '=', 0],
            ])->pluck('tech_name', 'id')->map(function ($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value,
                ];
            })->values();

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'description' => $item->getTranslations('description'),
                'sorting' => $item->sorting,
                'id_currency' => $item->id_currency,
                'status' => (bool)$item->status,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
            ],
            'currencies' => $currencies
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $notification = CurrencyNotificationModel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'id_currency' => 'required',
            'status' => 'required',
            'description.'.config('iexexchanger.default_locale') => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
        $notification->update([
            'id_currency' => $request->id_currency,
            'status' => $request->has('status') ? $request->get('status') : 0,
            'description' => $request->description,
        ]);

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
        $notify = CurrencyNotificationModel::findOrFail($id);
        $notify->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Уведомление успешно удалено'
        ]);
    }
}
