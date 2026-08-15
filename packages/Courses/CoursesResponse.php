<?php

namespace iEXPackages\Courses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class CoursesResponse implements Arrayable, Jsonable, JsonSerializable
{
    protected array $context;

    public function __construct(array $context)
    {
        $this->context = $context;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->context;
    }

    /**
     * Преобразовать объекты в  JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
