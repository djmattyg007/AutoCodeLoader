<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader\Generator;

use Zend\Code\Generator\GeneratorInterface as ZendGeneratorInterface;

interface GeneratorInterface
{
    /**
     * @param string $className
     */
    public function handle(string $className); // PHP 7.1: : ?ZendGeneratorInterface
}
