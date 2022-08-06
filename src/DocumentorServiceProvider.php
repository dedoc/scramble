<?php

namespace Dedoc\Documentor;

use Dedoc\Documentor\Commands\DocumentorCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DocumentorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('documentor')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasViews('documentor')
            ->hasMigration('create_documentor_table')
            ->hasCommand(DocumentorCommand::class);
    }
}
