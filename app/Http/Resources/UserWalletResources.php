<?php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class UserWalletResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->mapWithKeys(function ($item, $key)
        {
            return [$item->id => [
                'id' => $item->id,
                'attributes' => [
                    'currency_id' => $item->id_currency,
                    'wallet' => $item->wallet,
                ]]];
        });
    }
}
