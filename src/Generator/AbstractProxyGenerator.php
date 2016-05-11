<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader\Generator;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\GeneratorInterface as ZendGeneratorInterface;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ValueGenerator;

abstract class AbstractProxyGenerator implements GeneratorInterface
{
    use GeneratorHelperTrait;

    /**
     * @return ClassGenerator
     */
    abstract protected function getClassTemplate() : ClassGenerator;

    /**
     * @param string $className
     * @return ClassGenerator
     */
    public function handle(string $className) // PHP 7.1: : ?ZendGeneratorInterface
    {
        if (!preg_match(static::MATCH_PATTERN, $className, $matches)) {
            return null;
        }
        $baseName = $matches[1];
        $namespace = $this->deriveNamespaceFromClassName($className);
        $typeName = "{$namespace}\\{$baseName}";

        $reflectedClass = new ReflectionClass($typeName);
        if ($reflectedClass->isTrait() === true || $reflectedClass->isAbstract() === true) {
            return null;
        }

        $class = $this->getClassTemplate();
        $class->setNamespaceName($namespace)
            ->setName($baseName . $class->getName());

        $propertyDocBlock = new DocBlockGenerator(null, null, array(array("name" => "var", "content" => "\\{$typeName}")));
        $class->getProperty("_instance")->setDocBlock($propertyDocBlock);

        $methodDocBlock = new DocBlockGenerator();
        $returnTag = new ReturnTag("\\{$typeName}");
        $methodDocBlock->setTag($returnTag);
        $instanceMethod = $class->getMethod("_getInstance");
        $instanceMethod->setDocBlock($methodDocBlock)
            ->setReturnType($typeName);

        if ($reflectedClass->isInterface() === true) {
            $class->setImplementedInterfaces(array("\\{$typeName}"));
        } else {
            $class->setExtendedClass("\\{$typeName}");
        }

        $publicMethods = $reflectedClass->getMethods(ReflectionMethod::IS_PUBLIC);
        $addMethods = array();
        foreach ($publicMethods as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isFinal() || $method->isStatic()) {
                continue;
            }
            if ($method->getName() === "__clone") {
                continue;
            }
            $addMethods[] = $this->getMethodDetails($method);
        }
        $class->addMethods($addMethods);

        return $class;
    }

    /**
     * @param ReflectionMethod $method
     * @return MethodGenerator
     */
    private function getMethodDetails(ReflectionMethod $method) : MethodGenerator
    {
        $name = $method->getName();
        $methodDetails = array(
            "name" => $name,
            "docblock" => array("shortDescription" => '{@inheritdoc}'),
        );

        $paramNames = array();
        $params = array();

        foreach ($method->getParameters() as $param) {
            $params[] = $this->getMethodParamDetails($param);
            $paramNames[] = '$' . $param->getName();
        }
        $methodDetails["parameters"] = $params;

        if (count($params) === 0) {
            $bodyMethodCall = sprintf('%s()', $name);
        } else {
            $bodyMethodCall = sprintf('%1$s(%2$s)', $name, implode(', ', $paramNames));
        }
        $methodDetails["body"] = sprintf('return $this->_getInstance()->%s;', $bodyMethodCall);

        $newMethod = MethodGenerator::fromArray($methodDetails);

        if ($method->hasReturnType()) {
            $returnType = $method->getReturnType();
            if ($returnType === null) {
                $newMethod->setReturnType(new ValueGenerator(null, ValueGenerator::TYPE_NULL));
            } else {
                $newMethod->setReturnType((string) $returnType);
            }
        }

        return $newMethod;
    }

    /**
     * @param ReflectionParameter $param
     * @return array
     */
    private function getMethodParamDetails(ReflectionParameter $param) : array
    {
        $paramDetails = array(
            "name" => $param->getName(),
            "passedByReference" => $param->isPassedByReference(),
        );
        if ($param->hasType()) {
            $paramDetails["type"] = (string) $param->getType();
        }

        if ($param->isOptional() && $param->isDefaultValueAvailable()) {
            $defaultValue = $param->getDefaultValue();
            if ($defaultValue === null) {
                $paramDetails["defaultValue"] = new ValueGenerator(null, ValueGenerator::TYPE_NULL);
            } else {
                $paramDetails["defaultValue"] = $defaultValue;
            }
        }

        return $paramDetails;
    }
}
