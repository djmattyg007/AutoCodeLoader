#!/usr/bin/env php
<?php
declare(strict_types=1);

if (is_readable(dirname(__DIR__) . DIRECTORY_SEPARATOR . "autoload.php")) {
    require(dirname(__DIR__) . DIRECTORY_SEPARATOR . "autoload.php");
} elseif (is_readable(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . "autoload.php")) {
    require(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . "autoload.php");
} elseif (is_readable(dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php")) {
    require(dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php");
} else {
    throw new \RuntimeException("Cannot load composer autoloader.");
}

if (empty($argv[2])) {
    throw new \RuntimeException("You must specify a directory to store the generated files in, and at least one directory to scan for files to be generated.");
}
$autocoder = MattyG\AutoCodeLoader\Autoloader::registerAutoloader($argv[1]);
$scanner = new MattyG\AutoCodeLoader\Scanner();

foreach (array_slice($argv, 2) as $dir) {
    $dirScanner = new Zend\Code\Scanner\DirectoryScanner($dir);
    $scanner->findAllAndGenerate($dirScanner);
}
