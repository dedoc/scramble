<?php

namespace Dedoc\Scramble\Support\Helpers;

use Dedoc\Scramble\Support\Generator\MissingValue;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * Extracts `@example` value from PHPDoc node.
 */
class ExamplesExtractor
{
    public function __construct(
        private ?PhpDocNode $docNode,
        private string $tagName = '@example',
    ) {}

    public static function make(?PhpDocNode $docNode, string $tagName = '@example')
    {
        return new self($docNode, $tagName);
    }

    public function extract(bool $preferString = false)
    {
        if (! count($examples = $this->docNode?->getTagsByName($this->tagName) ?? [])) {
            return [];
        }

        return array_map(
            fn ($example) => $this->getTypedExampleValue($example->value->value ?? null, $preferString),
            array_values($examples),
        );
    }

    private function getTypedExampleValue($exampleValue, bool $preferString = false)
    {
        if (! is_string($exampleValue)) {
            return new MissingValue;
        }

        if (function_exists('json_decode')) {
            $json = json_decode($exampleValue, true);

            $exampleValue = $json === null || $json == $exampleValue
                ? $exampleValue
                : $json;
        }

        if ($exampleValue === 'null') {
            $exampleValue = null;
        } elseif (in_array($exampleValue, ['true', 'false'])) {
            $exampleValue = $exampleValue === 'true';
        } elseif (is_numeric($exampleValue) && ! $preferString) {
            $exampleValue = floatval($exampleValue);

            if (floor($exampleValue) == $exampleValue) {
                $exampleValue = intval($exampleValue);
            }
        }

        return $exampleValue;
    }
}
