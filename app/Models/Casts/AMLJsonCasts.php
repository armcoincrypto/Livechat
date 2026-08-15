<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 18.09.2020
 * Time: 10:09
 */

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AMLJsonCasts implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  Model  $model
     * @param  mixed  $value
     */
    public function get($model, string $key, $value, array $attributes): array
    {
        if (empty($value)) {
            return [];
        }

        return json_decode($value, true);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Model  $model
     * @param  array  $value
     */
    public function set($model, string $key, $value, array $attributes): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }
}
