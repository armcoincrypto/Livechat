<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\InfoStatistic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StatisticsController extends Controller
{
    /**
     * Список статистики
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $statistics = InfoStatistic::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'value' => $item->value,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });

        return response()->json([
            'data' => $statistics,
            'total' => count($statistics)
        ]);
    }

    /**
     * Обработка и добавление статистики
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'value.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        if($request->hasFile('icon')) {
            if (! \File::isDirectory(public_path('storage/statistics/'))) {
                \File::makeDirectory(public_path('storage/statistics/'), 0777, true, true);
            }


            $logo = $request->file('icon');
            $filename = sprintf('%s.%s', Str::random(13), $logo->getClientOriginalExtension());

            $destinationPath = public_path('/storage/statistics');
            $logo->move($destinationPath, $filename);
        }

        $options = [
            'status' => ($request->has('status') ? (int)filter_var($request->get('status'), FILTER_VALIDATE_BOOLEAN) : 0),
            'name' => $request->name,
            'value' => $request->value,
            'image' => $filename ?? ''
        ];


        $info = InfoStatistic::create($options);

        return response()->json([
            'status' => 0,
            'message' => $info->name.' успешно добавлен'
        ]);
    }

    /**
     * Форма редактирования статистики
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $item = InfoStatistic::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'value' => $item->value,
                'icon' => $item->image,
                'status' => (bool)$item->status,
                'locales' => $item->getTranslations(),
                'icon_path' => '/storage/statistics/'.$item->image,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
                'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                'updated_at_human' => $item->updated_at->diffForHumans(),
            ]
        ]);
    }

    /**
     * Обработка и обновление статистики
     *
     * @return JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'value.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $InfoStatistic = InfoStatistic::findOrFail($id);
        $filename = $InfoStatistic->image;


        if ($request->hasFile('icon')) {
            if (! \File::isDirectory(public_path('storage/statistics/'))) {
                \File::makeDirectory(public_path('storage/statistics/'), 0777, true, true);
            }

            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/contact/'.$filename));

            $logo_icon = $request->file('icon');
            $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

            $destinationPath = public_path('/storage/statistics');
            $logo_icon->move($destinationPath, $filename);
        }

        $options = [
            'status' => ($request->has('status') ? (int)filter_var($request->get('status'), FILTER_VALIDATE_BOOLEAN) : 0),
            'name' => $request->name,
            'value' => $request->value,
            'image' => $filename ?? ''
        ];

        $InfoStatistic->update($options);

        return response()->json([
            'status' => 0,
            'message' => $InfoStatistic->name .' успешно обновлен',
            's' => $options
        ]);
    }

    /**
     * Удаление статистики
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $item = InfoStatistic::findOrFail($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name.' успешно удален'
        ]);
    }
}
