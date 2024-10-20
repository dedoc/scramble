<?php

namespace Dedoc\Scramble\Tests\Support;

use Dedoc\Scramble\Support\ReflectionClassWithDocBlock;
use Dedoc\Scramble\Tests\TestCase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase as TestCaseAlias;

/**
 * @property null|(TestCaseAlias&TestCase) $intersection
 * @property TestCaseAlias|TestCase $union
 * @property array|TestCaseAlias[] $array
 * @property TestCaseAlias $case
 * @property array<int,TestCaseAlias> $generic
 * @property array{"name":TestCaseAlias} $object
 * @property int-mask<MockEnum> $bitmask
 * @property Collection|TestCaseAlias[] $collection
 */
class ReflectionClassWithDocBlockTest extends TestCaseAlias
{
    public function test()
    {
        $reflection = new ReflectionClassWithDocBlock(self::class);

        $docs = $reflection->getDocParsed();

        $this->assertEquals('null', $docs->children[0]->value->type->types[0]->name);
        $this->assertEquals(TestCaseAlias::class, $docs->children[0]->value->type->types[1]->types[0]->name);
        $this->assertEquals(TestCase::class, $docs->children[0]->value->type->types[1]->types[1]->name);

        $this->assertEquals(TestCaseAlias::class, $docs->children[1]->value->type->types[0]->name);
        $this->assertEquals(TestCase::class, $docs->children[1]->value->type->types[1]->name);

        $this->assertEquals('array', $docs->children[2]->value->type->types[0]->name);
        $this->assertEquals(TestCaseAlias::class, $docs->children[2]->value->type->types[1]->type->name);

        $this->assertEquals(TestCaseAlias::class, $docs->children[3]->value->type->name);

        $this->assertEquals(TestCaseAlias::class, $docs->children[4]->value->type->genericTypes[1]->name);

        $this->assertEquals(TestCaseAlias::class, $docs->children[5]->value->type->items[0]->valueType->name);

        $this->assertEquals(MockEnum::class, $docs->children[6]->value->type->genericTypes[0]->name);

        $this->assertEquals(Collection::class, $docs->children[7]->value->type->types[0]->name);
        $this->assertEquals(TestCaseAlias::class, $docs->children[7]->value->type->types[1]->type->name);
    }
}
