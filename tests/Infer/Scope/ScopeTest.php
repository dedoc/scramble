<?php

use Dedoc\Scramble\Infer\Flow\Node;
use Dedoc\Scramble\Infer\Flow\TerminateNode;
use Dedoc\Scramble\Infer\Flow\TerminationKind;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Type;

function getStatementTypeForScopeTest(string $statement, array $extensions = [])
{
    return analyzeFile('<?php', $extensions)->getExpressionType($statement);
}

it('infers property fetch nodes types', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['$foo->bar', 'unknown'],
    ['$foo->bar->{"baz"}', 'unknown'],
]);

it('infers ternary expressions nodes types', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['unknown() ? 1 : null', 'int(1)|null'],
    ['unknown() ? 1 : 1', 'int(1)'],
    ['unknown() ?: 1', 'unknown|int(1)'],
    ['(int) unknown() ?: "w"', 'int|string(w)'],
    ['1 ?: 1', 'int(1)'],
    ['unknown() ? 1 : unknown()', 'int(1)|unknown'],
    ['unknown() ? unknown() : unknown()', 'unknown'],
    ['unknown() ?: unknown()', 'unknown'],
    ['unknown() ?: true ?: 1', 'unknown|boolean(true)|int(1)'],
    ['unknown() ?: unknown() ?: unknown()', 'unknown'],
]);

it('infers expressions from a null coalescing operator', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['unknown() ?? 1', 'unknown|int(1)'],
    ['(int) unknown() ?? "w"', 'int|string(w)'],
    ['1 ?? 1', 'int(1)'],
    ['unknown() ?? unknown()', 'unknown'],
    ['unknown() ?? true ?? 1', 'unknown|boolean(true)|int(1)'],
    ['unknown() ?? unknown() ?? unknown()', 'unknown'],
]);

it('infers match node type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    [<<<'EOD'
match (unknown()) {
    42 => 1,
    default => null,
}
EOD, 'int(1)|null'],
]);

it('infers throw node type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['throw new Exception("foo")', 'void'],
]);

it('infers var var type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['$$a', 'unknown'],
]);

it('infers array type with const fetch keys', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['['.Foo_ScopeTest::class.'::FOO => 42]', 'array{foo: int(42)}'],
]);
class Foo_ScopeTest
{
    const FOO = 'foo';
}

