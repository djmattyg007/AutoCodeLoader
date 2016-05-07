<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader;

use Zend\Code\Generator\FileGenerator;

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
     * @param string $class
     * @return string|null Filename of generated file on success, null on failure
     */
    public function checkNeedsTrait(string $class)
    {
        if (!preg_match("/\\Needs([A-Za-z]+)Trait$/", $class, $matches)) {
            return null;
        }
        return null;
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
        $namespace = substr($className, 0, strrpos($className, "\\"));

        $klass = FileGenerator::fromReflectedFileName(__DIR__ . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "factory.php")->getClass("Factory");
        $klass->setNamespaceName($namespace)
            ->setName("{$baseName}Factory");
        $createMethod = $klass->getMethod("create");
        $createMethod->setBody('return $this->diContainer->newInstance(' . $baseName . '::class, $params);')
            ->setReturnType("{$namespace}\\{$baseName}");

        return $this->writeFile($className, $klass->generate());
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
