<?php

namespace App\Http\Controllers\Administrator\Marketing\Contests;

use App\Http\Controllers\Controller;
use App\Models\ContestConditionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContestsConditionController extends Controller
{
    /**
     * Список требований
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $items = ContestConditionModel::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'locales' => $item->getTranslations(),
                    'description' => $item->description,
                ]
            ];
        });

        return response()->json([
            'data' => $items
        ]);
    }

    /**
     * Обработка и добавление условий
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description.'.config('iexexchanger.default_locale') => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'description' => $request->description
        ];
        $options['id_manager'] = $request->user()->id;
        ContestConditionModel::create($options);

        return response()->json([
            'status' => 0,
            'message' =>'Текст успешно добавлен'
        ]);
    }

    /**
     * Обработка и обновление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description.'.config('iexexchanger.default_locale') => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

            $item = ContestConditionModel::findOrFail($id);
            $item->update([
                'description' => $request->description
            ]);

        return response()->json([
            'status' => 0,
            'message' =>'Текст успешно обновлен'
        ]);
    }

    /**
     * Удаление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $item = ContestConditionModel::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' =>'Текст успешно удален'
        ]);
    }
}
