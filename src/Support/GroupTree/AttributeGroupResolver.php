<?php

namespace Dedoc\Scramble\Support\GroupTree;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Routing\Route;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class AttributeGroupResolver implements GroupResolverStrategy
{
    public function resolve(Operation $operation, Route $route): ?array
    {
        $controller = $route->getAction('controller');
        if (! is_string($controller)) {
            return null;
        }

        try {
            $parts = explode('@', $controller, 2);
            $className = $parts[0];
            $methodName = $parts[1] ?? null;

            if (! class_exists($className)) {
                return null;
            }

            $classRef = new ReflectionClass($className);
            $classGroupAttr = $classRef->getAttributes(Group::class)[0] ?? null;

            $methodGroupAttr = null;
            if ($methodName !== null) {
                $methodRef = new ReflectionMethod($className, $methodName);
                $methodGroupAttr = $methodRef->getAttributes(Group::class)[0] ?? null;
            }

            if (! $classGroupAttr && ! $methodGroupAttr) {
                return null;
            }

            $path = array_merge(
                $this->pathFromAttribute($classGroupAttr),
                $this->pathFromAttribute($methodGroupAttr),
            );

            return $path === [] ? null : $path;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  ReflectionAttribute<Group>|null  $attribute
     * @return list<string>
     */
    private function pathFromAttribute(?ReflectionAttribute $attribute): array
    {
        if ($attribute === null) {
            return [];
        }

        $group = $attribute->newInstance();

        $path = $group->parent !== null ? GroupPathSplitter::split($group->parent) : [];

        if ($group->name !== null && $group->name !== '') {
            $path[] = $group->name;
        }

        return $path;
    }
}
