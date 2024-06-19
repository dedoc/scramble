<?php

namespace Dedoc\Scramble\Support;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use ReflectionClass;

class SchemaClassDocReflector
{
    public function __construct(public readonly PhpDocNode $phpDoc) {}

    public function getTagValue(string $tagName)
    {
        return array_values($this->phpDoc->getTagsByName($tagName))[0]?->value ?? null;
    }

    public function getSchemaName(string $default = ''): ?string
    {
        return explode("\n", $this->phpDoc->getTagsByName('@schemaName')[0]->value->value ?? $default)[0];
    }

    public function getDescription(): string
    {
        return trim(implode("\n", array_map(
            static function (PhpDocChildNode $child): string {
                $s = (string) $child;

                return $s === '' ? '' : ' '.$s;
            },
            array_filter($this->phpDoc->children, fn ($n) => $n instanceof PhpDocTextNode)
        )));
    }

    public static function createFromDocString(string $phpDocString)
    {
        return new self(PhpDoc::parse($phpDocString ?: '/** */'));
    }

    public static function createFromClassName(string $className)
    {
        $reflection = new ReflectionClass($className);

        return new self(PhpDoc::parse($reflection->getDocComment() ?: '/** */'));
    }
}
