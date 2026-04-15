<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Attributes\Parameter as ParameterAttribute;
use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\ComposedFormRequestRulesEvaluator;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Illuminate\Support\Arr;
use PhpParser\PrettyPrinter;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

class FormRequestParametersExtractor implements ParameterExtractor
{
    use CreatesParametersFromAttributes;
    use GeneratesParametersFromRules;

    /**
     * @var class-string<mixed>[]
     */
    private static array $ignoredInstancesOf = [];

    public function __construct(
        private PrettyPrinter $printer,
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $requestClassName = $this->getFormRequestClassName($routeInfo)) {
            return $parameterExtractionResults;
        }

        if ($this->isIgnored($requestClassName)) {
            return $parameterExtractionResults;
        }

        $parameterExtractionResults[] = $this->extractFormRequestParameters($requestClassName, $routeInfo);

        return $parameterExtractionResults;
    }

    /**
     * @param  class-string<mixed>|class-string<mixed>[]  $ignoredClasses
     */
    public static function ignoreInstanceOf(string|array $ignoredClasses): void
    {
        $ignoredClasses = Arr::wrap($ignoredClasses);

        foreach ($ignoredClasses as $ignoredClass) {
            if (! in_array($ignoredClass, self::$ignoredInstancesOf, true)) {
                self::$ignoredInstancesOf[] = $ignoredClass;
            }
        }
    }

    private function getFormRequestClassName(RouteInfo $routeInfo): ?string
    {
        if (! $reflectionAction = $routeInfo->reflectionAction()) {
            return null;
        }

        /** @var ReflectionParameter $requestParam */
        if (! $requestParam = collect($reflectionAction->getParameters())->first($this->isCustomRequestParam(...))) {
            return null;
        }

        $requestClassName = $requestParam->getType()->getName();

        $reflectionClass = new ReflectionClass($requestClassName);

        // If the classname is actually an interface, it may be bound to the container.
        if (! $reflectionClass->isInstantiable() && app()->bound($requestClassName)) {
            $classInstance = app()->getBindings()[$requestClassName]['concrete'](app());
            $requestClassName = $classInstance::class;
        }

        return $requestClassName;
    }

    private function isCustomRequestParam(ReflectionParameter $reflectionParameter): bool
    {
        if (! $reflectionParameter->getType() instanceof ReflectionNamedType) {
            return false;
        }

        $className = $reflectionParameter->getType()->getName();

        return method_exists($className, 'rules');
    }

    private function isIgnored(string $className): bool
    {
        foreach (self::$ignoredInstancesOf as $ignoredClass) {
            if (is_a($className, $ignoredClass, true)) {
                return true;
            }
        }

        return false;
    }

    public function extractFormRequestParameters(string $requestClassName, RouteInfo $routeInfo): ParametersExtractionResult
    {
        $classReflector = Infer\Reflector\ClassReflector::make($requestClassName);
        $reflection = $classReflector->getReflection();

        $phpDocReflector = SchemaClassDocReflector::createFromDocString($reflection->getDocComment() ?: '');

        $schemaNameAttr = ($reflection->getAttributes(SchemaName::class)[0] ?? null)?->newInstance();

        $schemaName = ($phpDocReflector->getTagValue('@ignoreSchema')->value ?? null) !== null
            ? null
            : ($schemaNameAttr
                ? ($schemaNameAttr->input ?? $schemaNameAttr->name)
                : $phpDocReflector->getSchemaName($requestClassName));

        $inferredParameters = $this->makeParameters(
            rules: (new ComposedFormRequestRulesEvaluator($this->printer, $classReflector, $routeInfo->method))->handle(),
            typeTransformer: $this->openApiTransformer,
            rulesDocsRetriever: new TypeBasedRulesDocumentationRetriever(
                $routeInfo->getScope(),
                new MethodCallReferenceType(new ObjectType($requestClassName), 'rules', []),
            ),
            in: in_array(mb_strtolower($routeInfo->method), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
                ? 'query'
                : 'body',
        );

        $classAttributes = $reflection->getAttributes(ParameterAttribute::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($classAttributes) {
            $inferredResult = new ParametersExtractionResult($inferredParameters);

            $attributeParameters = collect($classAttributes)
                ->map(fn (ReflectionAttribute $ra) => $this->createParameter([$inferredResult], $ra->newInstance(), $ra->getArguments()))
                ->all();

            $attributeKeys = collect($attributeParameters)->map(fn ($p) => "$p->name.$p->in")->all();

            $inferredParameters = collect($inferredParameters)
                ->filter(fn ($p) => ! in_array("$p->name.$p->in", $attributeKeys))
                ->values()
                ->all();

            $inferredParameters = [...$inferredParameters, ...$attributeParameters];
        }

        return new ParametersExtractionResult(
            parameters: $inferredParameters,
            schemaName: $schemaName,
            description: $phpDocReflector->getDescription(),
        );
    }
}
