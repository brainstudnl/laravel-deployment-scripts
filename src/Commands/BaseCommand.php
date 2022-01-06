<?php

namespace Brainstud\LaravelDeploymentScripts\Commands;

use Illuminate\Console\Command;

class BaseCommand extends Command
{
    protected function getDeploymentScriptsPath(): string
    {
        return $this->laravel->databasePath() . DIRECTORY_SEPARATOR . 'deployment_scripts';
    }
}
