<?php

namespace Dedoc\Scramble\Tests\Infer\Services;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\Services\TemplateTypesSolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;

it('adds context type to callable arguments if needed for primitive parameters (foo_TemplateTypesSolverTest)', function (string $expression, string $expectedType) {
    $argumentType = (new TemplateTypesSolver)->addContextTypesToTypelessParametersOfCallableArgument(
        argument: getStatementType($expression),
        nameOrPosition: 0,
        definition: (new FunctionLikeReflectionDefinitionBuilder('foo_TemplateTypesSolverTest', new \ReflectionFunction('Dedoc\Scramble\Tests\Infer\Services\foo_TemplateTypesSolverTest')))->build(),
        templates: [],
    );

    expect($argumentType->toString())->toBe($expectedType);
})->with([
    ['fn ($arg) => $arg', '(string): string'],
    ['fn (int $arg) => $arg', '(int): int'],
    ['fn (int $arg) => 42', '(int): int(42)'],
]);
/**
 * @param callable(string): string $p
 */
function foo_TemplateTypesSolverTest ($p) {}

it('accepts this parameter', function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            Foo_TemplateTypesSolverTest::class,
        ]);

    $type = new Generic(Foo_TemplateTypesSolverTest::class, [new LiteralIntegerType(42)]);

    $callCallbackType = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new MethodCallReferenceType($type, 'callCallback', [
                getStatementType('fn ($d) => $d->getT()')->getOriginal(),
            ])
        );

    expect($callCallbackType->toString())->toBe('int(42)');
});
/**
 * @template T
 */
class Foo_TemplateTypesSolverTest
{
    /**
     * @return T
     */
    public function getT() {}

    /**
     * @template TData
     * @param callable($this): TData $cb
     * @return TData
     */
    public function callCallback($cb) {}
}
