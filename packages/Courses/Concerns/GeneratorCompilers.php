<?php

namespace iEXPackages\Courses\Concerns;

use App\Models\DirectionExchange;
use iEXPackages\ExchangerClient\Http\Resources\Operations\DirectionDetailResource;

trait GeneratorCompilers
{
    public function generatorDirection(
        DirectionExchange $item,
    ): array|object
    {
        return new DirectionDetailResource($item);
    }
}
