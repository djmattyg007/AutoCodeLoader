<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader;

class Autoloader
{
    /**
     * @var Autoloader
     */
    private static $autoloader = null;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * @param Generator $generator
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * @param string $className
     */
    public function loadClass(string $className)
    {
        if ($filename = $this->generator->checkClass($className)) {
            includeFile($filename);
            return true;
        }
    }

    /**
     * @param string $generationDir
     * @return Autoloader
     */
    public static function registerAutoloader(string $generationDir) : Autoloader
    {
        $generator = new Generator($generationDir);
        self::$autoloader = new static($generator);
        spl_autoload_register(array(self::$autoloader, "loadClass"));
        return self::$autoloader;
    }

    public static function unregisterAutoloader()
    {
        if (self::$autoloader !== null) {
            spl_autoload_unregister(array(self::$autoloader, "loadClass"));
        }
    }
}

/**
 * Scope isolated include.
 * Prevents access to $this/self from included files.
 * 
 * @param string $filename
 */
function includeFile(string $filename)
{
    include($filename);
}
