<?php

namespace iEXPackages\Courses\Services\DefaultSources;

interface DefaultParserInterface
{
    public function getUrl(array $options = []): string;
    public function getParams(array $options = []): array;
    public function parseResponse(string $response): array;
}
