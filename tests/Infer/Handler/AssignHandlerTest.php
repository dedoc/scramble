<?php

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

function getVariableTypeAfter(string $body, string $var): Type
{
    $index = app(Index::class);

    $traverser = new NodeTraverser;
    $traverser->addVisitor($nameResolver = new NameResolver);
    $traverser->addVisitor(new PhpDocResolver(
        $nameResolver = new FileNameResolver($nameResolver->getNameContext()),
    ));
    $traverser->addVisitor(new TypeInferer(
        $index,
        $nameResolver,
        $scope = new Scope($index, new NodeTypesResolver, new ScopeContext, $nameResolver),
        Context::getInstance()->extensionsBroker->extensions,
    ));
    $traverser->traverse(
        FileParser::getInstance()->parseContent("<?php\n{$body}")->getStatements(),
    );

    $unresolvedType = $scope->getType(
        new Node\Expr\Variable($var, ['startLine' => INF]),
    );

    return (new ReferenceTypeResolver($index))->resolve($scope, $unresolvedType)->setOriginal($unresolvedType);
}

it('infers types from list destructuring assignment', function () {
    expect(getVariableTypeAfter('[$a, $b] = [1, 2];', 'a')->toString())->toBe('int(1)')
        ->and(getVariableTypeAfter('[$a, $b] = [1, 2];', 'b')->toString())->toBe('int(2)');
});

it('infers types from keyed destructuring assignment', function () {
    expect(getVariableTypeAfter("['a' => \$a, 'b' => \$b] = ['b' => 1, 'a' => 2];", 'a')->toString())->toBe('int(2)')
        ->and(getVariableTypeAfter("['a' => \$a, 'b' => \$b] = ['b' => 1, 'a' => 2];", 'b')->toString())->toBe('int(1)');
});

it('infers types from list() destructuring assignment', function () {
    expect(getVariableTypeAfter('list($a, $b) = [1, 2];', 'a')->toString())->toBe('int(1)')
        ->and(getVariableTypeAfter('list($a, $b) = [1, 2];', 'b')->toString())->toBe('int(2)');
});

it('infers types from nested destructuring assignment', function () {
    expect(getVariableTypeAfter('[[$a, $b], $c] = [[1, 2], 3];', 'a')->toString())->toBe('int(1)')
        ->and(getVariableTypeAfter('[[$a, $b], $c] = [[1, 2], 3];', 'b')->toString())->toBe('int(2)')
        ->and(getVariableTypeAfter('[[$a, $b], $c] = [[1, 2], 3];', 'c')->toString())->toBe('int(3)');
});

it('infers types from destructuring assignment with skipped slot', function () {
    expect(getVariableTypeAfter('[,$b] = [1, 2];', 'b')->toString())->toBe('int(2)');
});

it('tracks property types on object assignment', function () {
    $a = new stdClass;
    $a->foo = 42;

    expect($a->foo)->toHaveType('int(42)');
});

it('tracks multiple property assignments', function () {
    $a = new stdClass;
    $a->foo = 42;
    $a->bar = 'wow';

    expect($a->foo)->toHaveType('int(42)');
});

class PropertyTypesGeneric_AssignHandlerTest
{
    public mixed $foo;
}

it('tracks property types when assigning to a templated property', function () {
    $a = new PropertyTypesGeneric_AssignHandlerTest;
    $a->foo = 42;

    expect($a->foo)->toHaveType('int(42)');

    expect(getVariableTypeAfter('$a = new PropertyTypesGeneric_AssignHandlerTest(); $a->foo = 42;', 'a')->toString())
        ->toBe(PropertyTypesGeneric_AssignHandlerTest::class.'<int(42)>');
});

class PropertyArrayGeneric_AssignHandlerTest
{
    public array $items;
}

it('infers template type from array property assignment', function () {
    $class = PropertyArrayGeneric_AssignHandlerTest::class;

    expect(getVariableTypeAfter("\$a = new {$class}(); \$a->items = [1, 2];", 'a')->toString())->toBe("{$class}<list{int(1), int(2)}>");
});
