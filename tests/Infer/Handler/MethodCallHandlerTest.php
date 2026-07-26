<?php

namespace Dedoc\Scramble\Tests\Infer\Handler;

use Dedoc\Scramble\Scramble;

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
