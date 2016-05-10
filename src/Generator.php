<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\TraitGenerator;
use Zend\Code\Generator\ValueGenerator;

class Generator
{
    const GEN_VERSION = "1-dev4";

    /**
     * @var string
     */
    private $generationDir;

    /**
     * @param string $generationDir
     */
    public function __construct(string $generationDir)
    {
        if (!is_writeable($generationDir)) {
            throw new \InvalidArgumentException(sprintf("Cannot write to '%s'", $generationDir));
        }
        $this->generationDir = rtrim($generationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $className
     * @return string
     */
    private function deriveFileNameFromClassName(string $className) : string
    {
        return $this->generationDir . str_replace("\\", DIRECTORY_SEPARATOR, $className) . ".php";
    }

    /**
     * @param string $className
     * @return string
     */
    private function deriveNamespaceFromClassName(string $className) : string
    {
        return substr($className, 0, strrpos($className, "\\"));
    }

    /**
     * @param string $className
     * @return string|null Filename of generated file on success, null on failure
     */
    public function checkClass(string $className)
    {
        $className = ltrim($className, "\\");
        if ($filename = $this->checkCache($className)) {
            return $filename;
        }
        if ($filename = $this->checkNeedsTrait($className)) {
            return $filename;
        }
        if ($filename = $this->checkFactory($className)) {
            return $filename;
        }
        if ($filename = $this->checkSharedProxy($className)) {
            return $filename;
        }
        if ($filename = $this->checkProxy($className)) {
            return $filename;
        }
        return null;
    }

    /**
     * @param string $className
     * @return string|null Filename of generated file if it exists and is readable, null if it doesn't
     */
    private function checkCache(string $className)
    {
        $filename = $this->deriveFileNameFromClassName($className);
        if (is_readable($filename) === false) {
            return null;
        }
        $firstline = fgets(fopen($filename, "r"));
        if (!preg_match("#// GEN_VERSION = (.+)$#", $firstline, $matches)) {
            return null;
        }
        if ($matches[1] === self::GEN_VERSION) {
            return $filename;
        } else {
            return null;
        }
    }

    /**
     * @param string $className
     * @return string|null Filename of generated file on success, null on failure
     */
    public function checkNeedsTrait(string $className)
    {
        if (!preg_match("/\\Needs([A-Za-z]+)Trait$/", $className, $matches)) {
            return null;
        }
        $baseName = $matches[1];
        $namespace = $this->deriveNamespaceFromClassName($className);
        $camelCaseBaseName = $baseName;
        $baseNameLen = strlen($baseName);
        // This accounts for multiple capital letters at the start of a trait name
        for ($x = 0; $x < $baseNameLen; $x++) {
            $ord = ord($camelCaseBaseName[$x]);
            if ($ord < 65 || $ord > 90) {
                break;
            }
            $camelCaseBaseName[$x] = chr($ord + 32);
        }

        $trait = new TraitGenerator("Needs{$baseName}Trait");
        $trait->setNamespaceName($namespace);

        $docBlock = new DocBlockGenerator(null, null, array(array("name" => "var", "content" => "\\{$namespace}\\{$baseName}")));
        $property = new PropertyGenerator($camelCaseBaseName, null, PropertyGenerator::FLAG_PROTECTED);
        $property->setDocBlock($docBlock);
        $trait->addProperties(array($property));

        $parameter = new ParameterGenerator($camelCaseBaseName, "{$namespace}\\{$baseName}");
        $method = new MethodGenerator(
            "set{$baseName}",
            array($parameter),
            MethodGenerator::FLAG_PUBLIC,
            sprintf('$this->%1$s = $%1$s;', $camelCaseBaseName)
        );
        $trait->addMethods(array($method));

        return $this->writeFile($className, $trait->generate());
    }

    /**
     * @param string $className
     * @return string|null Filename of generated file on success, null on failure
     */
    public function checkFactory(string $className)
    {
        if (!preg_match("/\\\\([A-Za-z]+)Factory$/", $className, $matches)) {
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

        return $this->writeFile($className, $class->generate());
    }

    /**
     * @param string $className
     * @return string|null Filename of generated file on success, null on failure
     */
    public function checkSharedProxy(string $className)
    {
        if (!preg_match("/\\\\([A-Za-z]+)SharedProxy$/", $className, $matches)) {
            return null;
        }
        $baseName = $matches[1];
        $namespace = $this->deriveNamespaceFromClassName($className);
        $typeName = "{$namespace}\\{$baseName}";

        $reflectedClass = new ReflectionClass($typeName);
        if ($reflectedClass->isTrait() === true || $reflectedClass->isAbstract() === true) {
            return null;
        }

        $class = FileGenerator::fromReflectedFileName(__DIR__ . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "shared_proxy.php")->getClass("SharedProxy");
        $class->setNamespaceName($namespace)
            ->setName("{$baseName}SharedProxy");

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

        return $this->writeFile($className, $class->generate());
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

    private function getMethodParamDetails(ReflectionParameter $param)
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

    /**
     * @param string $className
     * @return string|null Filename of generated file on success, null on failure
     */
    public function checkProxy(string $className)
    {
        if (!preg_match("/\\\\([A-Za-z]+)Proxy$/", $className, $matches)) {
            return null;
        }
        $baseName = $matches[1];
        $namespace = $this->deriveNamespaceFromClassName($className);

        return null;
    }

    /**
     * @param string $className
     * @param string $fileContents
     * @return string|null Filename of generated file on success, null on failure
     */
    private function writeFile(string $className, string $fileContents)
    {
        $filename = $this->deriveFileNameFromClassName($className);
        $directory = dirname($filename);
        if (is_dir($directory) === false) {
            $mkdirCheck = @mkdir($directory, 0777, true);
            if ($mkdirCheck !== true) {
                return null;
            }
        }

        $fullFileContents = '<?php // GEN_VERSION = ' . self::GEN_VERSION . "\n" . 'declare(strict_types=1);' . "\n" . $fileContents;
        $filePutCheck = file_put_contents($filename, $fullFileContents);
        if ($filePutCheck) {
            return $filename;
        } else {
            return null;
        }
    }
}
