<?php

namespace Dedoc\Scramble\Tests\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\Reference\PotentialMethodMutatingCallType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * @template TLoadedRelations
 */
class ArchiveShelf_MethodCallHandlerTest
{
    /**
     * @phpstan-this-out self<array{labels: true}>
     */
    public function with(array $relations): self
    {
        return $this;
    }
}

class Archive_MethodCallHandlerTest
{
    /**
     * @return ArchiveShelf_MethodCallHandlerTest<array{}>
     */
    public function editions(): ArchiveShelf_MethodCallHandlerTest
    {
        return new ArchiveShelf_MethodCallHandlerTest;
    }
}

class RepeatedPredicate_MethodCallHandlerTest
{
    public function isReady(): bool
    {
        return true;
    }
}

class CountingReferenceTypeResolver_MethodCallHandlerTest extends ReferenceTypeResolver
{
    public int $potentialTypeResolutions = 0;

    public function resolve(Scope $scope, Type $type): Type
    {
        if ($type instanceof PotentialMethodMutatingCallType) {
            $this->potentialTypeResolutions++;
        }

        return parent::resolve($scope, $type);
    }
}

it('does not mutate the root variable for a fluent call on a returned object', function () {
    Scramble::infer()->configure()->buildDefinitionsUsingReflectionFor([
        Archive_MethodCallHandlerTest::class,
        ArchiveShelf_MethodCallHandlerTest::class,
    ]);

    $class = Archive_MethodCallHandlerTest::class;

    $archiveType = getVariableTypeAfter(<<<PHP
\$archive = new {$class};
\$archive->editions()->with(['labels']);
PHP,
        'archive',
    );

    expect($archiveType->toString())->toBe(Archive_MethodCallHandlerTest::class);
});

it('resolves repeated non-mutating calls on the same variable without expanding the call history', function () {
    Scramble::infer()->configure()->buildDefinitionsUsingReflectionFor([
        RepeatedPredicate_MethodCallHandlerTest::class,
    ]);

    $class = RepeatedPredicate_MethodCallHandlerTest::class;
    $predicateCalls = implode(",\n", array_fill(0, 100, '$value->isReady()'));
    $resolver = new CountingReferenceTypeResolver_MethodCallHandlerTest(app(Index::class));

    $valueType = getVariableTypeAfter(<<<PHP
\$value = new {$class};
\$results = [
{$predicateCalls},
];
PHP,
        'value',
        $resolver,
    );

    expect($valueType->toString())->toBe(RepeatedPredicate_MethodCallHandlerTest::class)
        ->and($resolver->potentialTypeResolutions)->toBe(1);
});
