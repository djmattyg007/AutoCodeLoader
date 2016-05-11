<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader;

use MattyG\AutoCodeLoader\Generator\FactoryGenerator;
use MattyG\AutoCodeLoader\Generator\NeedsTraitGenerator;
use MattyG\AutoCodeLoader\Generator\SharedProxyGenerator;

final class Generator
{
    const GEN_VERSION = "1-dev6";

    /**
     * @var string
     */
    private $generationDir;

    /**
     * @var NeedsTraitGenerator
     */
    private $needsTraitGenerator;

    /**
     * @var FactoryGenerator
     */
    private $factoryGenerator;

    /**
     * @var SharedProxyGenerator
     */
    private $sharedProxyGenerator;

    /**
     * @param string $generationDir
     * @param NeedsTraitGenerator|null $needsTraitGenerator
     * @param FactoryGenerator|null $factoryGenerator
     * @param SharedProxyGenerator|null $sharedProxyGenerator
     */
    public function __construct(
        string $generationDir,
        NeedsTraitGenerator $needsTraitGenerator = null,
        FactoryGenerator $factoryGenerator = null,
        SharedProxyGenerator $sharedProxyGenerator = null
    ) {
        if (!is_writeable($generationDir)) {
            throw new \InvalidArgumentException(sprintf("Cannot write to '%s'", $generationDir));
        }
        $this->generationDir = rtrim($generationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $this->needsTraitGenerator = $needsTraitGenerator ?: new NeedsTraitGenerator();
        $this->factoryGenerator = $factoryGenerator ?: new FactoryGenerator();
        $this->sharedProxyGenerator = $sharedProxyGenerator ?: new SharedProxyGenerator();
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
        $generatorOrder = array(
            $this->needsTraitGenerator,
            $this->factoryGenerator,
            $this->sharedProxyGenerator,
        );
        foreach ($generatorOrder as $generator) {
            if ($zendGenerator = $generator->handle($className)) {
                if ($filename = $this->writeFile($className, $zendGenerator->generate())) {
                    return $filename;
                }
            }
        }
        /*if ($filename = $this->checkNeedsTrait($className)) {
            return $filename;
        }*/
        /*if ($filename = $this->checkFactory($className)) {
            return $filename;
        }*/
        /*if ($filename = $this->checkSharedProxy($className)) {
            return $filename;
        }*/
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
            // Cache-bust
            @unlink($filename);
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
    private function checkProxy(string $className)
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
