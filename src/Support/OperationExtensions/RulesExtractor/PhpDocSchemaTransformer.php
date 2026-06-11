<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Example;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

class PhpDocSchemaTransformer
{
    public function __construct(
        private TypeTransformer $openApiTransformer,
    ) {}

    public function transform(Schema $type, PhpDocNode $docNode): Schema
    {
        if (count($varTags = $docNode->getVarTagValues())) {
            $varTag = array_values($varTags)[0];

            $type = $this->openApiTransformer
                ->transform(PhpDocTypeHelper::toType($varTag->type))
                ->mergeAttributes($type->attributes());
        }

        $description = (string) Str::of($docNode->getAttribute('summary') ?: '') // @phpstan-ignore argument.type
            ->append(' '.($docNode->getAttribute('description') ?: '')) // @phpstan-ignore binaryOp.invalid
            ->trim();

        if ($description) {
            $type->setDescription($description);
        }

        if ($examples = ExamplesExtractor::make($docNode)->extract(preferString: $type instanceof StringType)) {
            $exampleTypes = collect($examples)
                ->map(fn ($e) => $this->getExampleType($e))
                ->filter()
                ->unique()
                ->values();

            $allMatch = collect($examples)->every(fn ($e) => $this->typeMatchesExample($type, $e));

            if (! $allMatch && $exampleTypes->count() > 1 && ! $type instanceof \Dedoc\Scramble\Support\Generator\Combined\AnyOf && ! $type instanceof \Dedoc\Scramble\Support\Generator\Combined\OneOf) {
                $items = $exampleTypes->map(function ($typeName) {
                    return $this->openApiTransformer->transform(
                        PhpDocTypeHelper::toType(new IdentifierTypeNode($typeName))
                    );
                })->all();

                $type = (new \Dedoc\Scramble\Support\Generator\Combined\OneOf)->setItems($items)->addProperties($type);
            }

            if (count($examples) > 1 && $type instanceof \Dedoc\Scramble\Support\Generator\Combined\AnyOf) {
                $type = (new \Dedoc\Scramble\Support\Generator\Combined\OneOf)->setItems($type->items)->addProperties($type);
            }

            if (count($examples) === 1) {
                $type->example($examples[0]);
            } else {
                $type->examples($examples);
            }
        }

        if ($default = ExamplesExtractor::make($docNode, '@default')->extract(preferString: $type instanceof StringType)) {
            $type->default($default[0]);
        }

        $deprecated = array_values($docNode->getTagsByName('@deprecated'))[0]->value ?? null;
        if ($deprecated instanceof DeprecatedTagValueNode) {
            $type->deprecated(true);

            if ($deprecated->description) {
                $type->setDescription(implode(' ', array_filter([
                    $type->description,
                    $deprecated->description,
                ])));
            }
        }

        if ($format = array_values($docNode->getTagsByName('@format'))[0]->value->value ?? null) {
            $type->format($format);
        }

        if ($docNode->getTagsByName('@query')) {
            $type->setAttribute('isInQuery', true);
        }

        return $type;
    }

    private function getExampleType(mixed $example): ?string
    {
        if ($example instanceof Example && $example->type) {
            return $example->type;
        }
        $val = $example instanceof Example ? $example->value : $example;
        if ($val instanceof MissingValue) {
            return null;
        }

        if (is_int($val)) {
            return 'integer';
        }
        if (is_bool($val)) {
            return 'boolean';
        }
        if (is_float($val)) {
            return 'number';
        }
        if (is_array($val)) {
            return 'array';
        }
        if (is_null($val)) {
            return 'null';
        }
        if (is_string($val)) {
            return 'string';
        }

        return null;
    }

    private function typeMatchesExample(Schema $type, mixed $example): bool
    {
        if ($example instanceof Example && $example->type) {
            return $type->type === $example->type;
        }
        $val = $example instanceof Example ? $example->value : $example;

        return $type->matches($val);
    }
}
