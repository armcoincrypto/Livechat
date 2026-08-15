<?php
namespace iEXPackages\ExchangerClient\Http\Controller\Content;

use App\Models\FaqCategory;
use iEXPackages\ExchangerClient\Http\Resources\Content\FAQCategoryResources;

class FAQController
{
    public function index(): FAQCategoryResources
    {
        $data = FaqCategory::query()
            // показываем только включенные категории
            ->where('status', 1)
            ->with([
                'faq' => function ($query) {
                    $query
                        ->where('status', 1)
                        ->orderBy('sorting')
                        ->orderBy('id');
                },
            ])
            // скрываем пустые категории (без активных FAQ)
            ->whereHas('faq', function ($query) {
                $query->where('status', 1);
            })
            ->select(['id', 'name', 'sorting', 'status'])
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();

        return new FAQCategoryResources($data);
    }
}
