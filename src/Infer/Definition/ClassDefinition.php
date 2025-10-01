<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use LogicException;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;

class ClassDefinition implements ClassDefinitionContract
{
    public function __construct(
        // FQ name
        public string $name,
        /** @var TemplateType[] $templateTypes */
        public array $templateTypes = [],
        /** @var array<string, ClassPropertyDefinition> $properties */
        public array $properties = [],
        /** @var array<string, FunctionLikeDefinition> $methods */
        public array $methods = [],
        public ?string $parentFqn = null,
    ) {}

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function isChildOf(string $className)
    {
        return $this->isInstanceOf($className) && $this->name !== $className;
    }

    public function hasMethodDefinition(string $name): bool
    {
        return $this->lazilyLoadMethodDefinition($name) !== null;
    }

    public function getMethodDefinitionWithoutAnalysis(string $name): ?FunctionLikeDefinition
    {
        return $this->lazilyLoadMethodDefinition($name);
    }

    protected array $loadedMethods = [];

    protected function lazilyLoadMethodDefinition(string $name): ?FunctionLikeDefinition
    {
        if (array_key_exists($name, $this->loadedMethods)) {
            return $this->methods[$name] ?? null;
        }

        $this->loadedMethods[$name] = true;

        if ($this instanceof ShallowClassDefinition) {
            return $this->methods[$name] ?? null;
        }

        /** @var \ReflectionMethod|null $reflectionMethod */
        $reflectionMethod = rescue(
            fn () => (new \ReflectionClass($this->name))->getMethod($name),
            report: false,
        );

        if (! $reflectionMethod) {
            return $this->methods[$name] ?? null;
        }

        if (
            $reflectionMethod->class === $this->name
            || Scramble::infer()->config->shouldAnalyzeAst($reflectionMethod->class)
        ) {
            return $this->methods[$reflectionMethod->name] = new FunctionLikeDefinition(
                new FunctionType(
                    $reflectionMethod->name,
                    arguments: [],
                    returnType: new UnknownType,
                ),
                definingClassName: $reflectionMethod->class,
                isStatic: $reflectionMethod->isStatic(),
            );
        }

        return $this->methods[$name] ?? null;
    }

    public function getMethodDefiningClassName(string $name, Index $index)
    {
        $lastLookedUpClassName = $this->name;
        while ($lastLookedUpClassDefinition = $index->getClass($lastLookedUpClassName)) {
            if ($methodDefinition = $lastLookedUpClassDefinition->getMethodDefinitionWithoutAnalysis($name)) {
                return $methodDefinition->definingClassName;
            }

            if ($lastLookedUpClassDefinition->parentFqn) {
                $lastLookedUpClassName = $lastLookedUpClassDefinition->parentFqn;

                continue;
            }

            break;
        }

        return $lastLookedUpClassName;
    }

    /**
     * @param  IndexBuilder<array<string, mixed>>[]  $indexBuilders
     */
    public function getMethodDefinition(string $name, Scope $scope = new GlobalScope, array $indexBuilders = [], bool $withSideEffects = false): ?FunctionLikeDefinition
    {
        if (! $methodDefinition = $this->lazilyLoadMethodDefinition($name)) {
            return $this->getFunctionLikeDefinitionBuiltFromReflection($name);
        }

        return $this->getFunctionLikeDefinitionBuiltFromAst($methodDefinition, $name, $scope, $indexBuilders, $withSideEffects);
    }

    private function findReflectionMethod(string $name): ?\ReflectionMethod
    {
        /** @var \ReflectionClass|null $classReflection */
        $classReflection = rescue(fn () => new \ReflectionClass($this->name), report: false);
        /** @var \ReflectionMethod|null $methodReflection */
        $methodReflection = rescue(fn () => $classReflection?->getMethod($name), report: false);

        // The case when method is defined in the class or its parents.
        if ($methodReflection) {
            return $methodReflection;
        }
//
//        foreach ($this->getClassContexts()->keys() as $class) {
//            /** @var \ReflectionClass|null $classReflection */
//            $classReflection = rescue(fn () => new \ReflectionClass($class), report: false);
//            /** @var \ReflectionMethod|null $methodReflection */
//            $methodReflection = rescue(fn () => $classReflection?->getMethod($name), report: false);
//
//            if ($methodReflection) {
//                return $methodReflection;
//            }
//        }

        return null;
    }

