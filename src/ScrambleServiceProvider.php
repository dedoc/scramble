<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\BuiltInExtensions\AnonymousResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\BuiltInExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\BuiltInExtensions\LengthAwarePaginatorTypeToSchema;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Infer\Infer;
use Dedoc\Scramble\Support\InferExtensions\AnonymousResourceCollectionTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\PhpDocTypeInfer;
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

        $this->app->bind(Infer::class, function () {
            $extensions = config('scramble.extensions', []);

            $expressionTypeInferringExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, ExpressionTypeInferExtension::class, true),
            ));

            return new Infer(array_merge($expressionTypeInferringExtensions, [
                AnonymousResourceCollectionTypeInfer::class,
                JsonResourceTypeInfer::class,
                PhpDocTypeInfer::class,
            ]));
        });

        $this->app->bind(TypeTransformer::class, function () {
            $extensions = config('scramble.extensions', []);

            $typesToSchemaExtensions = array_values(array_filter(
                $extensions,
                fn ($e) => is_a($e, TypeToSchemaExtension::class, true),
            ));

            return new TypeTransformer(
                $this->app->make(Infer::class),
                new Components,
                array_merge($typesToSchemaExtensions, [
                    JsonResourceTypeToSchema::class,
                    AnonymousResourceCollectionTypeToSchema::class,
                    LengthAwarePaginatorTypeToSchema::class,
                ]),
            );
        });
    }
}