it('builds simplest control flow graph', function () {
    $code = <<<'EOF'
<?php
function foo () {
    $a = 1;
    return 42;
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($nodes = $flow->nodes)->toHaveCount(3) // start -> expr -> terminate
        ->and($nodes[2])->toBeInstanceOf(TerminateNode::class);
});

it('builds simplest control flow graph without connecting returns', function () {
    $code = <<<'EOF'
<?php
function foo () {
    return 1;
    return 42;
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> Ret_1; S_0; Ret_1[label="Return 1"]; Ret_2[label="Return 42"]; }');

    $reachableReturns = $flow->getReachableNodes(fn (Node $n) => $n instanceof TerminateNode && $n->kind === TerminationKind::RETURN);

    expect($nodes = $flow->nodes)->toHaveCount(3) // start -> terminate terminate
        ->and($reachableReturns)->toHaveCount(1); // return 1
});

it('builds simplest if flow graph with connecting merge node', function () {
    $code = <<<'EOF'
<?php
function foo () {
    if ($a === 0) {
        $b = 1;
    } elseif ($a === 42) {
        $b = 18;
    } else {
        $b = 2;
    }
    return 0;
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> If_1; If_1 -> Stmt_2 [label="$a === 0"]; If_1 -> Stmt_3 [label="$a === 42"]; If_1 -> Stmt_4 [label="!($a === 0 AND $a === 42)"]; Stmt_4 -> M_5; Stmt_3 -> M_5; Stmt_2 -> M_5; M_5 -> Ret_6; S_0; If_1[label="If"]; Stmt_2[label="$b = 1;"]; Stmt_3[label="$b = 18;"]; Stmt_4[label="$b = 2;"]; M_5; Ret_6[label="Return 0"]; }');
});

it('builds if flow graph with implicitly connecting merge node without else', function () {
    $code = <<<'EOF'
<?php
function foo () {
    if ($a === 0) {
        return 1;
    }
    $b = 13;
    return 0;
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> If_1; If_1 -> Ret_2 [label="$a === 0"]; If_1 -> M_3 [label="!($a === 0)"]; M_3 -> Stmt_4; Stmt_4 -> Ret_5; S_0; If_1[label="If"]; Ret_2[label="Return 1"]; M_3; Stmt_4[label="$b = 13;"]; Ret_5[label="Return 0"]; }');
});

it('builds simplest if flow graph with connecting merge node and nested ifs', function () {
    $code = <<<'EOF'
<?php
function foo () {
    if ($a === 0) {
        if ($d === 1) {
            $m = 1;
        } else {
            $m = 2;
        }
    }
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> If_1; If_1 -> If_2 [label="$a === 0"]; If_2 -> Stmt_3 [label="$d === 1"]; If_2 -> Stmt_4 [label="!($d === 1)"]; Stmt_4 -> M_5; Stmt_3 -> M_5; M_5 -> M_6; If_1 -> M_6 [label="!($a === 0)"]; S_0; If_1[label="If"]; If_2[label="If"]; Stmt_3[label="$m = 1;"]; Stmt_4[label="$m = 2;"]; M_5; M_6; }');
});

it('builds simplest control flow graph with branching', function () {
    $code = <<<'EOF'
<?php
function foo () {
    if ($a === 0) {
        return 42;
    } else {
        return 1;
    }
    return 0;
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> If_1; If_1 -> Ret_2 [label="$a === 0"]; If_1 -> Ret_3 [label="!($a === 0)"]; S_0; If_1[label="If"]; Ret_2[label="Return 42"]; Ret_3[label="Return 1"]; Ret_4[label="Return 0"]; }');
});

it('builds simplest control flow graph with branching and handling reachable nodes', function () {
    $code = <<<'EOF'
<?php
function foo () {
    if ($a === 0) {
        return 42;
    } else {
        return 1;
    }
    $a = 1;
    $b = 42;
    return 0;
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    $returns = $flow
        ->getReachableNodes(fn (Node $n) => $n instanceof TerminateNode && $n->kind === TerminationKind::RETURN);

    expect($returns)->toHaveCount(2);
});

it('builds simplest control flow graph with branching all returns', function () {
    $code = <<<'EOF'
<?php
function foo () {
    if ($a === 0) {
        return 42;
    } else {
        return 1;
    }
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> If_1; If_1 -> Ret_2 [label="$a === 0"]; If_1 -> Ret_3 [label="!($a === 0)"]; S_0; If_1[label="If"]; Ret_2[label="Return 42"]; Ret_3[label="Return 1"]; }');
});

it('builds control flow graph with terminated match', function () {
    $code = <<<'EOF'
<?php
function foo () {
    return match ($a) {
         'foo' => 1,
         'bar' => 42,
         default => null,
     };
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> If_1; If_1 -> Ret_2 [label="$a === \'foo\'"]; If_1 -> Ret_3 [label="$a === \'bar\'"]; If_1 -> Ret_4 [label="!($a === \'foo\' AND $a === \'bar\')"]; S_0; If_1[label="If"]; Ret_2[label="Return 1"]; Ret_3[label="Return 42"]; Ret_4[label="Return \\null"]; }');
});

it('builds control flow graph with match assigned to var', function () {
    $code = <<<'EOF'
<?php
function foo () {
    $b = match ($a) {
        'foo' => 1,
        'bar' => 42,
        default => null,
    };
    return $b;
}
EOF;

    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    expect($flow->toDot())->toBe('digraph Flow { S_0 -> If_1; If_1 -> Stmt_2 [label="$a === \'foo\'"]; If_1 -> Stmt_3 [label="$a === \'bar\'"]; If_1 -> Stmt_4 [label="!($a === \'foo\' AND $a === \'bar\')"]; Stmt_4 -> M_5; Stmt_3 -> M_5; Stmt_2 -> M_5; M_5 -> Ret_6; S_0; If_1[label="If"]; Stmt_2[label="$b = 1;"]; Stmt_3[label="$b = 42;"]; Stmt_4[label="$b = \null;"]; M_5; Ret_6[label="Return $b"]; }');
});

/**
 * Imagine a function:
 * function foo ($a) {
 *     return match ($a) {
 *         'foo' => 1,
 *         'bar' => 42,
 *         default => null,
 *     }
 * }
 * The return type of the function is 1|42|null.
 *
 * The test here is testing the part of the functionality that allows to know that when
 * return type is specifically 42, `$a` variable must have 'bar' type.
 */
it('allows inspecting known things about variables based on returned type', function () {
    $code = <<<'EOF'
<?php
function foo ($a) {
     return match ($a) {
         'foo' => 1,
         'bar' => 42,
         default => null,
     };
}
EOF;
    /** @var \Dedoc\Scramble\Infer\Flow\Nodes $flow */
    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    $originNodes = $flow->findValueOriginsByExitType(fn (Type $t) => $t instanceof LiteralIntegerType && $t->value === 42);

    $type = $flow->getTypeAt(new \PhpParser\Node\Expr\Variable('a'), $originNodes[0]);

    expect($type->toString())->toBe('string(bar)');
});

it('allows inspecting known facts about variables based on if', function () {
    $code = <<<'EOF'
<?php
function foo ($a) {
    if ($a === 'foo') {
        return 1;
    }

    if ($a === 'bar') {
        return 42;
    }

    return null;
}
EOF;

    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    $originNodes = $flow->findValueOriginsByExitType(fn (Type $t) => $t instanceof LiteralIntegerType && $t->value === 42);

    $type = $flow->getTypeAt(new \PhpParser\Node\Expr\Variable('a'), $originNodes[0]);

    expect($type->toString())->toBe('string(bar)');
});

it('allows inspecting known facts about variables based on if with recursion guard', function () {
    $code = <<<'EOF'
<?php
function foo($a) {
    if ($a === $a) {
        $a = $a;
    }
    return $a;
}
EOF;

    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    $returnNodes = $flow->getReachableNodes(fn (Node $n) => $n instanceof TerminateNode && $n->kind === TerminationKind::RETURN);

    $type = $flow->getTypeAt(new \PhpParser\Node\Expr\Variable('a'), $returnNodes[0]);

    expect($type->toString())->toBe('unknown');
});

it('allows inspecting known facts about variables based on if with else consideration guard', function () {
    $code = <<<'EOF'
<?php
function foo() {
    $a = 42;
    if ($a === 0) {
        $b = 1;
    }
    return $a;
}
EOF;

    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    $returnNodes = $flow->getReachableNodes(fn (Node $n) => $n instanceof TerminateNode && $n->kind === TerminationKind::RETURN);

    $type = $flow->getTypeAt(new \PhpParser\Node\Expr\Variable('a'), $returnNodes[0]);

    expect($type->toString())->toBe('int(42)');
});

it('allows inspecting known facts about variables based on if with recursion guard second', function () {
    $code = <<<'EOF'
<?php
function foo($a) {
    if ($a === 0) {
        // no assignment
    } else {
        // no assignment
    }

    return $a;
}
EOF;

    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    $returnNodes = $flow->getReachableNodes(fn (Node $n) => $n instanceof TerminateNode && $n->kind === TerminationKind::RETURN);

    $type = $flow->getTypeAt(new \PhpParser\Node\Expr\Variable('a'), $returnNodes[0]);

    expect($type->toString())->toBe('unknown');
});

it('allows inspecting known facts about variables keys', function () {
    $code = <<<'EOF'
<?php
function foo($a) {
    if ($a['foo'] === 42) {
        return 1;
    }

    return 0;
}
EOF;

    $flow = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getFlowContainer();

    $returnNodes = $flow->getReachableNodes(fn (Node $n) => $n instanceof TerminateNode && $n->kind === TerminationKind::RETURN);

    $type = $flow->getTypeAt(new \PhpParser\Node\Expr\ArrayDimFetch(
        new \PhpParser\Node\Expr\Variable('a'),
        new \PhpParser\Node\Scalar\String_('foo')
    ), $returnNodes[0]);

    expect($type->toString())->toBe('array{foo: int(42)}[string(foo)]');
});
