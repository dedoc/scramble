<?php

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

it('resolves variable-length spreads to their element types', function (array $items, string $expected) {
    $type = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new KeyedArrayType($items));

    expect($type->toString())->toBe($expected);
})->with([
    'multiple homogeneous spreads' => [[
        new ArrayItemType_(null, new ArrayType(value: new IntegerType), shouldUnpack: true),
        new ArrayItemType_(null, new ArrayType(value: new IntegerType), shouldUnpack: true),
    ], 'array<int>'],
    'different spread element types' => [[
        new ArrayItemType_(null, new ArrayType(value: new IntegerType), shouldUnpack: true),
        new ArrayItemType_(null, new ArrayType(value: new StringType), shouldUnpack: true),
    ], 'array<int|string>'],
    'nested array elements are not flattened' => [[
        new ArrayItemType_(null, new ArrayType(value: new ArrayType(value: new IntegerType)), shouldUnpack: true),
    ], 'array<array<int>>'],
    'unknown spread element type' => [[
        new ArrayItemType_(null, new UnknownType, shouldUnpack: true),
    ], 'array<mixed>'],
]);

it('preserves keyed shapes when resolving known array spreads', function (array $items, string $expected) {
    $type = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new KeyedArrayType($items));

    expect($type)->toBeInstanceOf(KeyedArrayType::class)
        ->and($type->isList)->toBeFalse()
        ->and($type->toString())->toBe($expected)
        ->and(collect($type->items)->contains(fn (ArrayItemType_ $item) => $item->shouldUnpack))->toBeFalse();
})->with([
    'single keyed shape' => [[
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new IntegerType),
        ]), shouldUnpack: true),
    ], 'array{id: int}'],
    'literal entries surrounding a spread' => [[
        new ArrayItemType_('name', new StringType),
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new IntegerType),
        ]), shouldUnpack: true),
        new ArrayItemType_('active', new BooleanType),
    ], 'array{name: string, id: int, active: boolean}'],
    'spread overwrites an earlier string key' => [[
        new ArrayItemType_('id', new IntegerType),
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new StringType),
        ]), shouldUnpack: true),
    ], 'array{id: string}'],
    'literal entry overwrites a spread string key' => [[
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new StringType),
        ]), shouldUnpack: true),
        new ArrayItemType_('id', new IntegerType),
    ], 'array{id: int}'],
    'multiple keyed spreads' => [[
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new IntegerType),
            new ArrayItemType_('name', new StringType),
        ]), shouldUnpack: true),
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new StringType),
            new ArrayItemType_('active', new BooleanType),
        ]), shouldUnpack: true),
    ], 'array{id: string, name: string, active: boolean}'],
    'optional property remains optional' => [[
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new IntegerType),
            new ArrayItemType_('name', new StringType, isOptional: true),
        ]), shouldUnpack: true),
    ], 'array{id: int, name?: string}'],
    'nested keyed value is not unpacked' => [[
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('product', new KeyedArrayType([
                new ArrayItemType_('id', new IntegerType),
            ])),
        ]), shouldUnpack: true),
    ], 'array{product: array{id: int}}'],
]);

it('preserves known properties alongside variable-length keyed spreads', function (array $items, string $expected) {
    $type = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new KeyedArrayType($items));

    expect($type)->toBeInstanceOf(KeyedArrayType::class)
        ->and($type->isList)->toBeFalse()
        ->and($type->toString())->toBe($expected);
})->with([
    'required property with additional values of the same type' => [[
        new ArrayItemType_('id', new IntegerType),
        new ArrayItemType_(null, new ArrayType(value: new IntegerType, key: new StringType), shouldUnpack: true),
    ], 'array{id: int, ...<string, int>}'],
    'literal property after a string-keyed spread' => [[
        new ArrayItemType_(null, new ArrayType(value: new StringType, key: new StringType), shouldUnpack: true),
        new ArrayItemType_('id', new IntegerType),
    ], 'array{id: int, ...<string, string>}'],
    'string-keyed spread may overwrite an earlier property' => [[
        new ArrayItemType_('id', new IntegerType),
        new ArrayItemType_(null, new ArrayType(value: new StringType, key: new StringType), shouldUnpack: true),
    ], 'array{id: int|string, ...<string, string>}'],
    'integer-keyed spread cannot overwrite a string property' => [[
        new ArrayItemType_('id', new IntegerType),
        new ArrayItemType_(null, new ArrayType(value: new StringType), shouldUnpack: true),
    ], 'array{id: int, ...<int, string>}'],
    'known keyed spread alongside a variable-length spread' => [[
        new ArrayItemType_(null, new ArrayType(value: new StringType, key: new StringType), shouldUnpack: true),
        new ArrayItemType_(null, new KeyedArrayType([
            new ArrayItemType_('id', new IntegerType),
            new ArrayItemType_('active', new BooleanType),
        ]), shouldUnpack: true),
    ], 'array{id: int, active: boolean, ...<string, string>}'],
]);

