<?php

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\OffsetAccessType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;

it('handles array fetch', function () {
    expect(['a' => 1]['a'])->toHaveType('int(1)');
});

it('handles array deep fetch', function () {
    expect(['b' => ['c' => 42]]['b']['c'])->toHaveType('int(42)');
});

it('handles array fetch on a union of shapes', function () {
    $condition = (bool) random_int(0, 1);
    $array = $condition ? ['x' => 'pdf'] : ['x' => 'jpg'];

    expect($array['x'])->toHaveType('string(pdf)|string(jpg)');
});

it('handles array fetch with a union of keys', function () {
    $condition = (bool) random_int(0, 1);
    $array = ['x' => 'pdf', 'y' => 'jpg'];
    $key = $condition ? 'x' : 'y';

    expect($array[$key])->toHaveType('string(pdf)|string(jpg)');
});

it('handles each array fetch on a method call result from a nullable object', function () {
    $analysis = analyzeFile(<<<'PHP'
<?php
class OffsetGetTest_Counts {
    public function get(): array { return ['added' => random_int(0, 5), 'existing' => random_int(0, 5)]; }
}
class OffsetGetTest_Holder {
    private ?OffsetGetTest_Counts $counts = null;
    public function counts(): ?OffsetGetTest_Counts { return $this->counts; }
}
function readCounts(OffsetGetTest_Holder $holder) {
    $counts = $holder->counts()->get();

    return ['added' => $counts['added'], 'existing' => $counts['existing']];
}
PHP);

    expect($analysis->getFunctionDefinition('readCounts')->getReturnType()->toString())
        ->toBe('array{added: int, existing: int}');
});

it('defers array fetch on a union containing a function parameter', function () {
    $analysis = analyzeFile(<<<'PHP'
<?php
function fetchOffset($a) {
    $condition = (bool) random_int(0, 1);
    $array = $condition ? ['x' => 42] : $a;
    $key = $condition ? 'x' : 'y';

    return $array[$key];
}
PHP);

    $returnType = $analysis->getFunctionDefinition('fetchOffset')->getReturnType();

    expect($returnType)->toBeInstanceOf(OffsetAccessType::class)
        ->and($returnType->type->toString())->toBe('array{x: int(42)}|TA')
        ->and($returnType->offset->toString())->toBe('string(x)|string(y)');
});

it('resolves deferred array fetch unions using a function call argument', function () {
    $analysis = analyzeFile(<<<'PHP'
<?php
function fetchOffset($a) {
    $condition = (bool) random_int(0, 1);
    $array = $condition ? ['x' => 42] : $a;
    $key = $condition ? 'x' : 'y';

    return $array[$key];
}
PHP);

    $returnType = $analysis->getFunctionDefinition('fetchOffset')->getReturnType();

    $result = (new ReferenceTypeResolver($analysis->index))->resolve(
        new GlobalScope($analysis->index),
        new CallableCallReferenceType(new CallableStringType('fetchOffset'), [
            new KeyedArrayType([
                new ArrayItemType_('x', new LiteralStringType('pdf')),
                new ArrayItemType_('y', new LiteralStringType('jpg')),
            ]),
        ]),
    );

    expect($result)->toBeInstanceOf(Union::class);

    expect(array_map(fn (Type $type) => $type->toString(), $result->types))
        ->toEqualCanonicalizing(['int(42)', 'string(pdf)', 'string(jpg)', 'unknown']);

    // The missing 'y' key contributes an unknown value and preserves possible undefinedness.
    expect(TypeHelper::mayBeUndefinedInCoalesce($result))->toBeTrue();

    expect($analysis->getFunctionDefinition('fetchOffset')->getReturnType())->toBe($returnType);
});
