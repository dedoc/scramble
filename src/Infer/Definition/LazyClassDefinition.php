<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use ReflectionClass;

/**
 * This class definition combines the behavior of class' definitions created by both AST
 * definition builder and by lazy shallow class definition.
 *
 * Some part of this class definition may be created by the lazy shallow reflection definition builder
 * which doesn't analyze method annotation till the moment when the method definition is requested. And
 * other part of this definition may be built by AST definition builder which also has its own methods analysis
 * behavior: initially the list of methods is stored, but the method AST isn't analyzed till the very last
 * moment when method definition is requests.
 *
 * So summarizing, LazyClassDefinition will combine both of these behaviors: AST behavior is for methods of classes
 * that are not the vendor ones and LazyShallowClassDefinition behavior is for methods of classes that are the part
 * of the vendor.
 *
 * For example, if there is a concrete model class that is the part of the app, it's incomplete methods will be the
 * part of the LazyClassDefinition, and the methods of the parent class will also be available when requested.
 *
 * @todo how interfaces are handled!?
 */
class LazyClassDefinition implements ClassDefinitionContract
{
    public function __construct(
        public IndexContract $index,
        public ClassDefinitionContract $definition,
    ) {}

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        $data = $this->getData();

        if (! isset($data->methods[$name])) {
            return null;
        }

        if ($data->methods[$name]->isFullyAnalyzed) {
            return $data->methods[$name];
        }

        $reflectionMethod = (new ReflectionClass($data->name))->getMethod($name); // @phpstan-ignore argument.type

        if (! $path = $reflectionMethod->getFileName()) {
            return null;
        }

        if (Index::shouldAnalyzeAst($path)) {
            return $data->methods[$name] = $this->buildCompleteMethodDefinition($data->methods[$name]);
        }

        return $data->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $reflectionMethod,
            collect($data->templateTypes)->keyBy('name'),
        ))->build();

    }

    public function getData(): ClassDefinitionData
    {
        return $this->definition->getData();
    }

    private function buildCompleteMethodDefinition(FunctionLikeDefinition $methodDefinition): FunctionLikeDefinition
    {
        $classDefinitionData = $this->getData();

        $methodDefinition = (new MethodAnalyzer(
            $this->index,
            $classDefinitionData
        ))->analyze($methodDefinition, indexBuilders: [], withSideEffects: false);

        $methodScope = new Scope(
            $this->index,
            new NodeTypesResolver,
            new ScopeContext($classDefinitionData, $methodDefinition),
            new FileNameResolver(
                class_exists($classDefinitionData->name)
                    ? ClassReflector::make($classDefinitionData->name)->getNameContext()
                    : tap(new NameContext(new Throwing), fn (NameContext $nc) => $nc->startNamespace()),
            ),
        );

        (new ReferenceTypeResolver($this->index))
            ->resolveFunctionReturnReferences($methodScope, $methodDefinition->type);

        foreach ($methodDefinition->type->exceptions as $i => $exceptionType) {
            if (ReferenceTypeResolver::hasResolvableReferences($exceptionType)) {
                $methodDefinition->type->exceptions[$i] = (new ReferenceTypeResolver($this->index))
                    ->resolve($methodScope, $exceptionType)
                    ->mergeAttributes($exceptionType->attributes());
            }
        }

        return $methodDefinition;
    }
}
