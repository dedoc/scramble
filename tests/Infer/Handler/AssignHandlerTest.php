<?php

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
    $definition = analyzeFile(__FILE__)
        ->getClassDefinition(ObjectAssignment_AssignHandlerTest_Controller::class)
        ->getMethodDefinition('__invoke');

    expect($definition->getReturnType()->toString())->toBe('int(42)');
});
class ObjectAssignment_AssignHandlerTest_Controller
{
    public function __invoke()
    {
        $a = new stdClass;
        $a->foo = 42;

        return $a->foo;
    }
}

it('tracks multiple property assignments', function () {
    $definition = analyzeFile(__FILE__)
        ->getClassDefinition(MultiplePropertyAssignments_AssignHandlerTest_Controller::class)
        ->getMethodDefinition('__invoke');

    expect($definition->getReturnType()->toString())->toBe('int(42)');
});
class MultiplePropertyAssignments_AssignHandlerTest_Controller
{
    public function __invoke()
    {
        $a = new stdClass;
        $a->foo = 42;
        $a->bar = 'wow';

        return $a->foo;
    }
}

class PropertyTypesGeneric_AssignHandlerTest
{
    public mixed $foo;
}

it('tracks property types when assigning to a templated property', function () {
    $definition = analyzeFile(__FILE__)
        ->getClassDefinition(TemplatedPropertyAssignment_AssignHandlerTest_Controller::class)
        ->getMethodDefinition('__invoke');

    expect($definition->getReturnType()->toString())->toBe('int(42)')
        ->and(getVariableTypeAfter('$a = new PropertyTypesGeneric_AssignHandlerTest(); $a->foo = 42;', 'a')->toString())
        ->toBe(PropertyTypesGeneric_AssignHandlerTest::class.'<int(42)>');
});
class TemplatedPropertyAssignment_AssignHandlerTest_Controller
{
    public function __invoke()
    {
        $a = new PropertyTypesGeneric_AssignHandlerTest;
        $a->foo = 42;

        return $a->foo;
    }
}

class PropertyArrayGeneric_AssignHandlerTest
{
    public array $items;
}

it('infers template type from array property assignment', function () {
    $class = PropertyArrayGeneric_AssignHandlerTest::class;

    expect(getVariableTypeAfter("\$a = new {$class}(); \$a->items = [1, 2];", 'a')->toString())->toBe("{$class}<list{int(1), int(2)}>");
});

class MethodMutatingCall_AssignHandlerTest
{
    public $prop;

    public function setProp($a)
    {
        $this->prop = $a;

        return $this;
    }
}

it('tracks variable type after mutating method call on variable', function () {
    $class = MethodMutatingCall_AssignHandlerTest::class;

    expect(getVariableTypeAfter("\$a = new {$class}(); \$a->setProp(123);", 'a')->toString())
        ->toBe("{$class}<int(123)>");
});

it('tracks chained mutating method calls on variable', function () {
    $class = MethodMutatingCall_AssignHandlerTest::class;

    expect(getVariableTypeAfter("\$a = new {$class}(); \$a->setProp(123); \$a->setProp(456);", 'a')->toString())
        ->toBe("{$class}<int(456)>");
});

it('conditional assignments', function (string $code, string $expectedType) {
    expect(getVariableTypeAfter($code, 'a')->toString())->toBe($expectedType);
})->with([
    [
        <<<'PHP'
$a = 1;
if (mt_rand()) {
  $a = 42;
}
PHP,
        'int(42)|int(1)',
    ],
    [
        <<<'PHP'
$a = 1;
if (mt_rand()) {
  $a = 42;
} else {
  $a = 21;
}
PHP,
        'int(42)|int(21)',
    ],
    [
        <<<'PHP'
$a = [];
if (mt_rand()) {
  $a['foo'] = 42;
}
PHP,
        'array{foo?: int(42)}',
    ],
    [
        <<<'PHP'
$a = [];
if (mt_rand()) {
  $a['foo'] = 42;
}
if (mt_rand()) {
  $a['bar'] = 1;
}
PHP,
        'array{foo?: int(42), bar?: int(1)}',
    ],
    [
        <<<'PHP'
$a = [];
if (mt_rand()) {
  $a['foo'] = 42;
} else {
  $a['foo'] = 1;
}
PHP,
        'array{foo: int(42)|int(1)}',
    ],
    [
        <<<'PHP'
$a = ['bar' => 1];
unset($a['bar']);
if (mt_rand()) {
  $a['foo'] = 42;
}
PHP,
        'array{foo?: int(42)}',
    ],
])->todo();
