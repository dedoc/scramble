<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

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
            $type->example($examples[0]);
        }

        if ($default = ExamplesExtractor::make($docNode, '@default')->extract(preferString: $type instanceof StringType)) {
            $type->default($default[0]);
        }

        $deprecated = array_values($docNode->getTagsByName('@deprecated'))[0]->value ?? null;
        if ($deprecated instanceof DeprecatedTagValueNode) {
            $type->deprecated(true);

            if ($deprecated->description) {
                $type->setDescription($type->description.$deprecated->description);
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
}
