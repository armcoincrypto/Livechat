<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Tools\ReviewsResources;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReviewsController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'is_enabled_reviews',
        'count_reviews_page',
    ];

    protected array $allowLocaleOptions = [
        'reviews_block_title',
        'reviews_block_description'
    ];

    /***
     * Главная страница отзывов
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('showPage') == 'settings')
        {
            return response()->json([
                'reviews_block_title' => iEXContentLanguage('reviews_block_title', raw: true),
                'reviews_block_description' => iEXContentLanguage('reviews_block_description', raw: true),
                'is_enabled_reviews' => (bool)iEXSetting('is_enabled_reviews'),
            ]);
        }

        $reviews = Review::filter($request->all())
            ->where('version', '=', 1)
            ->orderBy('id', 'desc')->paginate((int) iEXSetting('count_reviews_page', 20));

        return response()->json([
            'items' => new ReviewsResources($reviews)
        ]);
    }

    /**
     * Обработка и публикация отзыва
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                Review::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => __('Отзыв опубликован')
                ]);
            }
        }

        // Если включена возможность обновления данных
        if((int)$request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                $options_locale = [
                    'reviews_block_title' => $request->reviews_block_title,
                    'reviews_block_description' => $request->reviews_block_description,
                ];

                $array_options = [];
                foreach ($this->allowFiltered as $item)
                {
                    if($request->has($item) and is_numeric($request->get($item))) {
                        $array_options[$item] = ($request->has($item) ? (int)$request->get($item) : 0);
                    } else {
                        $array_options[$item] = ($request->has($item) ? $request->get($item) : null);
                    }
                }

                //Для мультиязычности
                $locale_data = collect($options_locale)->only($this->allowLocaleOptions)->all();

                iEXContentLanguage($locale_data);
                iEXSetting($array_options);
            }


            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }
    }

    /**
     * Удаление отзыва
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $item = Review::find($id);
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Отзыв успешно удален'
        ]);
    }
}
