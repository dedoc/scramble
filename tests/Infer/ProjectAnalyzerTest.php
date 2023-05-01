<?php

use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Services\FileParser;
use PhpParser\ParserFactory;

beforeEach(function () {
    $this->projectAnalyzer = new ProjectAnalyzer(
        parser: new FileParser((new ParserFactory)->create(ParserFactory::PREFER_PHP7))
    );
});

it('adds a real file', function () {
    $this->projectAnalyzer->addFile($path = __DIR__.'/files/project_analyzer_symbols_test.php');

    expect($this->projectAnalyzer->files())->toHaveKey($path);
});

it('adds a virtual file', function () {
    $this->projectAnalyzer->addFile('file.php', '<?php $a = 1;');

    expect($this->projectAnalyzer->files())->toHaveKey('file.php');
});

it('creates symbols map to files', function () {
    $this->projectAnalyzer->addFile($path = __DIR__.'/files/project_analyzer_symbols_test.php');

    expect($this->projectAnalyzer->symbols['function'])
        ->toMatchArray(['foo' => $path])
        ->and($this->projectAnalyzer->symbols['class'])
        ->toMatchArray(['Bar' => $path]);
});

it('creates ordered symbols queue to analyze', function () {
    $this->projectAnalyzer->addFile(__DIR__.'/files/project_analyzer_symbols_test.php');

    expect($this->projectAnalyzer->queue)
        ->toMatchArray([
            ['function', 'foo'],
            ['class', 'Bar'],
        ]);
});

it('adds analyzed symbols from a file to the global index', function () {
    $this->projectAnalyzer
        ->addFile(__DIR__.'/files/project_analyzer_symbols_test.php')
        ->analyze();

    expect($this->projectAnalyzer->index->functionsDefinitions)
        ->toHaveKey('foo')
        ->and($this->projectAnalyzer->index->classesDefinitions)
        ->toHaveKey('Bar');
});

it('analyzes parent class before analyzing a child', function () {
    $this->projectAnalyzer
        ->addFile(__DIR__.'/files/project_analyzer_inheritance_test.php')
        ->analyze();

    expect($this->projectAnalyzer->index->classesDefinitions)
        ->toHaveKey('App\Foo')
        ->and(($foo = $this->projectAnalyzer->index->getClassDefinition('App\Foo'))->templateTypes)
        ->toHaveCount(2)
        ->and($foo->methods['__construct']->type->toString())
        ->toBe('(TFooProp, TBarProp): void');
});