    protected function getFunctionLikeDefinitionBuiltFromReflection(string $name): ?FunctionLikeDefinition
    {
        if (array_key_exists($name, $this->methods)) {
            return $this->methods[$name];
        }

        if (! $methodReflection = $this->findReflectionMethod($name)) {
            return null;
        }

        return $this->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $methodReflection,
            collect([]),
//            collect($this->templateTypes)->keyBy->name
//                ->merge($this->getMethodContextTemplates($methodReflection)),
        ))->build();
    }

    protected function getFunctionLikeDefinitionBuiltFromAst(
        FunctionLikeDefinition $methodDefinition,
        string $name,
        Scope $scope = new GlobalScope,
        array $indexBuilders = [],
        bool $withSideEffects = false,
    )
    {
        if (! $methodDefinition->isFullyAnalyzed()) {
            $this->methods[$name] = (new MethodAnalyzer(
                $scope->index,
                $this,
            ))->analyze($methodDefinition, $indexBuilders, $withSideEffects);
        }

        if (! $this->methods[$name]->referencesResolved) {
            $methodScope = new Scope(
                $scope->index,
                new NodeTypesResolver,
                new ScopeContext($this, $methodDefinition),
                new FileNameResolver(
                    class_exists($this->name)
                        ? ClassReflector::make($this->name)->getNameContext()
                        : tap(new NameContext(new Throwing), fn (NameContext $nc) => $nc->startNamespace()),
                ),
            );

            static::resolveFunctionReturnReferences($methodScope, $this->methods[$name]);

            static::resolveFunctionExceptions($methodScope, $this->methods[$name]);

            $this->methods[$name]->referencesResolved = true;
        }

        return $this->methods[$name];
    }

    public static function resolveFunctionExceptions(Scope $scope, FunctionLikeDefinition $functionLikeDefinition): void
    {
        $functionType = $functionLikeDefinition->type;

        foreach ($functionType->exceptions as $i => $exceptionType) { // @phpstan-ignore property.notFound
            $functionType->exceptions[$i] = (new ReferenceTypeResolver($scope->index))
                ->resolve($scope, $exceptionType);
        }
    }

    public static function resolveFunctionReturnReferences(Scope $scope, FunctionLikeDefinition $functionLikeDefinition): void
    {
        $functionType = $functionLikeDefinition->type;

        $returnType = $functionType->getReturnType();
        $resolvedReference = ReferenceTypeResolver::getInstance()->resolve($scope, $returnType);
        $functionType->setReturnType($resolvedReference);

        if ($annotatedReturnType = $functionType->getAttribute('annotatedReturnType')) {
            if (! $functionType->getAttribute('inferredReturnType')) {
                $functionType->setAttribute('inferredReturnType', clone $functionType->getReturnType());
            }

            $functionType->setReturnType(
                self::addAnnotatedReturnType($functionType->getReturnType(), $annotatedReturnType, $scope)
            );
        }
    }

    private static function addAnnotatedReturnType(Type $inferredReturnType, Type $annotatedReturnType, Scope $scope): Type
    {
        $types = $inferredReturnType instanceof Union
            ? $inferredReturnType->types
            : [$inferredReturnType];

        // @todo: Handle case when annotated return type is union.
        if ($annotatedReturnType instanceof ObjectType) {
            $resolvedName = ReferenceTypeResolver::resolveClassName($scope, $annotatedReturnType->name);
            if (! $resolvedName) {
                throw new LogicException("Got null after class name resolution of [$annotatedReturnType->name], string expected");
            }
            $annotatedReturnType->name = $resolvedName;
        }

        $annotatedTypeCanAcceptAnyInferredType = collect($types)
            ->some(function (Type $t) use ($annotatedReturnType) {
                $isAnnotatedAsArray = $annotatedReturnType instanceof ArrayType
                    || $annotatedReturnType instanceof KeyedArrayType;

                if ($isAnnotatedAsArray && $t instanceof LateResolvingType) {
                    return true;
                }

                if ($t instanceof TemplateType && ! $t->is) {
                    return true;
                }

                if ($annotatedReturnType->accepts($t)) {
                    return true;
                }

                return $t->acceptedBy($annotatedReturnType);
            });

        if (! $annotatedTypeCanAcceptAnyInferredType) {
            return $annotatedReturnType;
        }

        return Union::wrap($types)->mergeAttributes($inferredReturnType->attributes());
    }

    public function getPropertyDefinition($name)
    {
        return $this->properties[$name] ?? null;
    }

    public function hasPropertyDefinition(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    public function getMethodCallType(string $name)
    {
        return $this->getMethodDefinition($name)?->getReturnType()
            ?: new UnknownType("Cannot get type of calling method [$name] on object [$this->name]");
    }

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        return $this->getMethodDefinition($name);
    }

    public function getData(): ClassDefinition
    {
        return $this;
    }
}
