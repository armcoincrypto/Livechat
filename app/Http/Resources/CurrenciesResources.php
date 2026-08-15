<?php
declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Operations\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class CurrenciesResources extends ResourceCollection
{
    public bool $preserveKeys = true;

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->mapWithKeys(function ($item) {
            return [$item->id => new CurrencyResource($item)];
        });
    }
}
