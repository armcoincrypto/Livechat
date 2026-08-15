<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\LinksReview;
use App\Models\LinksReviewGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class LinksReviewsController extends Controller
{
    protected array $allowFiltered = [

    ];

    /**
     * Дополнительные фильтры
     */
    protected array $allowLocaleOptions = [
        'description_review',
    ];

    /**
     * Список ссылок
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('showPage') == 'settings')
        {
            return response()->json([
                'description_review' => iEXContentLanguage('description_review', raw: true)
            ]);
        }


        $links = LinksReview::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'url' => $item->url,
                    'type' => $item->type,
                    'group' => [
                        'id' => $item->id_group,
                        'name' => $item->group?->name
                    ],
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });

        // Список групп
        $groups = LinksReviewGroup::get()->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });


        return response()->json([
            'data' => $links,
            'total' => count($links),
            'groups' => $groups
        ]);
    }

    /**
     * Обработка и добавление ссылки
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        // Если включена возможность обновления данных
        if((int)$request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                $options_locale = [
                    'description_review' => $request->description_review
                ];

                //Для мультиязычности
                $locale_data = collect($options_locale)->only($this->allowLocaleOptions)->all();
                iEXContentLanguage($locale_data);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'url.'.config('iexexchanger.default_locale') => 'required|max:255',
            'id_group' => ['required', 'exists:links_review_groups,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        if ($request->hasFile('icon')) {
            // Загружаем фото
            $filename = sprintf('%s.png', Str::random(10));
            Image::read(
                $request->file('icon')->getRealPath()
            )->save(public_path('storage/links/'.$filename));
        }

        $options = [
            'id_group' => ($request->has('id_group') ? (int)$request->get('id_group') : 0),
            'icon' => $filename ?? '',
            'is_review' => ($request->has('is_review') ? (int)filter_var($request->get('is_review'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'is_show' => ($request->has('is_show') ? (int)filter_var($request->get('is_show'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'count_review' => $request->has('count_review') ? (int)$request->get('count_review') : 0,
            'name' => $request->name,
            'url' => $request->url,
            'description' => $request->description
        ];

        LinksReview::create($options);

        return response()->json([
            'status' => 0,
            'message' => __('Ссылка успешно добавлена')
        ]);
    }

    /**
     * Форма редактирования ссылки
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = LinksReview::findOrFail($id);

        // Список групп
        $groups = LinksReviewGroup::get()->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'icon' => $item->icon,
                'icon_path' => '/storage/links/'.$item->icon,
                'name' => $item->name,
                'url' => $item->url,
                'type' => $item->type,
                'count_review' => (int)$item->count_review,
                'is_review' => (bool)$item->is_review,
                'is_show' => (bool)$item->is_show,
                'group' => [
                    'id' => $item->id_group,
                    'name' => $item->group?->name
                ],
            ],
            'groups' => $groups
        ]);
    }

    /**
     * Обработка и обновление ссылки
     */
    public function update(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'url.'.config('iexexchanger.default_locale') => 'required|max:255',
            'id_group' => ['required', 'exists:links_review_groups,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $link_review = LinksReview::findOrFail($id);
        $filename = $link_review->icon;

        if ($request->hasFile('icon')) {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/links/'.$filename));

            // Загружаем фото
            $filename = sprintf('%s.png', Str::random(10));
            Image::read(
                $request->file('icon')->getRealPath()
            )->save(public_path('storage/links/'.$filename));
        }

        $options = [
            'id_group' => ($request->has('id_group') ? (int)$request->get('id_group') : 0),
            'icon' => $filename,
            'is_review' => ($request->has('is_review') ? (int)filter_var($request->get('is_review'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'is_show' => ($request->has('is_show') ? (int)filter_var($request->get('is_show'), FILTER_VALIDATE_BOOLEAN ) : 0),
            'count_review' => $request->has('count_review') ? (int)$request->get('count_review') : 0,
            'name' => $request->name,
            'url' => $request->url,
            'description' => $request->description
        ];

        $link_review->update($options);

        return response()->json([
            'asd' => $request->all(),
            'status' => 0,
            'message' => $link_review->name .' '. __('успешно обновлена')
        ]);
    }

    /**
     * Удаление ссылки
     *
     * @throws \Exception
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $item = LinksReview::findOrFail($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
