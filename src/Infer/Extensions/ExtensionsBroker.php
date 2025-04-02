<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;

class ExtensionsBroker
{
    /** @var PropertyTypeExtension[] */
    private array $propertyTypeExtensions;

    /** @var MethodReturnTypeExtension[] */
    private array $methodReturnTypeExtensions;

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

    public function __construct(public readonly array $extensions = [])
    {
        $this->propertyTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof PropertyTypeExtension;
        });

        $this->methodReturnTypeExtensions = array_filter($extensions, function ($e) {
            return $e instanceof MethodReturnTypeExtension;
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
    }

    public function getPropertyType($event)
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

    public function getMethodReturnType($event)
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

    public function getMethodCallExceptions($event)
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

    public function getStaticMethodReturnType($event)
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

    public function getFunctionReturnType($event)
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

    public function afterClassDefinitionCreated($event)
    {
        foreach ($this->afterClassDefinitionCreatedExtensions as $extension) {
            if (! $extension->shouldHandle($event->name)) {
                continue;
            }

            $extension->afterClassDefinitionCreated($event);
        }
    }

    public function afterSideEffectCallAnalyzed(SideEffectCallEvent $event)
    {
        foreach ($this->afterSideEffectCallAnalyzedExtensions as $extension) {
            if (! $extension->shouldHandle($event)) {
                continue;
            }

            $extension->afterSideEffectCallAnalyzed($event);
        }
    }
}
