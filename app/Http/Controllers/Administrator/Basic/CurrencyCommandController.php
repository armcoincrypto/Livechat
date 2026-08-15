<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\CurrencyCommandResources;
use App\Models\Currency;
use App\Models\CurrencyCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrencyCommandController extends Controller
{
    /**
     * Список команд
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $groups = CurrencyCommand::orderBy('sorting')->paginate(20);

        return response()->json([
            'items' => new CurrencyCommandResources($groups),
        ]);
    }

    /**
     * Форма добавления группы
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
     * Обработка и добавления новой команды
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'id_currency' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
        $item = CurrencyCommand::create([
            'id_currency' => $request->id_currency,
            'name' => $request->name,
            'amount' => ($request->has('amount') ? $request->get('amount') : 0),
            'sorting' => ($request->has('sorting') ? $request->get('sorting') : 0),
        ]);
        $item->save();

        return response()->json([
            'status' => 0,
            'message' => $item->name .' успешно добавлен'
        ]);
    }

    /**
     * Форма изменения команды
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = CurrencyCommand::findOrFail($id);

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
                'name' => $item->name,
                'amount' => $item->amount,
                'sorting' => $item->sorting,
                'currency' => [
                    'id' => $item->currency?->id,
                    'name' => $item->currency?->tech_name
                ],
                'id_currency' => $item->id_currency,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
            ],

            'currencies' => $currencies
        ]);
    }

    /**
     * Обработчик обновления группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $group = CurrencyCommand::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'id_currency' => ['required', 'exists:currencies,id'],
            'name' => 'required',
            'amount' => ['required', 'numeric'],
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group->update([
            'id_currency' => $request->id_currency,
            'name' => ($request->has('name') ? $request->get('name') : null),
            'amount' => ($request->has('amount') ? $request->get('amount') : 0),
            'sorting' => ($request->has('sorting') ? $request->get('sorting') : 0),
        ]);

        return response()->json([
            'status' => 0,
            'message' => $group->name.' успешно обновлен'
        ]);
    }

    /**
     * Удалить команду
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $item = CurrencyCommand::findOrFail($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name.' успешно удален'
        ]);
    }
}
