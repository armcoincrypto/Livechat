<?php

namespace App\Http\Controllers\Administrator\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\BannerButton;
use App\Settings\BannerConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    /**
     * Дополнительные фильтры
     *
     * @var array
     */
    protected array $allowFilteredPage = [
        'is_autoplay',
        'timeout',
        'hide_nav'
    ];

    /**
     * Список баннеров
     *
     * @param BannerConfig $settings
     * @param Request $request
     * @return JsonResponse
     */
    public function index(
        BannerConfig $settings,
        Request $request
    )
    {
        if($request->has('showPage') == 'settings')
        {
            return response()->json([
                'is_autoplay' => $settings->isAutoplay(),
                'timeout' => $settings->timeout(),
                'hide_nav' => (int)$settings->hideNav(),
            ]);
        }


        $items = Banner::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'title' => $item->title,
                    'text' => $item->text,
                    'status' => $item->status,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });

        $buttons = BannerButton::get()->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });


        return response()->json([
            'data' => $items,
            'total' => count($items),
            'buttons' => $buttons
        ]);

    }


    /**
     * Сохранение баннеров
     *
     * @param BannerConfig $settings
     * @param Request $request
     * @return JsonResponse
     */
    public function store(
        BannerConfig $settings,
        Request $request
    )
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                // Обновление конфига
                $update = [];
                foreach ($this->allowFilteredPage as $item) {
                    $update[$item] = $request->has($item) ? (int)$request->get($item) : 0;
                }
                $settings->update($update);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }



        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        if($request->hasFile('images'))
        {
            $logo_icon = $request->file('images');
            $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

            if (! \File::isDirectory(public_path('storage/banners/'))) {
                \File::makeDirectory(public_path('storage/banners/'), 0777, true, true);
            }

            $destinationPath = public_path('/storage/banners');
            $logo_icon->move($destinationPath, $filename);
            $options['images'] = $filename;
        }

        if ($request->hasFile('images_banner')) {
            $images_banner = $request->file('images_banner');
            $filename_image = sprintf('banner-image-%s.%s', Str::random(10), $images_banner->getClientOriginalExtension());
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/banners/'.$filename_image));

            $destinationPath = public_path('/storage/banners');
            $images_banner->move($destinationPath, $filename_image);
            $options['images_banner'] = $filename_image;
        }

        $options['color_title'] = $request->has('color_title') ? (string)$request->get('color_title') : null;
        $options['color_text'] = $request->has('color_text') ? (string)$request->get('color_text') : null;
        $options['status'] = $request->has('status') ? (int)filter_var($request->get('status'), FILTER_VALIDATE_BOOLEAN ) : 0;
        $options['title'] = $request->title;
        $options['text'] = $request->text;

        $banner = Banner::create($options);
        if(!empty($request->ids_buttons)) {
            $banner->buttons()->sync(array_map('intval', explode(',', $request->ids_buttons)) ?? []);
        }

        return response()->json([
            'status' => 0,
            'message' => __('Баннер успешно создан')
        ]);
    }

    /**
     * JSON формат баннеров
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function edit(Request $request, int $id)
    {
        $item = Banner::find($id);

        // Удаление фото
        if ($request->has('is_delete_image'))
        {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/banners/'.$item->images));
            $item->images = null;
            $item->save();

            return response()->json([
                'status' => 0,
                'message' => 'Фото удалена'
            ]);
        }

        if ($request->has('is_delete_image_banner'))
        {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/banners/'.$item->images_banner));
            $item->images_banner = null;
            $item->save();

            return response()->json([
                'status' => 0,
                'message' => 'Баннер удален'
            ]);
        }


        $buttons = BannerButton::select('id', 'name')
            ->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                ];
            });


        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'title' => $item->title,
                'text' => $item->text,
                'status' => (bool)$item->status,
                'color_title' => $item->color_title,
                'color_text' => $item->color_text,
                'ids_buttons' => $item->buttons->map(fn($item) => $item['id']),
                'images' => $item->images,
                'images_url' => '/storage/banners/'.$item->images,
                'images_banner' => $item->images_banner,
                'images_banner_url' => '/storage/banners/'.$item->images_banner,

                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
                'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                'updated_at_human' => $item->updated_at->diffForHumans(),
            ],
            'buttons' => $buttons,
        ]);
    }

    /**
     * Обновление баннеров
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function update(int $id, Request $request)
    {
        $banner = Banner::findOrFail($id);
        $validator = Validator::make($request->all(), []);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => __('Баннер не обновлен')
            ]);
        } else {

            if($request->hasFile('images'))
            {
                // Удаляем актуальное фото
                iex_file_delete(public_path('storage/banners/'.$banner->images));

                $logo_icon = $request->file('images');
                $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

                if (! \File::isDirectory(public_path('storage/banners/'))) {
                    \File::makeDirectory(public_path('storage/banners/'), 0777, true, true);
                }

                $destinationPath = public_path('/storage/banners');
                $logo_icon->move($destinationPath, $filename);
                $options['images'] = $filename;
            }

            if ($request->hasFile('images_banner'))
            {
                // Удаляем актуальное фото
                iex_file_delete(public_path('storage/banners/'.$banner->images_banner));

                $images_banner = $request->file('images_banner');
                $filename_image = sprintf('banner-image-%s.%s', Str::random(10), $images_banner->getClientOriginalExtension());
                // Удаляем актуальное фото
                iex_file_delete(public_path('storage/banners/'.$filename_image));

                $destinationPath = public_path('/storage/banners');
                $images_banner->move($destinationPath, $filename_image);
                $options['images_banner'] = $filename_image;
            }

            $options['color_title'] = $request->has('color_title') ? (string)$request->get('color_title') : null;
            $options['color_text'] = $request->has('color_text') ? (string)$request->get('color_text') : null;
            $options['status'] = $request->has('status') ? (int)filter_var($request->get('status'), FILTER_VALIDATE_BOOLEAN ) : 0;
            $options['title'] = $request->title;
            $options['text'] = $request->text;


            $banner->update($options);
            if(!empty($request->ids_buttons)) {
                $banner->buttons()->sync(array_map('intval', explode(',', $request->ids_buttons)) ?? []);
            } else {
                $banner->buttons()->sync([]);
            }

            return response()->json([
                'status' => 0,
                'message' => sprintf('Баннер %s успешно обновлен', $banner->title)
            ]);
        }
    }

    /**
     * Удалить
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $item = Banner::findOrFail($id);
        iex_file_delete(public_path('storage/banners/'.$item->images));
        $oldItem = $item;
        $item->buttons()->sync([]);
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->title .__('успешно удален')
        ]);
    }
}
