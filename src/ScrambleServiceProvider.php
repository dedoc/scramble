<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer\Extensions\InferExtension;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\AuthorizationExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\NotFoundExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\ValidationExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\InferHandlers\ModelClassHandler;
use Dedoc\Scramble\Support\InferHandlers\PhpDocHandler;
use Dedoc\Scramble\Support\OperationBuilder;
use Dedoc\Scramble\Support\OperationExtensions\ErrorResponsesExtension;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RequestEssentialsExtension;
use Dedoc\Scramble\Support\OperationExtensions\ResponseExtension;
use Dedoc\Scramble\Support\ServerFactory;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\AnonymousResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EloquentCollectionToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EnumToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\LengthAwarePaginatorTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ModelToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponseTypeToSchema;
use PhpParser\ParserFactory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScrambleServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scramble')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasViews('scramble');

        $this->app->singleton(FileParser::class, function () {
            return new FileParser(
                (new ParserFactory)->create(ParserFactory::PREFER_PHP7)
            );
        });

        $this->app->singleton(ProjectAnalyzer::class, function () {
            return new ProjectAnalyzer(
                $this->app->make(FileParser::class),
                extensions: $this->getInferExtensions(),
                handlers: [new PhpDocHandler(), new ModelClassHandler()],
                index: new Index,
            );
        });

        $this->app->singleton(Infer::class, function () {
            return new Infer($this->app->make(ProjectAnalyzer::class));
        });

        $this->app->when(OperationBuilder::class)
            ->needs('$extensionsClasses')
            ->give(function () {
                $extensions = config('scramble.extensions', []);

                $operationExtensions = array_values(array_filter(
                    $extensions,
                    fn ($e) => is_a($e, OperationExtension::class, true),
                ));

                return array_merge([
                    RequestEssentialsExtension::class,
                    RequestBodyExtension::class,
                    ErrorResponsesExtension::class,
                    ResponseExtension::class,
                ], $operationExtensions);
            });

        $this->app->singleton(ServerFactory::class);

        $this->app->singleton(TypeTransformer::class, function () {
            $extensions = config('scramble.extensions', []);

            $typesToSchemaExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, TypeToSchemaExtension::class, true),
            ));

            $exceptionToResponseExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, ExceptionToResponseExtension::class, true),
            ));

            return new TypeTransformer(
                $this->app->make(Infer::class),
                new Components,
                array_merge($typesToSchemaExtensions, [
                    EnumToSchema::class,
                    JsonResourceTypeToSchema::class,
                    ModelToSchema::class,
                    EloquentCollectionToSchema::class,
                    AnonymousResourceCollectionTypeToSchema::class,
                    LengthAwarePaginatorTypeToSchema::class,
                    ResponseTypeToSchema::class,
                ]),
                array_merge($exceptionToResponseExtensions, [
                    ValidationExceptionToResponseExtension::class,
                    AuthorizationExceptionToResponseExtension::class,
                    NotFoundExceptionToResponseExtension::class,
                    HttpExceptionToResponseExtension::class,
                ]),
            );
        });
    }

    private function getInferExtensions()
    {
        $extensions = config('scramble.extensions', []);

        $inferExtensionsClasses = array_values(array_filter(
            $extensions,
            fn ($e) => is_a($e, InferExtension::class, true),
        ));
        $inferExtensions = array_map(
            fn ($inferExtensionClass) => new $inferExtensionClass(),
            $inferExtensionsClasses,
        );

        return array_merge($inferExtensions, DefaultExtensions::infer());
    }
}
