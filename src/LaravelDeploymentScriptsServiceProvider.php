<?php

namespace Brainstud\LaravelDeploymentScripts;

use Brainstud\LaravelDeploymentScripts\Commands\ExecuteDeploymentScriptCommand;
use Brainstud\LaravelDeploymentScripts\Commands\MakeDeploymentScriptCommand;
use Brainstud\LaravelDeploymentScripts\Commands\RollbackDeploymentScriptCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelDeploymentScriptsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-deployment-scripts')
            ->hasConfigFile()
            ->hasMigration('create_laravel-deployment-scripts_table')
            ->hasCommands([
                MakeDeploymentScriptCommand::class,
                ExecuteDeploymentScriptCommand::class,
                RollbackDeploymentScriptCommand::class,
            ]);
    }
}
