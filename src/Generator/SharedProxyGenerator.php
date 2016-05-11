<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader\Generator;

use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\FileGenerator;

class SharedProxyGenerator extends AbstractProxyGenerator implements GeneratorInterface
{
    const MATCH_PATTERN = "/\\\\([A-Za-z]+)SharedProxy$/";

    /**
     * @return ClassGenerator
     */
    protected function getClassTemplate() : ClassGenerator
    {
        return FileGenerator::fromReflectedFileName(__DIR__ . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "shared_proxy.php")->getClass("SharedProxy");
    }
}
