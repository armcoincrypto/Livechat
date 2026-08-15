<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\LinksFooter;
use App\Models\LinksFooterGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use function Clue\StreamFilter\fun;

class LinksFootersController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowLocaleOptions = [
        'input_footer_title',
        'description_footer_text',
    ];

    /**
     * Дополнительные фильтры
     */
    protected array $allowOptions = [
        'input_footer_select'
    ];

    /**
     * Список ссылок
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('showPage') == 'settings')
        {
            return response()->json([
                'input_footer_title' => iEXContentLanguage('input_footer_title', raw: true),
                'description_footer_text' => iEXContentLanguage('description_footer_text', raw: true),
                'input_footer_image' => iEXSetting('input_footer_image'),
                'input_footer_image_url' => '/storage/'.iEXSetting('input_footer_image'),
                'input_footer_select' => iEXSetting('input_footer_select')
            ]);
        }


        $links = LinksFooter::orderBy('sorting')->get()->map(function ($linksFooter) {
            return [
                'id' => $linksFooter->id,
                'attributes' => [
                    'name' => $linksFooter->name,
                    'url' => $linksFooter->url,
                    'group' => [
                        'id' => $linksFooter->id_group,
                        'name' => $linksFooter->group?->name
                    ],
                    'is_blank' => (bool)$linksFooter->is_blank,
                    'status' => (bool)$linksFooter->status,

                    'created_at' => $linksFooter->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $linksFooter->created_at->diffForHumans(),
                    'updated_at' => $linksFooter->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $linksFooter->updated_at->diffForHumans(),
                ]
            ];
        });
        // Список групп
        $groups = LinksFooterGroup::get()->map(function($item) {
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
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {

        // Если включена возможность обновления данных
        if((int)$request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                $options_locale = [
                    'input_footer_title' => $request->input_footer_title,
                    'description_footer_text' => $request->description_footer_text,
                ];


                $array_options = [];
                foreach ($this->allowOptions as $item) {
                    if ($request->has($item) and is_array($request->get($item))) {
                        $array_options[$item] = implode(',', $request->get($item));
                    } else {

                        if($request->has($item) and is_numeric($request->get($item))) {
                            $array_options[$item] = ($request->has($item) ? (int)$request->get($item) : 0);
                        } else {
                            $array_options[$item] = ($request->has($item) ? $request->get($item) : null);
                        }
                    }
                }

                if ($request->hasFile('footer_image') and !empty($request->hasFile('footer_image')))
                {
                    $logo = $request->file('footer_image');
                    $filename = sprintf('iex-footer-%s.%s', Str::random(10), $logo->getClientOriginalExtension());

                    // Удаление предыдущих фото
                    if (! empty(iEXSetting('input_footer_image'))) {
                        iex_file_delete(public_path('storage/'.iEXSetting('input_footer_image')));
                    }

                    $destinationPath = public_path('/storage');
                    $logo->move($destinationPath, $filename);
                    $array_options['input_footer_image'] = $filename;
                }

                //Для мультиязычности
                $locale_data = collect($options_locale)->only($this->allowLocaleOptions)->all();

                iEXContentLanguage($locale_data);
                iEXSetting($array_options);
            }

            return response()->json([
                ...$array_options ?? [],
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }


        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'id_group' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $options = [
            'id_group' => ($request->has('id_group') ? $request->get('id_group') : 0),
            'is_blank' => $request->has('is_blank') ? $request->get('is_blank') : 0,
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'name' => $request->name,
            'url' => $request->url
        ];

        LinksFooter::create($options);

        return response()->json([
            'status' => 0,
            'message' => __('Ссылка успешно добавлена')
        ]);
    }

    /**
     * Форма редактирования ссылки
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $linksFooter = LinksFooter::findOrFail($id);

        // Список групп
        $groups = LinksFooterGroup::get()->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });

        return response()->json([
            'id' => $linksFooter->id,
            'attributes' => [
                'locales' => $linksFooter->getTranslations(),
                'name' => $linksFooter->name,
                'url' => $linksFooter->url,
                'is_blank' => (bool)$linksFooter->is_blank,
                'status' => (bool)$linksFooter->status,
                'group' => [
                    'id' => $linksFooter->id_group,
                    'name' => $linksFooter->group?->name
                ],

                'created_at' => $linksFooter->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $linksFooter->created_at->diffForHumans(),
                'updated_at' => $linksFooter->updated_at->translatedFormat('d M Y H:i'),
                'updated_at_human' => $linksFooter->updated_at->diffForHumans(),
            ],

            'groups' => $groups
        ]);
    }

    /**
     * Обработка и обновление ссылки
     */
    public function update(int $id, Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'id_group' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $options = [
            'id_group' => ($request->has('id_group') ? $request->get('id_group') : 0),
            'is_blank' => $request->has('is_blank') ? $request->get('is_blank') : 0,
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'name' => $request->name,
            'url' => $request->url
        ];
        $link_review = LinksFooter::findOrFail($id);
        $link_review->update($options);

        return response()->json([
            'status' => 0,
            'message' => $link_review->name .' '. __('успешно обновлена')
        ]);
    }

    /**
     * Удаление ссылки
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $item = LinksFooter::findOrFail($id);
        $oldItem = $item;
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
