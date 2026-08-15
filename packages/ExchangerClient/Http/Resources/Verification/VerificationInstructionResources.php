<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Verification;

use Illuminate\Http\Resources\Json\ResourceCollection;

class VerificationInstructionResources extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function ($category) {
            return [
                'name' => $category->name,
                'details' => isset($category->instructions) ? $category->instructions->map(function ($instruction) {
                    return [
                        'id' => $instruction->id,
                        'name' => $instruction->name,
                        'text'  =>  $instruction->text,
                        'image' => sprintf('/storage/%s', $instruction->image),
                    ];
                }) : [],
            ];
        });
    }
}
