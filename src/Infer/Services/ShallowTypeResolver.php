<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use ReflectionClass;
use ReflectionFunction;

class ShallowTypeResolver
{
    public function __construct(
        private Index $index,
        private FileNameResolver $nameResolver,
    ) {}

    public function resolve(Type $type): Type
    {
        return (new TypeWalker)->map(
            $type,
            $this->doResolve(...),
            function (Type $t) {
                $nodes = $t->nodes();
                /*
                 * When mapping function type, we don't want to affect arguments of the function types, just the return type.
                 */
                if ($t instanceof FunctionType) {
                    return [];
                }

                return $nodes;
            },
        );
    }

    private function doResolve(Type $type): Type
    {
        return match (true) {
            $type instanceof SelfType => $type,
            $type instanceof PropertyFetchReferenceType => $this->resolvePropertyFetchReferenceType($type),
            $type instanceof MethodCallReferenceType => $this->resolveMethodCallReferenceType($type),
            $type instanceof StaticMethodCallReferenceType => $this->resolveStaticMethodCallReferenceType($type),
            default => $type,
        };
    }

    private function resolvePropertyFetchReferenceType(PropertyFetchReferenceType $type): Type
    {
        $callee = $this->resolve($type->object);
        if (! $callee instanceof ObjectType) {
            return new UnknownType('fetching a property on a non-object');
        }

        $definition = $this->index->getClassDefinition($callee->name);
        if (! $definition) {
            return new UnknownType('cannot find a definition of '.$callee->name);
        }

        $propertyDefinition = $definition->properties[$type->propertyName] ?? null;
        if (! $propertyDefinition) {
            return new UnknownType("property [{$type->propertyName}] is not found on object [{$callee->name}]");
        }

        $propertyType = $propertyDefinition->type ?: $propertyDefinition->defaultType;
        if ($propertyType instanceof TemplateType) {
            $propertyType = $propertyType->is;
        }

        if (! $propertyType) {
            return new MixedType;
        }

        return $propertyType;
    }

    private function resolveMethodCallReferenceType(MethodCallReferenceType $type): Type
    {
        $callee = $this->resolve($type->callee);
        if (! $callee instanceof ObjectType) {
            return new UnknownType('calling a method on a non-object');
        }

        $definition = $this->index->getClassDefinition($callee->name);
        if (! $definition) {
            return new UnknownType('cannot find a definition of '.$callee->name);
        }

        $methodDefinition = $definition->methods[$type->methodName] ?? null;
        if (! $methodDefinition) {
            return new UnknownType("method [{$type->methodName}] is not found on object [{$callee->name}]");
        }

        if (! $methodDefinition->isFullyAnalyzed()) { // avoid overriding type inference (?)
            $this->attachShallowMethodData($methodDefinition);
        }

        return $methodDefinition->type->returnType;
    }

    private function resolveStaticMethodCallReferenceType(StaticMethodCallReferenceType $type): Type
    {
        $class = $type->callee instanceof Type
            ? $this->resolve($type->callee)
            : $type->callee;

        if ($class instanceof LiteralStringType) {
            $class = $class->value;
        }

        if (! is_string($class)) {
            return new UnknownType;
        }

        $definition = $this->index->getClassDefinition($class);
        if (! $definition) {
            return new UnknownType('cannot find a definition of '.$class);
        }

        $methodDefinition = $definition->methods[$type->methodName] ?? null;
        if (! $methodDefinition) {
            return new UnknownType("method [{$type->methodName}] is not found on object [{$class}]");
        }

        if (! $methodDefinition->isFullyAnalyzed()) { // avoid overriding type inference (?)
            $this->attachShallowMethodData($methodDefinition);
        }

        return $methodDefinition->type->returnType;
    }

    private function attachShallowMethodData(FunctionLikeDefinition $definition)
    {
        if ($definition->type->getAttribute('shallowSeen')) {
            return;
        }
        $definition->type->setAttribute('shallowSeen', true);

        $reflection = $definition->definingClassName
            ? (new ReflectionClass($definition->definingClassName))->getMethod($definition->type->name)
            : new ReflectionFunction($definition->type->name);

        $handleStatic = fn (Type $type) => tap($type, function (Type $type) use ($definition) {
            if ($type instanceof ObjectType) {
                $type->name = ltrim($type->name, '\\');
            }
            if ($type instanceof ObjectType && $type->name === 'static' && $definition->definingClassName) {
                $type->name = $definition->definingClassName;
            }
        });

        if ($reflection->getReturnType()) {
            $definition->type->returnType = $handleStatic(TypeHelper::createTypeFromReflectionType($reflection->getReturnType()));
        }

        $nameResolver = $definition->definingClassName
            ? new FileNameResolver(ClassReflector::make($definition->definingClassName)->getNameContext())
            : $this->nameResolver;

        $phpDoc = PhpDoc::parse($reflection->getDocComment() ?: '/** */', $nameResolver);
        foreach ($phpDoc->getThrowsTagValues() as $throwsTagValue) {
            $definition->type->exceptions[] = $handleStatic(PhpDocTypeHelper::toType($throwsTagValue->type));
        }
    }
}
