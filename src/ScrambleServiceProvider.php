<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Configuration\GeneratorConfigCollection;
use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Console\Commands\AnalyzeDocumentation;
use Dedoc\Scramble\Console\Commands\ExportDocumentation;
use Dedoc\Scramble\DocumentTransformers\AddDocumentTags;
use Dedoc\Scramble\DocumentTransformers\CleanupUnusedResponseReferencesTransformer;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\ExtensionsBroker;
use Dedoc\Scramble\Infer\Extensions\IndexBuildingBroker;
use Dedoc\Scramble\Infer\Extensions\InferExtension;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\AuthenticationExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\AuthorizationExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\NotFoundExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\ValidationExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\IndexBuilders\PaginatorsCandidatesBuilder;
use Dedoc\Scramble\Support\InferExtensions\AbortHelpersExceptionInfer;
use Dedoc\Scramble\Support\InferExtensions\AfterAnonymousResourceCollectionDefinitionCreatedExtension;
use Dedoc\Scramble\Support\InferExtensions\AfterJsonResourceDefinitionCreatedExtension;
use Dedoc\Scramble\Support\InferExtensions\AfterResourceCollectionDefinitionCreatedExtension;
use Dedoc\Scramble\Support\InferExtensions\ArrayMergeReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\EloquentBuilderExtension;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceExtension;
use Dedoc\Scramble\Support\InferExtensions\JsonResponseMethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\ModelExtension;
use Dedoc\Scramble\Support\InferExtensions\PaginateMethodsReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\PossibleExceptionInfer;
use Dedoc\Scramble\Support\InferExtensions\RequestExtension;
use Dedoc\Scramble\Support\InferExtensions\ResourceCollectionTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\ResourceResponseMethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\ResponseFactoryTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\ResponseMethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\ShallowFunctionDefinition;
use Dedoc\Scramble\Support\InferExtensions\TypeTraceInfer;
use Dedoc\Scramble\Support\InferExtensions\ValidatorTypeInfer;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\VoidType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\BinaryFileResponseToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\CollectionToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\CursorPaginatorTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EloquentCollectionToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EnumToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\LengthAwarePaginatorTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ModelToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PaginatedResourceResponseTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PaginatorTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResourceResponseTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponseTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\StreamedResponseToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\VoidTypeToSchema;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScrambleServiceProvider extends PackageServiceProvider
{
    public $singletons = [
        PrettyPrinter::class => PrettyPrinter\Standard::class,
        GeneratorConfigCollection::class => GeneratorConfigCollection::class,
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('scramble')
            ->hasConfigFile()
            ->hasCommand(ExportDocumentation::class)
            ->hasCommand(AnalyzeDocumentation::class)
            ->hasViews('scramble');

        $this->app->singleton(FileParser::class, function () {
            return new FileParser(
                (new ParserFactory)->createForHostVersion()
            );
        });

        $this->app->singleton(LazyShallowReflectionIndex::class, function () {
            return new LazyShallowReflectionIndex(
                // Abort helpers are handled in the extension and these definitions are needed to avoid leaking the
                // annotated exceptions to the caller's definitions.
                functions: [
                    'abort' => $abortType = new FunctionLikeDefinition(type: new FunctionType('abort', returnType: new VoidType)),
                    'abort_if' => $abortType,
                    'abort_unless' => $abortType,
                    'throw_if' => $throwType = new FunctionLikeDefinition(type: new FunctionType('throw_if', returnType: new VoidType)),
                    'throw_unless' => $throwType,
                ]
            );
        });

        $this->app->singleton(Index::class, function () {
            $index = new Index;
            foreach ((require __DIR__.'/../dictionaries/classMap.php') ?: [] as $className => $serializedClassDefinition) {
                $index->classesDefinitions[$className] = unserialize($serializedClassDefinition);
            }

            $templates = [$tValue = new TemplateType('TValue')];
            $index->functionsDefinitions['tap'] = new ShallowFunctionDefinition(
                type: tap(new FunctionType(
                    name: 'tap',
                    arguments: [
                        'value' => $tValue,
                    ],
                    returnType: $tValue,
                ), function (FunctionType $type) use ($templates) {
                    $type->templates = $templates;
                })
            );

            return $index;
        });

        $this->app->singleton(Infer::class);

        $this->app->when(ExtensionsBroker::class)
            ->needs('$extensions')
            ->give(function () {
                $extensions = array_merge(config('scramble.extensions', []), Scramble::$extensions);

                $inferExtensionsClasses = array_values(array_filter(
                    $extensions,
                    fn ($e) => is_a($e, InferExtension::class, true),
                ));

                $inferExtensionsClasses = array_merge($inferExtensionsClasses, [
                    ResponseMethodReturnTypeExtension::class,
                    JsonResourceExtension::class,
                    ResourceResponseMethodReturnTypeExtension::class,
                    JsonResponseMethodReturnTypeExtension::class,
                    ModelExtension::class,
                    EloquentBuilderExtension::class,
                    RequestExtension::class,
                    AfterJsonResourceDefinitionCreatedExtension::class,
                    AfterResourceCollectionDefinitionCreatedExtension::class,
                    AfterAnonymousResourceCollectionDefinitionCreatedExtension::class,
                ]);

                return array_merge(
                    [
                        new PossibleExceptionInfer,
                        new AbortHelpersExceptionInfer,

                        new PaginateMethodsReturnTypeExtension,

                        new ValidatorTypeInfer,
                        new ResourceCollectionTypeInfer,
                        new ResponseFactoryTypeInfer,

                        new ArrayMergeReturnTypeExtension,

                        /* Keep this extension last, so the trace info is preserved. */
                        new TypeTraceInfer,
                    ],
                    array_map(function ($class) {
                        return app($class);
                    }, $inferExtensionsClasses)
                );
            });

        $this->app->when(IndexBuildingBroker::class)
            ->needs('$indexBuilders')
            ->give(function () {
                $extensions = array_merge(config('scramble.extensions', []), Scramble::$extensions);

                $indexBuilders = array_values(array_filter(
                    $extensions,
                    fn ($e) => is_a($e, IndexBuilder::class, true),
                ));

                return array_map(function ($class) {
                    return app($class);
                }, array_merge([PaginatorsCandidatesBuilder::class], $indexBuilders));
            });

        $this->app->bind(TypeTransformer::class, function (Application $application, array $parameters) {
            $extensions = array_merge(config('scramble.extensions', []), Scramble::$extensions);

            $typesToSchemaExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, TypeToSchemaExtension::class, true),
            ));

            $exceptionToResponseExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, ExceptionToResponseExtension::class, true),
            ));

            return new TypeTransformer(
                $parameters['infer'] ?? $application->make(Infer::class),
                $parameters['context'],
                typeToSchemaExtensionsClasses: $parameters['typeToSchemaExtensions'] ?? array_merge([
                    EnumToSchema::class,
                    JsonResourceTypeToSchema::class,
                    ModelToSchema::class,
                    CollectionToSchema::class,
                    EloquentCollectionToSchema::class,
                    ResourceCollectionTypeToSchema::class,
                    CursorPaginatorTypeToSchema::class,
                    PaginatorTypeToSchema::class,
                    LengthAwarePaginatorTypeToSchema::class,
                    ResponseTypeToSchema::class,
                    BinaryFileResponseToSchema::class,
                    StreamedResponseToSchema::class,
                    ResourceResponseTypeToSchema::class,
                    PaginatedResourceResponseTypeToSchema::class,
                    VoidTypeToSchema::class,
                ], $typesToSchemaExtensions),
                exceptionToResponseExtensionsClasses: $parameters['exceptionToResponseExtensions'] ?? array_merge([
                    ValidationExceptionToResponseExtension::class,
                    AuthorizationExceptionToResponseExtension::class,
                    AuthenticationExceptionToResponseExtension::class,
                    NotFoundExceptionToResponseExtension::class,
                    HttpExceptionToResponseExtension::class,
                ], $exceptionToResponseExtensions),
            );
        });
    }

    public function bootingPackage()
    {
        Scramble::configure()
            ->useConfig(config('scramble'))
            ->withOperationTransformers(function (OperationTransformers $transformers) {
                $extensions = array_merge(config('scramble.extensions', []), Scramble::$extensions);

                $operationExtensions = array_values(array_filter(
                    $extensions,
                    fn ($e) => is_a($e, OperationExtension::class, true),
                ));

                $transformers->append($operationExtensions);
            })
            ->withDocumentTransformers([
                AddDocumentTags::class,
                CleanupUnusedResponseReferencesTransformer::class,
            ]);

        if (Scramble::$defaultRoutesIgnored) {
            Scramble::configure()->expose(false);
        }

        $this->app->booted(function () {
            $this->registerRoutes();
        });
    }

    private function registerRoutes(): void
    {
        foreach (Scramble::getConfigurationsInstance()->all() as $api => $generatorConfig) {
            /** @var Router $router */
            $router = $this->app->get(Router::class);

            if ($generatorConfig->uiRoute) {
                $cb = is_callable($generatorConfig->uiRoute)
                    ? $generatorConfig->uiRoute
                    : fn ($router, $action) => $router->get($generatorConfig->uiRoute, $action);

                $cb($router, function (Generator $generator) use ($api) {
                    $config = Scramble::getGeneratorConfig($api);

                    return view('scramble::docs', [
                        'spec' => $generator($config),
                        'config' => $config,
                    ]);
                })->middleware($generatorConfig->get('middleware', [RestrictedDocsAccess::class]));
            }

            if ($generatorConfig->documentRoute) {
                $cb = is_callable($generatorConfig->documentRoute)
                    ? $generatorConfig->documentRoute
                    : fn ($router, $action) => $router->get($generatorConfig->documentRoute, $action);

                $cb($router, function (Generator $generator) use ($api) {
                    $config = Scramble::getGeneratorConfig($api);

                    return response()->json($generator($config), options: JSON_PRETTY_PRINT);
                })->middleware($generatorConfig->get('middleware', [RestrictedDocsAccess::class]));
            }
        }
    }
}
