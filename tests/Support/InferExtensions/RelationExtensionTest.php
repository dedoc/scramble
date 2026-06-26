<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlainPostModel_RelationExtensionTest extends \Illuminate\Database\Eloquent\Model {}

class PlainUserModel_RelationExtensionTest extends \Illuminate\Database\Eloquent\Model
{
    public function posts()
    {
        return $this->hasMany(PlainPostModel_RelationExtensionTest::class);
    }
}

it('tracks relations passed to with() on relation', function (string $expression, string $expectedRelationsType) {
    $relationType = getStatementType($expression);

    expect($relationType)->toBeInstanceOf(Generic::class)
        ->and($relationType->name)->toBe(HasMany::class);

    $relationsType = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new PropertyFetchReferenceType($relationType->templateTypes[0], 'relations'),
        );

    expect($relationsType->toString())->toBe($expectedRelationsType);
})->with([
    'variadic strings' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author', 'comments')->with('team')",
        'list{string(author), string(comments), string(team)}',
    ],
    'relation with closure' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author', fn (\$q) => \$q)",
        'list{string(author)}',
    ],
    'array relations' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with(['author' => fn (\$q) => \$q, 'comments' => fn (\$q) => \$q])",
        'list{string(author), string(comments)}',
    ],
    'without variadic strings' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author', 'comments', 'team')->without('author', 'comments')",
        'list{string(team)}',
    ],
    'withOnly replaces eager loads' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author', 'comments')->withOnly('team')",
        'list{string(team)}',
    ],
    'setEagerLoads replaces eager loads' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author')->setEagerLoads(['comments', 'team'])",
        'list{string(comments), string(team)}',
    ],
    'withoutEagerLoads clears eager loads' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author', 'comments')->withoutEagerLoads()",
        'list{}',
    ],
]);

it('carries loaded relations from relation through get()', function (string $expression, string $expectedRelationsType) {
    $type = getStatementType($expression);

    $modelType = (new TypeWalker)->first(
        $type,
        fn ($t) => $t->isInstanceOf(PlainPostModel_RelationExtensionTest::class),
    );

    expect($modelType)->not->toBeNull();

    $relationsType = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new PropertyFetchReferenceType($modelType, 'relations'),
        );

    expect($relationsType->toString())->toBe($expectedRelationsType);
})->with([
    'get' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author', 'comments')->get()",
        'list{string(author), string(comments)}',
    ],
    'first' => [
        PlainUserModel_RelationExtensionTest::class."::query()->first()->posts()->with('author', 'comments')->first()",
        'list{string(author), string(comments)}',
    ],
]);

it('tracks relations after with() on assigned relation', function (string $mutatingExpression, string $expectedLoadedRelationsType) {
    $class = PlainUserModel_RelationExtensionTest::class;

    $type = getVariableTypeAfter(<<<PHP
\$user = {$class}::query()->first();
\$relation = \$user->posts();
{$mutatingExpression};
PHP,
        'relation',
    );

    expect($type->toString())
        ->toBe('Illuminate\Database\Eloquent\Relations\HasMany<'.PlainPostModel_RelationExtensionTest::class.', '.$class.'>')
        ->and($type->templateTypes[0]->getPropertyType('relations')->toString())
        ->toBe($expectedLoadedRelationsType);
})->with([
    'with' => [
        "\$relation->with('author')",
        'list{string(author)}',
    ],
    'sequential with calls' => [
        "\$relation->with('author')->with('comments')",
        'list{string(author), string(comments)}',
    ],
]);
