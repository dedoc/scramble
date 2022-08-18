<?php

namespace Dedoc\ApiDocs;

use Dedoc\ApiDocs\Commands\ApiDocsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ApiDocsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-api-docs')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasViews('api-docs');
    }
}
