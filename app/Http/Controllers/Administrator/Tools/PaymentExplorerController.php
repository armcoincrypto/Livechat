<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentExplorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentExplorerController extends Controller
{
    public function index()
    {
        $payments = Payment::select('id', 'name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name
            ];
        });
        $authSystem = PaymentExplorer::orderBy('id', 'desc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'payment_name' => isset($item->payment) ? $item->payment->name : '',
                    'link' => $item->link,
                    'text' => $item->text,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                    'status' => (bool)$item->status
                ]
            ];
        });

        return response()->json([
            'data' => $authSystem,
            'total' => count($authSystem),
            'payments' => $payments
        ]);
    }

    /**
     * Обработчик и добавление нового Explorer
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_payment' => 'required',
            'link' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        PaymentExplorer::create([
            'id_payment' => $request->get('id_payment'),
            'link' => $request->get('link'),
            'text' => ($request->has('text') ? $request->get('text') : null),
            'status' => 0,
        ]);

        return response()->json([
            'status' => 0,
            'message' => __('Ссылка успешно добавлено')
        ]);
    }

    public function edit($id)
    {
        $item = PaymentExplorer::find($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'id_payment' => $item->id_payment,
                'link' => $item->link,
                'text' => $item->text
            ]
        ]);
    }

    /**
     * Обновить blockchain explorer
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_payment' => 'required',
            'link' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        PaymentExplorer::find($id)->update([
                'id_payment' => (int)$request->get('id_payment'),
                'link' => $request->get('link'),
                'text' => ($request->has('text') ? $request->get('text') : null)
            ]);

        return response()->json([
            'status' => 0,
            'message' => 'Ссылка успешно обновлена'
        ]);
    }

    /**
     * Удаление ссылки
     */
    public function destroy($id)
    {
        PaymentExplorer::find($id)->delete();
        return response()->json([
            'status' => 0,
            'message' => 'Ссылка успешно удалена'
        ]);
    }
}
