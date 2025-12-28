<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeAstDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\MixinTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\UsesTagValueNode;

class ClassDefinition implements ClassDefinitionContract
{
    private bool $propagatesTemplates = false;

    /** @var array<string, true> */
    protected array $loadedMethods = [];

    /** @var Collection<string, Collection<string, Type>> */
    private Collection $classContexts;

    private Index $index;

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

    public function propagatesTemplates(?bool $propagatesTemplates = null): bool
    {
        if ($propagatesTemplates === null) {
            return $this->propagatesTemplates;
        }

        return $this->propagatesTemplates = $propagatesTemplates;
    }

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
        return $this->lazilyLoadMethodDefinition($name) || $this->findReflectionMethod($name);
    }

    public function getMethodDefinitionWithoutAnalysis(string $name): ?FunctionLikeDefinition
    {
        return $this->lazilyLoadMethodDefinition($name);
    }

    protected function lazilyLoadMethodDefinition(string $name): ?FunctionLikeDefinition
    {
        if (array_key_exists($name, $this->loadedMethods)) {
            return $this->methods[$name] ?? null;
        }

        $this->loadedMethods[$name] = true;

        /** @var \ReflectionMethod|null $reflectionMethod */
        $reflectionMethod = rescue(
            fn () => (new \ReflectionClass($this->name))->getMethod($name), // @phpstan-ignore argument.type
            report: false,
        );

        if (! $reflectionMethod) {
            return $this->methods[$name] ?? null;
        }

        if ($this->isMethodDefinedInNonAstAnalyzableTrait($name)) {
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

    protected function isMethodDefinedInNonAstAnalyzableTrait(string $name): bool
    {
        if (! $reflectionMethod = $this->findReflectionMethod($name)) {
            return false;
        }
        /** @var \ReflectionClass<object>|null $classReflection */
        $classReflection = rescue(fn () => new \ReflectionClass($this->name), report: false); // @phpstan-ignore argument.type

        if (! $classReflection) {
            return false;
        }

        if ($this->isDeclaredIn($reflectionMethod, $classReflection)) {
            return false;
        }

        foreach (class_uses_recursive($classReflection->name) as $traitName) {
            $traitReflection = rescue(
                fn () => new \ReflectionClass($traitName), // @phpstan-ignore argument.type
                report: false,
            );

            if (! $traitReflection) {
                continue;
            }

            if (! $this->isDeclaredIn($reflectionMethod, $traitReflection)) {
                return false;
            }

            return ! Scramble::infer()->config->shouldAnalyzeAst($traitName);
        }

        return false;
    }

    /**
     * @param  \ReflectionClass<object>  $class
     */
    private function isDeclaredIn(\ReflectionMethod $reflectionMethod, \ReflectionClass $class): bool
    {
        $reflectionMethodFileName = $reflectionMethod->getFileName();
        $reflectionMethodStartLine = $reflectionMethod->getStartLine();

        $classFileName = $class->getFileName();
        $classStartLine = $class->getStartLine();
        $classEndLine = $class->getEndLine();

        if (! $reflectionMethodFileName || ! $classFileName) {
            return false;
        }

        if ($reflectionMethodFileName !== $classFileName) {
            return false;
        }

        return $reflectionMethodStartLine > $classStartLine && $reflectionMethodStartLine < $classEndLine;
    }

    private function findReflectionMethod(string $name): ?\ReflectionMethod
    {
        /** @var \ReflectionClass<object>|null $classReflection */
        $classReflection = rescue(fn () => new \ReflectionClass($this->name), report: false); // @phpstan-ignore argument.type
        /** @var \ReflectionMethod|null $methodReflection */
        $methodReflection = rescue(fn () => $classReflection?->getMethod($name), report: false);

        // The case when method is defined in the class or its parents.
        if ($methodReflection && $this->isDeclaredIn($methodReflection, $methodReflection->getDeclaringClass())) {
            return $methodReflection;
        }

        foreach ($this->getClassContexts()->keys() as $class) {
            /** @var \ReflectionClass<object>|null $classReflection */
            $classReflection = rescue(fn () => new \ReflectionClass($class), report: false); // @phpstan-ignore argument.type
            /** @var \ReflectionMethod|null $traitMethodReflection */
            $traitMethodReflection = rescue(fn () => $classReflection?->getMethod($name), report: false);

            if ($traitMethodReflection) {
                return $traitMethodReflection;
            }
        }

        return $methodReflection;
    }

    protected function getFunctionLikeDefinitionBuiltFromReflection(string $name): ?FunctionLikeDefinition
    {
        if (array_key_exists($name, $this->methods)) {
            return $this->methods[$name];
        }

        if (! $methodReflection = $this->findReflectionMethod($name)) {
            return null;
        }

        $definition = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $methodReflection,
            collect($this->templateTypes)->keyBy->name
                ->merge($this->getMethodContextTemplates($methodReflection)), // @phpstan-ignore argument.type
        ))->build();

        return $this->methods[$name] = $definition;
    }

    /**
     * @param  IndexBuilder<array<string, mixed>>[]  $indexBuilders
     */
    protected function getFunctionLikeDefinitionBuiltFromAst(
        FunctionLikeDefinition $methodDefinition,
        string $name,
        Scope $scope = new GlobalScope,
        array $indexBuilders = [],
        bool $withSideEffects = false,
    ): ?FunctionLikeDefinition {
        if (! $methodDefinition->isFullyAnalyzed()) {
            $this->methods[$name] = (new MethodAnalyzer(
                $scope->index,
                $this,
            ))->analyze($methodDefinition, $indexBuilders, $withSideEffects);
        }

        if (! $this->methods[$name]) { // @phpstan-ignore booleanNot.alwaysFalse
            return $this->methods[$name];
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

            FunctionLikeAstDefinitionBuilder::resolveFunctionReturnReferences($methodScope, $this->methods[$name]);

            FunctionLikeAstDefinitionBuilder::resolveFunctionExceptions($methodScope, $this->methods[$name]);

            $this->methods[$name]->referencesResolved = true;
        }

        return $this->methods[$name];
    }

    /**
     * @return Collection<string, Type>
     */
    private function getMethodContextTemplates(\ReflectionMethod $methodReflection): Collection
    {
        $classContexts = $this->getClassContexts();

        return $classContexts->get($methodReflection->class, collect());
    }

    /**
     * @param  string[]  $ignoreClasses
     * @return Collection<string, Collection<string, Type>>
     */
    public function getClassContexts(array $ignoreClasses = []): Collection
    {
        if (isset($this->classContexts)) {
            return $this->classContexts;
        }

        if (in_array($this->name, $ignoreClasses, true)) {
            return collect();
        }

        $reflector = ClassReflector::make($this->name);

        $docComment = rescue(fn () => $reflector->getReflection()->getDocComment(), report: false) ?: '';

        $classSource = $docComment."\n".rescue($reflector->getSource(...), '', report: false);

        /** @var Collection<int, string> $tagsDoc */
        $tagsDoc = Str::matchAll('/@(?:use|extends|mixin)\s+[^\r\n*]+/', $classSource);
        $contextPhpDoc = $tagsDoc->map(fn ($s) => " * $s")->prepend('/**')->push('*/')->join("\n");

        $nameContext = rescue($reflector->getNameContext(...), report: false);

        $phpDoc = PhpDoc::parse(
            $contextPhpDoc,
            $nameContext ? new FileNameResolver($nameContext) : null,
        );

        /** @var (ExtendsTagValueNode|UsesTagValueNode|MixinTagValueNode)[] $tags */
        $tags = [
            ...array_values($phpDoc->getExtendsTagValues()),
            ...array_values($phpDoc->getUsesTagValues()),
            ...array_values($phpDoc->getMixinTagValues()),
        ];

        $classTemplatesByName = collect($this->templateTypes)->keyBy->name;

        $types = collect($tags)
            ->map(fn ($tag) => PhpDocTypeHelper::toType($tag->type))
            ->filter(fn ($type) => $type instanceof ObjectType);

        if ($this->parentFqn && ! $types->firstWhere('name', $this->parentFqn)) {
            $types->push(new ObjectType($this->parentFqn));
        }

        $classContexts = collect();

        foreach ($types as $type) {
            $classContext = collect();
            $type = ! $type instanceof Generic ? new Generic($type->name) : $type;

            if ($definition = $this->getIndex()->getClass($type->name)) {
                foreach ($definition->templateTypes as $i => $templateType) {
                    $concreteType = (new TypeWalker)->map(
                        $type->templateTypes[$i] ?? $templateType->default ?? new UnknownType,
                        fn ($t) => $t instanceof ObjectType ? $classTemplatesByName->get($t->name, $t) : $t,
                    );

                    $classContext->offsetSet($templateType->name, $concreteType);
                }
            }

            $classContexts->offsetSet($type->name, $classContext);
        }

        foreach ($classContexts as $class => $localContext) {
            if (in_array($class, $ignoreClasses, true)) {
                continue;
            }

            if (! $classDef = $this->getIndex()->getClass($class)) {
                continue;
            }

            $classContext = $classDef->getClassContexts([...$ignoreClasses, $this->name]);

            $localizedClassContext = $classContext->map->map(function ($type) use ($localContext) {
                return (new TypeWalker)->map(
                    $type,
                    fn ($t) => $type instanceof TemplateType
                        ? $localContext->get($type->name, new UnknownType)
                        : $type,
                );
            });

            $classContexts = $localizedClassContext->merge($classContexts);
        }

        // $this->dumpContext($this, $classContexts);

        return $this->classContexts = $classContexts;
    }

    /**
     * @param  Collection<string, Collection<string, Type>>  $classContexts
     */
    private function dumpContext(ClassDefinition $def, Collection $classContexts): void // @phpstan-ignore method.unused
    {
        $className = $def->name.($def->templateTypes ? '<'.implode(',', array_map($this->dumpTemplateName(...), $def->templateTypes)).'>' : '');

        $contextNames = $classContexts->map(function ($contexts, $name) {
            $templates = $contexts->map(fn ($t) => $t instanceof TemplateType ? $this->dumpTemplateName($t) : $t->toString());

            return $name.($templates->count() ? '<'.$templates->join(',').'>' : '');
        })->values()->toArray();

        dump([
            $className => $contextNames,
        ]);
    }

    private function dumpTemplateName(TemplateType $tt): string
    {
        return $tt->name.'#'.spl_object_id($tt);
    }

    public function setIndex(Index $index): void
    {
        $this->index = $index;
    }

    protected function getIndex(): Index
    {
        return isset($this->index) ? $this->index : app(Index::class);
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
