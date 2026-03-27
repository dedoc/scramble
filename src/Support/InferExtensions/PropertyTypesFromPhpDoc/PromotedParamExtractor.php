<?php

namespace Dedoc\Scramble\Support\InferExtensions\PropertyTypesFromPhpDoc;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Illuminate\Support\Collection;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use ReflectionClass;
use ReflectionParameter;
use Webmozart\Assert\Assert;

/**
 * Extracts properties from `param` tags on the constructor's docblock for promoted parameters.
 */
final class PromotedParamExtractor implements PropertyExtractor
{
    /**
     * Retrieve the properties.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @return \Illuminate\Support\Collection<string, \Dedoc\Scramble\Support\Type\Type>
     */
    public function __invoke(ReflectionClass $reflection): Collection
    {
        $constructor = $reflection->getConstructor();
        $phpdoc = $constructor?->getDocComment();

        if (! is_string($phpdoc) || $constructor === null) {
            return new Collection;
        }

        $promotedParameters = (new Collection($constructor->getParameters()))
            ->filter(fn (ReflectionParameter $parameter) => $parameter->isPromoted());

        return (new Collection(PhpDoc::parse($phpdoc)->children))
            ->filter(fn (mixed $tag) => $this->isPromotedParamTag($tag, $promotedParameters))
            ->mapWithKeys(function (PhpDocTagNode $tag) {
                Assert::isInstanceOf($tag->value, ParamTagValueNode::class);

                $name = ltrim($tag->value->parameterName, '$');

                return [$name => PhpDocTypeHelper::toType($tag->value->type)];
            });
    }

    /**
     * Check if the tag is a valid `param` tag for a promoted parameter.
     *
     * @param  \Illuminate\Support\Collection<int, \ReflectionParameter>  $promotedParameters
     */
    private function isPromotedParamTag(mixed $tag, Collection $promotedParameters): bool
    {
        if (! $tag instanceof PhpDocTagNode || $tag->name !== '@param' || ! $tag->value instanceof ParamTagValueNode) {
            return false;
        }

        $name = ltrim($tag->value->parameterName, '$');

        return $promotedParameters->contains(
            fn (ReflectionParameter $parameter) => $parameter->getName() === $name,
        );
    }
}
