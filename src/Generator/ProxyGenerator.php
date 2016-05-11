<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader\Generator;

use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\FileGenerator;

class ProxyGenerator extends AbstractProxyGenerator implements GeneratorInterface
{
    const MATCH_PATTERN = "/\\\\([A-Za-z]+)Proxy$/";

    /**
     * @return ClassGenerator
     */
    protected function getClassTemplate() : ClassGenerator
    {
        return FileGenerator::fromReflectedFileName(__DIR__ . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "proxy.php")->getClass("Proxy");
    }

    /**
     * Method for subclasses to override to perform any implementation-specific modifications.
     *
     * @param ClassGenerator $class
     * @param string $origTypeName
     */
    protected function makeExtraModifications(ClassGenerator $class, string $origTypeName)
    {
        $class->addConstant("_INSTANCE_NAME", "\\{$origTypeName}");
    }
}
