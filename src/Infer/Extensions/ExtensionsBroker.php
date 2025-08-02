<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\AnyMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

class ExtensionsBroker
{
    /** @var PropertyTypeExtension[] */
    private array $propertyTypeExtensions;

    /** @var MethodReturnTypeExtension[] */
    private array $methodReturnTypeExtensions;

    /** @var AnyMethodReturnTypeExtension[] */
    private array $anyMethodReturnTypeExtensions;

    /** @var MethodCallExceptionsExtension[] */
    private array $methodCallExceptionsExtensions;

    /** @var StaticMethodReturnTypeExtension[] */
    private array $staticMethodReturnTypeExtensions;

    /** @var FunctionReturnTypeExtension[] */
    private array $functionReturnTypeExtensions;

    /** @var AfterClassDefinitionCreatedExtension[] */
    private array $afterClassDefinitionCreatedExtensions;

    /** @var AfterSideEffectCallAnalyzed[] */
    private array $afterSideEffectCallAnalyzedExtensions;

    /** @var TypeResolverExtension[] */
    private array $typeResolverExtensions;

    /**
     * @var class-string<InferExtension>[]
     */
    private array $priorities = [];

    public function __construct(public readonly array $extensions = [])
    {
        $this->buildExtensions();
    }

    /**
     * @param  class-string<InferExtension>[]  $priority
     */
    public function priority(array $priority): self
    {
        $this->priorities = array_merge($this->priorities, $priority);

        $this->buildExtensions();

        return $this;
    }

    private function buildExtensions(): void
    {
        $extensions = $this->sortExtensionsInOrder($this->extensions, $this->priorities);

        $this->propertyTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof PropertyTypeExtension;
        });

        $this->methodReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof MethodReturnTypeExtension;
        });

        $this->anyMethodReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof AnyMethodReturnTypeExtension;
        });

        $this->methodCallExceptionsExtensions = array_filter($extensions, function ($e) {
            return $e instanceof MethodCallExceptionsExtension;
        });

        $this->staticMethodReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof StaticMethodReturnTypeExtension;
        });

        $this->functionReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof FunctionReturnTypeExtension;
        });

        $this->afterClassDefinitionCreatedExtensions = array_filter($extensions, function ($e) {
            return $e instanceof AfterClassDefinitionCreatedExtension;
        });

        $this->afterSideEffectCallAnalyzedExtensions = array_filter($extensions, function ($e) {
            return $e instanceof AfterSideEffectCallAnalyzed;
        });

        $this->typeResolverExtensions = array_filter($extensions, function ($e) {
            return $e instanceof TypeResolverExtension;
        });
    }

    /**
     * @param  InferExtension[]  $arrayToSort
     * @param  class-string<InferExtension>[]  $arrayToSortWithItems
     * @return InferExtension[]
     */
    private function sortExtensionsInOrder(array $arrayToSort, array $arrayToSortWithItems): array
    {
        // 1) Figure out which items match any of the given “order” patterns
        $isMatched = []; // parallel boolean array
        $matchedItems = []; // will collect items for sorting
        foreach ($arrayToSort as $item) {
            $found = false;
            foreach ($arrayToSortWithItems as $pattern) {
                if ($item::class === $pattern) {
                    $found = true;
                    break;
                }
            }
            $isMatched[] = $found;
            if ($found) {
                $matchedItems[] = $item;
            }
        }

        // 2) Sort the matched-items list by the order of patterns
        usort($matchedItems, function ($a, $b) use ($arrayToSortWithItems) {
            $rank = array_flip($arrayToSortWithItems);
            // Find the first pattern each item matches
            $getRank = function ($item) use ($rank) {
                foreach ($rank as $pattern => $idx) {
                    if ($item::class === $pattern) {
                        return $idx;
                    }
                }

                return PHP_INT_MAX; // fallback (should not happen)
            };

            return $getRank($a) <=> $getRank($b);
        });

        // 3) Rebuild the final array
        $result = [];
        $mIndex = 0;
        foreach ($arrayToSort as $i => $item) {
            if ($isMatched[$i]) {
                // pull from the sorted‐matches list
                $result[] = $matchedItems[$mIndex++];
            } else {
                // untouched item
                $result[] = $item;
            }
        }

        return $result;
    }

    public function getPropertyType($event): ?Type
    {
        foreach ($this->propertyTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getInstance())) {
                continue;
            }

            if ($propertyType = $extension->getPropertyType($event)) {
                return $propertyType;
            }
        }

        return null;
    }

    public function getMethodReturnType($event): ?Type
    {
        foreach ($this->methodReturnTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getInstance())) {
                continue;
            }

            if ($returnType = $extension->getMethodReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }

    /**
     * @return Type[]
     */
    public function getMethodCallExceptions($event): array
    {
        $exceptions = [];

        foreach ($this->methodCallExceptionsExtensions as $extension) {
            if (! $extension->shouldHandle($event->getInstance())) {
                continue;
            }

            if ($extensionExceptions = $extension->getMethodCallExceptions($event)) {
                $exceptions = array_merge($exceptions, $extensionExceptions);
            }
        }

        return $exceptions;
    }

    public function getStaticMethodReturnType($event): ?Type
    {
        foreach ($this->staticMethodReturnTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getCallee())) {
                continue;
            }

            if ($returnType = $extension->getStaticMethodReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }

    public function getFunctionReturnType($event): ?Type
    {
        foreach ($this->functionReturnTypeExtensions as $extension) {
            if (! $extension->shouldHandle($event->getName())) {
                continue;
            }

            if ($returnType = $extension->getFunctionReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }

    public function getResolvedType(ReferenceResolutionEvent $event): ?Type
    {
        if ($event->type instanceof ObjectType && is_a($event->type->name, ResolvingType::class, true)) {
            if ($type = app($event->type->name)->resolve($event)) {
                return $type;
            }
        }

        foreach ($this->typeResolverExtensions as $extension) {
            if ($type = $extension->resolve($event)) {
                return $type;
            }
        }

        return $event->type;
    }

    public function afterClassDefinitionCreated($event): void
    {
        foreach ($this->afterClassDefinitionCreatedExtensions as $extension) {
            if (! $extension->shouldHandle($event->name)) {
                continue;
            }

            $extension->afterClassDefinitionCreated($event);
        }
    }

    public function afterSideEffectCallAnalyzed(SideEffectCallEvent $event): void
    {
        foreach ($this->afterSideEffectCallAnalyzedExtensions as $extension) {
            if (! $extension->shouldHandle($event)) {
                continue;
            }

            $extension->afterSideEffectCallAnalyzed($event);
        }
    }

    public function getAnyMethodReturnType(AnyMethodCallEvent $event): ?Type
    {
        foreach ($this->anyMethodReturnTypeExtensions as $extension) {
            if ($returnType = $extension->getMethodReturnType($event)) {
                return $returnType;
            }
        }

        return null;
    }
}
