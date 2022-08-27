<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Support\Infer\Handler\ReturnTypeGettingExtensions;
use Dedoc\Scramble\Support\ResponseExtractor\InferExtensions\AnonymousResourceCollectionTypeInfer;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScrambleServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        ReturnTypeGettingExtensions::$extensions[] = AnonymousResourceCollectionTypeInfer::class;

        $package
            ->name('scramble')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasViews('scramble');
    }
}
