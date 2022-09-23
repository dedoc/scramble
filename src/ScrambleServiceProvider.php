<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Infer;
use Dedoc\Scramble\Infer\TypeInferringVisitor;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\ClassAstHelper;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceStaticCallsTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\PhpDocTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\ResponseFactoryTypeInfer;
use Dedoc\Scramble\Support\InferHandlers\ModelClassHandler;
use Dedoc\Scramble\Support\InferHandlers\PhpDocHandler;
use Dedoc\Scramble\Support\OperationBuilder;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RequestEssentialsExtension;
use Dedoc\Scramble\Support\OperationExtensions\ResponseExtension;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\AnonymousResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EloquentCollectionToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EnumToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\LengthAwarePaginatorTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ModelToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponseTypeToSchema;
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

        $this->app->when([Infer::class, ClassAstHelper::class, TypeInferringVisitor::class])
            ->needs('$extensions')
            ->give(function () {
                $extensions = config('scramble.extensions', []);

                $expressionTypeInferringExtensions = array_values(array_filter(
                    $extensions,
                    fn ($e) => is_a($e, ExpressionTypeInferExtension::class, true),
                ));

                return array_merge($expressionTypeInferringExtensions, [
                    JsonResourceStaticCallsTypeInfer::class,
                    JsonResourceTypeInfer::class,
                    ResponseFactoryTypeInfer::class,
                ]);
            });

        $this->app->when([Infer::class, ClassAstHelper::class, TypeInferringVisitor::class])
            ->needs('$handlers')
            ->give(function () {
                return [
//                    new PhpDocHandler(),
//                    new ModelClassHandler(),
                ];
            });

        $this->app->singleton(TypeTransformer::class, function () {
            $extensions = config('scramble.extensions', []);

            $typesToSchemaExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, TypeToSchemaExtension::class, true),
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
            );
        });

        $this->app->bind(OperationBuilder::class, function () {
            $extensions = config('scramble.extensions', []);

            $operationExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, OperationExtension::class, true),
            ));

            $extensions = array_merge([
                RequestEssentialsExtension::class,
                RequestBodyExtension::class,
                ResponseExtension::class,
            ], $operationExtensions);

            return new OperationBuilder(
                array_map(fn ($extensionClass) => $this->app->make($extensionClass), $extensions),
            );
        });
    }
}
