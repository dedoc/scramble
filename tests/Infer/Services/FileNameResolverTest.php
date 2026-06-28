<?php

namespace Dedoc\Scramble\Tests\Infer\Services;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;

beforeEach(function () {
    FileNameResolver::$nameContextCache = [];
});

it('returns leading-backslash FQN as-is without prepending the file namespace', function () {
    $resolver = makeResolver_FileNameResolverTest('Facades\\App\\Services\\Search\\Meilisearch\\Product');

    $resolved = $resolver('\\App\\Services\\Search\\Meilisearch\\Product\\ProductSearchService');

    expect($resolved)->toBe('App\\Services\\Search\\Meilisearch\\Product\\ProductSearchService');
});

it('resolves an unqualified short name against the file namespace when the class exists', function () {
    $resolver = makeResolver_FileNameResolverTest('Dedoc\\Scramble\\Tests\\Infer\\Services');

    $resolved = $resolver('Existing_FileNameResolverTest');

    expect($resolved)->toBe(Existing_FileNameResolverTest::class);
});

it('resolves a multi-segment relative name correctly', function () {
    $resolver = makeResolver_FileNameResolverTest('App');

    $resolved = $resolver('Sub\\Thing');

    // The class doesn't exist, so __invoke returns the original short name (per existing contract).
    // What matters is that the intermediate resolution doesn't blow up or produce literal `\\`.
    expect($resolved)->toBe('Sub\\Thing');
});

it('resolves an aliased use statement', function () {
    $resolver = makeResolver_FileNameResolverTest('App', uses: [
        ['Other\\Service\\Bar', 'Bar'],
    ]);

    $resolved = $resolver('Bar');

    expect($resolved)->toBe('Bar');
});

it('does not produce literal double backslashes for any common phpdoc input', function () {
    $resolver = makeResolver_FileNameResolverTest('Facades\\App\\Foo');

    foreach ([
        '\\App\\Foo\\Bar',
        'App\\Foo\\Bar',
        '\\Bar',
        'Bar',
        '\\Some\\Deeply\\Nested\\Type',
    ] as $input) {
        expect($resolver($input))->not->toContain('\\\\');
    }
});

function makeResolver_FileNameResolverTest(string $namespace, array $uses = []): FileNameResolver
{
    $context = new NameContext(new Throwing);
    $context->startNamespace(new Name($namespace));

    foreach ($uses as [$fqn, $alias]) {
        $context->addAlias(new Name($fqn), $alias, Use_::TYPE_NORMAL);
    }

    return new FileNameResolver($context);
}

class Existing_FileNameResolverTest {}
