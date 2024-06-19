<?php

include 'vendor/autoload.php';

$classes = [
    Exception::class,
    RuntimeException::class,
    \Symfony\Component\HttpKernel\Exception\HttpException::class,
];

$classesDefinitions = [];
foreach ($classes as $className) {
    $classesDefinitions[$className] = generateClassDefinitionInitialization($className);
}

function generateClassDefinitionInitialization(string $name)
{
    $classAnalyzer = new \Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer(
        new \Dedoc\Scramble\Infer\Scope\Index(),
    );

    return serialize($classAnalyzer->analyze($name));
}

$def = var_export($classesDefinitions, true);
file_put_contents(__DIR__.'/../classMap.php', <<<EOL
<?php
/*
 * Do not change! This file is generated via scripts/generate.php.
 */
return {$def};
EOL);
