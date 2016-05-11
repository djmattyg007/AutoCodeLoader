<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader\Generator;

trait GeneratorHelperTrait
{
    /**
     * @param string $className
     * @return string
     */
    protected function deriveNamespaceFromClassName(string $className) : string
    {
        return substr($className, 0, strrpos($className, "\\"));
    }
}
