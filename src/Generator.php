<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader;

use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\TraitGenerator;

class Generator
{
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
        if (is_readable($filename)) {
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

        $property = new PropertyGenerator($camelCaseBaseName, null, PropertyGenerator::FLAG_PROTECTED);
        $trait->addProperties(array($property));

        $parameter = new ParameterGenerator($camelCaseBaseName, "{$namespace}\\{$baseName}");
        $method = new MethodGenerator(
            "set{$baseName}",
            array($parameter),
            MethodGenerator::FLAG_PUBLIC,
            sprintf('$this->%1$s = $%1$s;', $camelCaseBaseName)
        );
        $trait->addMethods(array($method));

        $return = $this->writeFile($className, $trait->generate());

        if ($return) {
            $this->checkFactory("{$namespace}\\{$baseName}");
            $this->checkProxy("{$namespace}\\{$baseName}");
        }

        return $return;
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
    public function checkProxy(string $class)
    {
        if (!preg_match("/\\[A-Za-z]+Proxy$/", $class)) {
            return null;
        }
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

        $fullFileContents = '<?php' . "\n" . 'declare(strict_types=1);' . "\n" . $fileContents;
        $filePutCheck = file_put_contents($filename, $fullFileContents);
        if ($filePutCheck) {
            return $filename;
        } else {
            return null;
        }
    }
}
