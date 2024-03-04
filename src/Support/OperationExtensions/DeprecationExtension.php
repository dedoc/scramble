<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;

/**
 * Extension to add deprecation notice to the operation description
 * Skips if method is not deprecated
 * If a whole class is deprecated, all methods are deprecated, only add the description if exists
 */
class DeprecationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        // Skip if method is not deprecated
        if (! $routeInfo->reflectionMethod() || $routeInfo->phpDoc()->getTagsByName('@not-deprecated')) {
            return;
        }

        $fqdn = $routeInfo->reflectionMethod()->getDeclaringClass()->getName();
        $deprecatedClass = $this->getClassDeprecatedValues($fqdn);
        $deprecatedTags = $routeInfo->phpDoc()->getDeprecatedTagValues();

        // Skip if no deprecations found
        if (! $deprecatedClass && ! $deprecatedTags) {
            return;
        }

        $description = Str::of($this->generateDescription($deprecatedClass));

        if ($description->isNotEmpty()) {
            $description = $description->append("\n\n");
        }

        $description = $description->append($this->generateDescription($deprecatedTags));

        $operation
            ->description((string) $description)
            ->deprecated(true);
    }

    /**
     * @return array<DeprecatedTagValueNode>
     */
    protected function getClassDeprecatedValues(string $fqdn)
    {
        $reflector = ClassReflector::make($fqdn);
        $classPhpDocString = $reflector->getReflection()->getDocComment();

        if ($classPhpDocString === false) {
            return [];
        }

        return PhpDoc::parse($classPhpDocString)->getDeprecatedTagValues();
    }

    /**
     * @return string
     */
    private function generateDescription(array $deprecation)
    {
        return implode("\n", array_map(fn ($tag) => $tag->description, $deprecation));
    }
}
