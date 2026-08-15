<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Content;

use App\Http\Controllers\AbstractController;
use App\Models\News;
use iEXPackages\ExchangerClient\Http\Resources\Content\NewsResource;
use iEXPackages\ExchangerClient\Http\Resources\Content\NewsResources;
use Illuminate\Http\JsonResponse;

class NewsController extends AbstractController
{
    /**
     * Получаем список новостей
     *
     * @return NewsResources
     */
    public function index(): NewsResources
    {
        return new NewsResources(
            News::latest('id')->paginate(20)
        );
    }

    /**
     * Получение новости по slug
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function show(string $slug): JsonResponse
    {
        $getId = $this->extractIdFromSlug($slug);
        $item = News::findOrFail($getId);

        $item->increment('views');

        return response()->json(new NewsResource($item));
    }

    /**
     * Извлекает ID из Slug и проверяет корректность формата
     *
     * @param string $slug
     * @return int
     */
    protected function extractIdFromSlug(string $slug): int
    {
        // Canonical API/list format: "{id}-{parent_url}" (e.g. "15-exswaping-teper-na-exchangesumo")
        if (preg_match('/^(\d+)-/', $slug, $matches)) {
            return (int) $matches[1];
        }

        // Blog/SEO public URL format: "{parent_url}-{id}" (e.g. "exswaping-teper-na-exchangesumo-15")
        if (preg_match('/-(\d+)$/', $slug, $matches)) {
            return (int) $matches[1];
        }

        abort(404, 'Новость не найдена');
    }
}
