<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Support\Infer\Handler\ReturnTypeGettingExtensions;
use Dedoc\Scramble\Support\InferExtensions\AnonymousResourceCollectionTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\PhpDocTypeInfer;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScrambleServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        ReturnTypeGettingExtensions::$extensions[] = AnonymousResourceCollectionTypeInfer::class;
        ReturnTypeGettingExtensions::$extensions[] = JsonResourceTypeInfer::class;
        ReturnTypeGettingExtensions::$extensions[] = PhpDocTypeInfer::class;

        $package
            ->name('scramble')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasViews('scramble');
    }
}
