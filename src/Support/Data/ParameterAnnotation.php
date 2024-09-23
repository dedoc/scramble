<?php

namespace Dedoc\Scramble\Support\Data;

use Illuminate\Support\Str;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use Dedoc\Scramble\Support\Generator\MissingExample;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;

class ParameterAnnotation
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $summary = null,
        public readonly ?string $description = null, // can be single with description
        public readonly ?Type $type = null,
        public readonly ?string $format = null,
        public readonly mixed $example = new MissingExample,
        public readonly mixed $default = new MissingExample,
        public readonly bool $shouldIgnore = false,
        public readonly bool $isInQuery = false,
    )
    {
    }

    public static function fromDocNode(string $name, ?PhpDocNode $docNode, bool $preferStrings = false)
    {
        $parameter = new self($name);

        if (! $docNode) {
            return $parameter;
        }

        $description = (string) Str::of($docNode->getAttribute('summary') ?: '')
            ->append(' '.($docNode->getAttribute('description') ?: ''))
            ->trim();

        if ($description) {
            $parameter->description = $description;
        }

        if (count($varTags = $docNode->getVarTagValues())) {
            $varTag = $varTags[0];

            $parameter->type = PhpDocTypeHelper::toType($varTag->type);
        }

        if ($examples = ExamplesExtractor::make($docNode)->extract($preferStrings)) {
            $parameter->example = $examples[0];
        }

        if ($default = ExamplesExtractor::make($docNode, '@default')->extract($preferStrings)) {
            $parameter->default = $default[0];
        }

        if ($format = array_values($docNode->getTagsByName('@format'))[0]->value->value ?? null) {
            $parameter->format = $format;
        }

        if ($docNode->getTagsByName('@query')) {
            $parameter->isInQuery = true;
        }

        if (count($docNode->getTagsByName('@ignoreParam') ?? [])) {
            $parameter->shouldIgnore = true;
        }

        return $parameter;
    }
}
