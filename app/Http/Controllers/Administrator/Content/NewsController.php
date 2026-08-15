<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Tools\NewsResources;
use App\Models\News;
use App\Models\NewsCategory;
use App\Support\Content\NewsUpdateParentUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class NewsController extends Controller
{
    /**
     * Список новостей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $news = News::orderBy('id', 'desc')->paginate(20);
        return response()->json(new NewsResources($news));
    }


    /**
     * Обработка и добавление новостей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'text.'.config('iexexchanger.default_locale') => 'required',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        if ($request->hasFile('image'))
        {
            $filename = Str::uuid()->toString() . '.' . $request->file('image')->getClientOriginalExtension();
            Image::read($request->file('image'))
                ->save(public_path('storage/news/'. $filename));
        }

        // Гарантируем наличие дефолтной категории "Общая" (idempotent)
        $defaultSlug = Str::slug('Общая');
        $defaultCategory = NewsCategory::firstOrCreate(
            ['slug' => $defaultSlug],
            ['name' => ['ru' => 'Общая'], 'color' => '#cccccc']
        );

        // Выбираем категорию: валидное значение из запроса или дефолт
        $requestedId = (int) ($request->category_id ?? 0);
        $categoryId = ($requestedId > 0 && NewsCategory::whereKey($requestedId)->exists())
            ? $requestedId
            : $defaultCategory->id;

        $options = [
            'id_user' => $request->user()->id,
            'image' => $filename ?? null,
            'parent_url' => Str::slug(array_first($request->name) . '-' . time()),
            'name' => $request->name,
            'text' => cleanHtmlContent($request->text),
            'category_id' => $categoryId,
        ];


        News::create($options);

        return response()->json([
            'status' => 0,
            'message' => __('Новость успешно опубликовано')
        ]);

    }

    /**
     * Форма обновления новостей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = News::find($id);
        $categories = NewsCategory::orderBy('name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'color' => $item->color
            ];
        })->values();

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'views' => $item->views,
                'image' => $item->image,
                'category_id' => $item->category_id,
                'image_path' => '/storage/news/'. $item->image,
                'text' => $item->text
            ],
            'categories' => $categories

        ]);
    }

    /**
     * Обработка и обновление новостей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'text.'.config('iexexchanger.default_locale') => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $news = News::find($id);
        $filename = $news->image;
        if ($request->hasFile('image'))
        {
            $filename = Str::uuid()->toString() . '.' . $request->file('image')->getClientOriginalExtension();
            Image::read($request->file('image'))
                ->save(public_path('storage/news/'. $filename));
        }

        // Гарантируем наличие дефолтной категории и валидируем выбранную
        $defaultSlug = Str::slug('Общая');
        $defaultCategory = NewsCategory::firstOrCreate(
            ['slug' => $defaultSlug],
            ['name' => ['ru' => 'Общая'], 'color' => '#cccccc']
        );
        $requestedId = (int) ($request->category_id ?? 0);
        $categoryId = ($requestedId > 0 && NewsCategory::whereKey($requestedId)->exists())
            ? $requestedId
            : $defaultCategory->id;

        $preserveParentUrl = NewsUpdateParentUrl::preserveFlagFromRequest(
            $request->input('preserve_parent_url')
        );
        $parentUrl = NewsUpdateParentUrl::resolve(
            (string) $news->parent_url,
            $request->name,
            $preserveParentUrl
        );

        if ($preserveParentUrl) {
            Log::info('news.update.preserve_parent_url', [
                'news_id' => $id,
                'parent_url' => $parentUrl,
                'user_id' => optional($request->user())->id,
            ]);
        }

        $options = [
            'image' => $filename,
            'parent_url' => $parentUrl,
            'name' => $request->name,
            'text' => cleanHtmlContent($request->text),
            'category_id' => $categoryId,
        ];

        $news->update($options);

        return response()->json([
            'status' => 0,
            'message' => 'Новость обновлена'
        ]);
    }

    /**
     * Удаление новостей
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
                'message' => 'В demo версии данная функция недоступна'
            ]);
        }

        $news = News::findOrFail($id);
        $news->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Новость успешно удалена'
        ]);
    }
}
