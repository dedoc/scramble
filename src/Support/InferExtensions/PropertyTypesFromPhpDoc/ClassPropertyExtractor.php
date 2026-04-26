<?php

namespace Dedoc\Scramble\Support\InferExtensions\PropertyTypesFromPhpDoc;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Illuminate\Support\Collection;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use ReflectionClass;

/**
 * Extracts properties from `property` and `property-read` tags on the class docblock.
 */
final class ClassPropertyExtractor implements PropertyExtractor
{
    /**
     * Retrieve the properties.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @return \Illuminate\Support\Collection<string, \Dedoc\Scramble\Support\Type\Type>
     */
    public function __invoke(ReflectionClass $reflection): Collection
    {
        $comment = $reflection->getDocComment();

        if (! $comment) {
            return new Collection;
        }

        return (new Collection(PhpDoc::parse($comment)->children))
            ->filter($this->isPropertyTag(...))
            ->mapWithKeys(function (PhpDocTagNode $tag) {
                /** @var \PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode $value */
                $value = $tag->value;

                $name = ltrim($value->propertyName, '$');

                return [$name => PhpDocTypeHelper::toType($value->type)];
            });
    }

    private function isPropertyTag(mixed $tag): bool
    {
        return $tag instanceof PhpDocTagNode
            && ($tag->name === '@property' || $tag->name === '@property-read')
            && $tag->value instanceof PropertyTagValueNode;
    }
}
