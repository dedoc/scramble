<?php

namespace Dedoc\Scramble\Support\InferExtensions\PropertyTypesFromPhpDoc;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Illuminate\Support\Collection;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use ReflectionClass;
use Webmozart\Assert\Assert;

/**
 * Extracts properties from `var` tags on the properties of a class.
 */
final class VarPropertyExtractor implements PropertyExtractor
{
    /**
     * Retrieve the properties.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @return \Illuminate\Support\Collection<string, \Dedoc\Scramble\Support\Type\Type>
     */
    public function __invoke(ReflectionClass $reflection): Collection
    {
        $properties = new Collection;

        foreach ($reflection->getProperties() as $reflectionProperty) {
            $phpDoc = $reflectionProperty->getDocComment();

            if (! is_string($phpDoc)) {
                continue;
            }

            $property = new Collection(PhpDoc::parse($phpDoc)->children)
                ->filter($this->isVarTag(...))
                ->map(function (PhpDocTagNode $tag) {
                    Assert::isInstanceOf($tag->value, VarTagValueNode::class);

                    return PhpDocTypeHelper::toType($tag->value->type);
                })
                ->first();

            if ($property !== null) {
                $properties->put($reflectionProperty->getName(), $property);
            }
        }

        return $properties;
    }

    /**
     * Check if the tag is a valid `var` tag.
     */
    private function isVarTag(mixed $tag): bool
    {
        return $tag instanceof PhpDocTagNode
            && $tag->name === '@var'
            && $tag->value instanceof VarTagValueNode;
    }
}
