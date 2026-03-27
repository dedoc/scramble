<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Support\Collection;

it('supports this in map', function () {
    $collectionType = new Generic(Collection::class, [
        new IntegerType,
        new ObjectType(SamplePostModel::class),
    ]);

    $typeAfterMap = ReferenceTypeResolver::getInstance()
        ->resolve(
            tap(new GlobalScope, function (GlobalScope $s) {
                $s->context = new ScopeContext(
                    classDefinition: app(Infer::class)->analyzeClass(SamplePostModel::class),
                    functionDefinition: null,
                );
            }),
            (new MethodCallReferenceType($collectionType, 'map', [
                new FunctionType('{}', [], new SelfType(SamplePostModel::class)),
            ])),
        );

    expect($typeAfterMap->toString())->toBe(Collection::class.'<int, '.SamplePostModel::class.'>');
});

/*
 * Regression subject: lexical `$this` is lowered to `ObjectType(Bar::class)` in
 * `ReferenceTypeResolver::finalizeSelfForCallableArguments`, so template
 * arguments (here `int(42)` on `Bar`) are lost when the value flows through
 * `Collection::map`’s `@template TMapValue` resolution. The closure case above
 * (`array{a: $this}`) still preserves `Bar<int(42)>` because it does not
 * cross that path.
 *
 * Target value type for the collection: `Bar<int(42)>` (match lexical `self`).
 * Today: plain `Bar`. Key type may widen to `int|string` from the framework stub.
 */
it('lexical this through collection map loses generic template args on self', function () {
    $type = analyzeFile(<<<'PHP'
<?php
use Illuminate\Support\Collection;

class Bar {
    public int $prop;
    public function __construct(int $p) {
        $this->prop = $p;
    }

    /** @return Collection<int, Bar> */
    public function related(): Collection {
        return new Collection([]);
    }

    public function mapped() {
        return $this->related()->map(fn () => $this);
    }
}
PHP)
        ->getExpressionType('(new Bar(42))->mapped()');

    expect($type->toString())->toBe('Illuminate\Support\Collection<int|string, Bar<int(42)>>');
});
