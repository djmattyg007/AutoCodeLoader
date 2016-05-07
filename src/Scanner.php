<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader;

use Zend\Code\Scanner\DirectoryScanner;

class Scanner
{
    /**
     * @param DirectoryScanner $dirScanner
     */
    public function findAllAndGenerate(DirectoryScanner $dirScanner)
    {
        $foundClasses = $this->findClasses($dirScanner);
        $this->checkAllClasses($foundClasses);
    }

    /**
     * @param DirectoryScanner $dirScanner
     * @return array
     */
    private function findClasses(DirectoryScanner $dirScanner) : array
    {
        $classes = $dirScanner->getClasses();
        $foundClasses = array();
        foreach ($classes as $klass) {
            $constructor = $klass->getMethod("__construct");
            if (!$constructor) {
                continue;
            }
            $parameters = $constructor->getParameters(true);
            $foundClasses = array_merge($foundClasses, $this->findClassesFromParams($parameters));
        }
        return array_unique($foundClasses);
    }

    /**
     * @param array $params
     * @return array
     */
    private function findClassesFromParams(array $params) : array
    {
        $classNames = array();
        foreach ($params as $param) {
            $paramClass = $param->getClass();
            if (is_string($paramClass) === false) {
                continue;
            }
            // Just in case
            $paramClass = ltrim($paramClass, "\\");

            // We need to check for scalar type hints, as Zend\Code doesn't currently do this.
            if (($lastNsSep = strrpos($paramClass, "\\")) !== false) {
                $className = strtolower(substr($paramClass, $lastNsSep + 1));
            } else {
                $className = strtolower($paramClass);
            }
            if (in_array($className, array("string", "int", "float", "bool"), true)) {
                continue;
            }
            $classNames[] = $paramClass;
        }
        return $classNames;
    }

    /**
     * @param array $classNames
     */
    private function checkAllClasses(array $classNames)
    {
        foreach ($classNames as $className) {
            class_exists($className);
        }
    }
}
