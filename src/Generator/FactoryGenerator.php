<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader\Generator;

use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\GeneratorInterface as ZendGeneratorInterface;

class FactoryGenerator implements GeneratorInterface
{
    use GeneratorHelperTrait;

    const MATCH_PATTERN = "/\\\\([A-Za-z]+)Factory$/";

    /**
     * @param string $className
     * @return \Zend\Code\Generator\ClassGenerator
     */
    public function handle(string $className) // PHP 7.1: : ?ZendGeneratorInterface
    {
        if (!preg_match(static::MATCH_PATTERN, $className, $matches)) {
            return null;
        }
        $baseName = $matches[1];
        $namespace = $this->deriveNamespaceFromClassName($className);

        $class = FileGenerator::fromReflectedFileName(__DIR__ . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "factory.php")->getClass("Factory");
        $class->setNamespaceName($namespace)
            ->setName("{$baseName}Factory");
        $createMethod = $class->getMethod("create");
        $createMethod->setBody('return $this->diContainer->newInstance(' . $baseName . '::class, $params);')
            ->setReturnType("{$namespace}\\{$baseName}");

        return $class;
    }
}
