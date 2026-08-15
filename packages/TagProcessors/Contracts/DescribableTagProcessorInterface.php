<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors\Contracts;

/**
 * Процессор, который умеет отдавать описания своих тегов.
 */
interface DescribableTagProcessorInterface extends TagProcessorInterface
{
    /**
     * @return array<\iEXPackages\TagProcessors\Support\TagDefinition>
     */
    public function definitions(): array;
}
