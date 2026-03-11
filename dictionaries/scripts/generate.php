<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileParser;
use PhpParser\ParserFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

include 'vendor/autoload.php';

$classes = [
    Exception::class,
    RuntimeException::class,
    HttpException::class,
];

app()->singleton(FileParser::class, function () {
    return new FileParser(
        (new ParserFactory)->createForHostVersion()
    );
});
app()->singleton(Index::class);

$classesDefinitions = [];
foreach ($classes as $className) {
    $classesDefinitions[$className] = generateClassDefinitionInitialization($className);
}

function generateClassDefinitionInitialization(string $name)
{
    $classAnalyzer = app(ClassAnalyzer::class);

    $classDefinition = $classAnalyzer->analyze($name);
    foreach ($classDefinition->methods as $methodName => $method) {
        $classDefinition->getMethodDefinition($methodName);
    }

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
