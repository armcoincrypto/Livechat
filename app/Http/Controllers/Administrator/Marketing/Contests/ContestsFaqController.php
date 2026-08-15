<?php

namespace App\Http\Controllers\Administrator\Marketing\Contests;

use App\Http\Controllers\Controller;
use App\Models\ContestFaqModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContestsFaqController extends Controller
{
    /**
     * Список вопросов и ответов
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $items = ContestFaqModel::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'locales' => $item->getTranslations(),
                    'title' => $item->title,
                    'description' => $item->description,
                ]
            ];
        });

        return response()->json([
            'data' => $items
        ]);
    }

    /**
     * Обработка и добавление вопросов и ответов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required',
            'description.'.config('iexexchanger.default_locale') => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'status' => 1,
            'title' => $request->title,
            'description' => $request->description,
        ];

        $faq = ContestFaqModel::create($options);

        return response()->json([
            'status' => 0,
            'message' => $faq->title .' успешно добавлен'
        ]);

    }

    /**
     * Форма редактирования вопросов и ответов
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(int $id)
    {
        $faq = ContestFaqModel::findOrFail($id);

        return view('admin.plugins.contests.faq.edit', compact('faq'));
    }

    /**
     * Обработка и редактирование вопросов и ответов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required',
            'description.'.config('iexexchanger.default_locale') => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $faq = ContestFaqModel::findOrFail($id);
        $options = [
            'title' => $request->title,
            'description' => $request->description,
        ];

        $faq->update($options);

        return response()->json([
            'status' => 0,
            'message' => $faq->title.' успешно обновлен'
        ]);
    }

    /**
     * Удаление вопросов и ответов
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $faq = ContestFaqModel::findOrFail($id);
        $oldItem = $faq;
        $faq->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->title.' успешно удален'
        ]);
    }
}
