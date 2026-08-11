<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * Extension to add deprecation notice to the operation description
 * Skips if method is not deprecated
 * If a whole class is deprecated, all methods are deprecated, only add the description if exists
 */
class DeprecationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $routeInfo->reflectionAction() || $this->isExplicitlyMarkedNotDeprecated($routeInfo)) {
            return;
        }

        $reflectionAction = $routeInfo->reflectionAction();

        $deprecatedClassTags = $reflectionAction instanceof ReflectionMethod
            ? $this->getClassDeprecatedTagValues($reflectionAction->getDeclaringClass()->getName())
            : [];
        $deprecatedTags = $routeInfo->phpDoc()->getDeprecatedTagValues();
        $deprecatedClassAttributes = $reflectionAction instanceof ReflectionMethod
            ? $this->getDeprecatedAttributeDescriptions($reflectionAction->getDeclaringClass())
            : [];
        $deprecatedAttributes = $this->getDeprecatedAttributeDescriptions($reflectionAction);

        // Skip if no deprecations found
        if (! $deprecatedClassTags && ! $deprecatedTags && ! $deprecatedClassAttributes && ! $deprecatedAttributes) {
            return;
        }

        $description = Str::of($this->generateDescription($deprecatedClassTags, $deprecatedClassAttributes));

        if ($description->isNotEmpty()) {
            $description = $description->append("\n\n");
        }

        $description = $description->append($this->generateDescription($deprecatedTags, $deprecatedAttributes));

        $operation
            ->description((string) $description)
            ->deprecated(true);
    }

    /**
     * @param  ReflectionClass<object>|ReflectionFunctionAbstract  $reflection
     * @return array<string>
     */
    private function getDeprecatedAttributeDescriptions(ReflectionClass|ReflectionFunctionAbstract $reflection): array
    {
        return array_map(function (ReflectionAttribute $attribute) {
            $arguments = $attribute->getArguments();
            $message = $arguments['message'] ?? $arguments[0] ?? null;
            $since = $arguments['since'] ?? $arguments[1] ?? null;

            return implode(' ', array_filter(
                [$since, $message],
                fn ($argument) => is_string($argument) && $argument !== '',
            ));
        }, $reflection->getAttributes('Deprecated'));
    }

    protected function isExplicitlyMarkedNotDeprecated(RouteInfo $routeInfo): bool
    {
        return $routeInfo->phpDoc()->getTagsByName('@notDeprecated')
            || $routeInfo->phpDoc()->getTagsByName('@not-deprecated'); // inconsistent alias.
    }

    /**
     * @return array<DeprecatedTagValueNode>
     */
    protected function getClassDeprecatedTagValues(string $fqdn): array
    {
        $reflector = ClassReflector::make($fqdn);
        $classPhpDocString = $reflector->getReflection()->getDocComment();

        if ($classPhpDocString === false) {
            return [];
        }

        return array_values(PhpDoc::parse($classPhpDocString)->getDeprecatedTagValues());
    }

    /**
     * @param  array<DeprecatedTagValueNode>  $deprecatedTagValues
     * @param  array<string>  $deprecatedAttributeDescriptions
     */
    private function generateDescription(array $deprecatedTagValues, array $deprecatedAttributeDescriptions): string
    {
        return implode("\n", array_filter(
            [
                ...array_map(fn ($tag) => $tag->description, $deprecatedTagValues),
                ...$deprecatedAttributeDescriptions,
            ],
            fn ($description) => $description !== '',
        ));
    }
}
