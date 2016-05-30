<?php
declare(strict_types=1);

namespace MattyG\AutoCodeLoader\Generator;

use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\GeneratorInterface as ZendGeneratorInterface;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\TraitGenerator;

class NeedsTraitGenerator implements GeneratorInterface
{
    use GeneratorHelperTrait;

    const MATCH_PATTERN = "/\\Needs([A-Za-z]+)Trait$/";

    /**
     * @param string $className
     * @return TraitGenerator
     */
    public function handle(string $className) // PHP 7.1: : ?ZendGeneratorInterface
    {
        if (!preg_match(static::MATCH_PATTERN, $className, $matches)) {
            return null;
        }
        $baseName = $matches[1];
        $namespace = $this->deriveNamespaceFromClassName($className);
        $camelCaseBaseName = $this->stripInterfaceLabelFromName($this->makeClassNameCamelCase($baseName));

        $trait = new TraitGenerator("Needs{$baseName}Trait");
        $trait->setNamespaceName($namespace);

        $docBlock = new DocBlockGenerator(null, null, array(array("name" => "var", "content" => "\\{$namespace}\\{$baseName}")));
        $property = new PropertyGenerator($camelCaseBaseName, null, PropertyGenerator::FLAG_PROTECTED);
        $property->setDocBlock($docBlock);
        $trait->addProperties(array($property));

        $parameter = new ParameterGenerator($camelCaseBaseName, "{$namespace}\\{$baseName}");
        $method = new MethodGenerator(
            "set" . $this->stripInterfaceLabelFromName($baseName),
            array($parameter),
            MethodGenerator::FLAG_PUBLIC,
            sprintf('$this->%1$s = $%1$s;', $camelCaseBaseName)
        );
        $trait->addMethods(array($method));

        return $trait;
    }

    /**
     * Make a not-fully-qualified classname camelcase, accounting for multiple
     * capital letters at the start of the name, and assuming that it is provided
     * in Studly-caps form.
     *
     * @param string $className
     * @return string
     */
    protected function makeClassNameCamelCase(string $className) : string
    {
        $camelCaseClassName = $className;
        $classNameLen = strlen($className);
        // This accounts for multiple capital letters at the start of a trait name
        for ($x = 0; $x < $classNameLen; $x++) {
            $ord = ord($camelCaseClassName[$x]);
            if ($ord < 65 || $ord > 90) {
                break;
            }
            $camelCaseClassName[$x] = chr($ord + 32);
        }

        return $camelCaseClassName;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function stripInterfaceLabelFromName(string $name) : string
    {
        $ilen = strlen("Interface");
        $namelen = strlen($name);
        if ($ilen > $namelen) {
            return $name;
        }
        if (substr_compare($name, "Interface", $namelen - $ilen, $ilen) === 0) {
            return substr($name, 0, -$ilen);
        } else {
            return $name;
        }
    }
}