it('preserves a fixed prefix and the minimum length contributed by a trailing element', function () {
    $type = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new KeyedArrayType([
        new ArrayItemType_(null, new LiteralStringType('start')),
        new ArrayItemType_(null, new ArrayType(value: new IntegerType), shouldUnpack: true),
        new ArrayItemType_(null, new StringType),
    ]));

    // The trailing string has no fixed offset, but guarantees a second element.
    // Its type joins the spread elements at every position after the fixed prefix.
    expect($type)->toBeInstanceOf(KeyedArrayType::class)
        ->and($type->isList)->toBeTrue()
        ->and($type->toString())->toBe('list{string(start), int|string, ...<int, int|string>}')
        ->and(collect($type->items)->where('isOptional', false)->where('shouldUnpack', false))->toHaveCount(2);
});

it('preserves unpacked parameter templates until resolving a call', function () {
    $analysis = analyzeFile(<<<'PHP'
<?php
class UnpackedParameters {
    public function unpack(array $first, array $second): array
    {
        return [...$first, ...$second];
    }
}
PHP);

    $class = $analysis->getClassDefinition('UnpackedParameters');
    $type = $class->methods['unpack']->type->getReturnType();

    expect($type)->toBeInstanceOf(KeyedArrayType::class)
        ->and($type->toString())->toBe('list{TFirst, TSecond}')
        ->and($type->items)->toHaveCount(2)
        ->and($type->items[0]->shouldUnpack)->toBeTrue()
        ->and($type->items[0]->value)->toBeInstanceOf(TemplateType::class)
        ->and($type->items[1]->shouldUnpack)->toBeTrue()
        ->and($type->items[1]->value)->toBeInstanceOf(TemplateType::class);

    $result = $analysis->getExpressionType('(new UnpackedParameters)->unpack(["id" => 1], ["name" => "foo"])');

    expect($result)->toBeInstanceOf(KeyedArrayType::class)
        ->and($result->toString())->toBe('array{id: int(1), name: string(foo)}');
});

it('resolves unpacked mapped collections to an array of shapes', function (string $method) {
    if ($method === 'scopedRewards' && ! class_exists(Scope::class)) {
        $this->markTestSkipped('Attribute-based scopes require Laravel 12 or later.');
    }

    $type = getStatementType('(new '.UnpackedCollections_ArrayUnpackingTest::class.')->'.$method.'()');

    expect($type)->toBeInstanceOf(ArrayType::class)
        ->and($type->toString())->toBe('array<array{id: int}>');
})->with(['plain collections' => 'rewards', 'attribute-scoped queries' => 'scopedRewards']);

class UnpackedCollections_ArrayUnpackingTest
{
    public function rewards(): array
    {
        $first = (new Collection)->map(fn (int $id): array => ['id' => $id]);
        $second = (new Collection)->map(fn (int $id): array => ['id' => $id]);

        return [...$first->values()->all(), ...$second->values()->all()];
    }

    public function scopedRewards(): array
    {
        $first = UnpackedScopedModel_ArrayUnpackingTest::query()
            ->available()
            ->get()
            ->map(fn (UnpackedScopedModel_ArrayUnpackingTest $post): array => ['id' => $post->id]);
        $second = UnpackedScopedModel_ArrayUnpackingTest::query()
            ->available()
            ->get()
            ->map(fn (UnpackedScopedModel_ArrayUnpackingTest $post): array => ['id' => $post->id]);

        return [...$first->values()->all(), ...$second->values()->all()];
    }
}

class UnpackedScopedModel_ArrayUnpackingTest extends SamplePostModel
{
    #[Scope]
    protected function available(Builder $query): Builder
    {
        return $query->where('approved', true);
    }
}
