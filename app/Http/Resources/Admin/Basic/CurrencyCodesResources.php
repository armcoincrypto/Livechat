<?php

namespace App\Http\Resources\Admin\Basic;

use App\Services\Calculator\CalculatorMathService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CurrencyCodesResources extends ResourceCollection
{
    private $pagination;


    public function __construct($resource)
    {
        $this->pagination = [
            'total' => $resource->total(),
            'per_page' => $resource->perPage(),
            'current_page' => $resource->currentPage(),
            'from' => $resource->firstItem(),
            'to' => $resource->lastItem(),
            'last_page' => $resource->lastPage(),
        ];

        $resource = $resource->getCollection(); // Necessary to remove meta and links

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item)
            {
                $getRate = 0;
                if($item->id_parser_exchange > 0 and isset($item->parser_exchange))
                {
                    $mathValue = new CalculatorMathService($item->parser_exchange->summa);
                    $mathValue->calculate($item->add_to_course);
                    $getRate = $mathValue->getSumma();
                }elseif($item->id_parser_formula > 0 and isset($item->parser_formula)) {
                    $mathValue = new CalculatorMathService($item->parser_formula->summa);
                    $mathValue->calculate($item->add_to_course_formula);
                    $getRate = $mathValue->getSumma();
                } elseif($item->internal_rate > 0) {
                    $getRate = $item->internal_rate;
                }

                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'exchange_rate' => $getRate,
                        'currencies' => $item->currency->count() > 0 ? $item->currency->map(function($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->tech_name
                            ];
                        }) : [],
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
