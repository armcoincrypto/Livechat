<?php
namespace iEXPackages\TagProcessors\Contracts;

use Illuminate\Http\Request;

interface TagProcessorInterface
{
    public function tags(): array;

    public function handle(string $tag, mixed $data = [], ?Request $request = null): string;
}
