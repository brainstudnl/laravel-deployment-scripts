<?php

namespace Brainstud\LaravelDeploymentScripts\Commands;

use Brainstud\LaravelDeploymentScripts\Deployments\Deployer;

class RollbackDeploymentScriptCommand extends BaseCommand
{
    public $signature = 'deployment-script:rollback {--database= : The database connection to use}';

    public $description = 'Roll back the deployment scripts by one iteration';

    private Deployer $deployer;

    public function __construct(Deployer $deployer)
    {
        parent::__construct();

        $this->deployer = $deployer;
    }

    public function handle(): int
    {
        $this->deployer
            ->setConnection($this->option('database'))
            ->setOutput($this->output)
            ->rollback([$this->getDeploymentScriptsPath()]);

        return self::SUCCESS;
    }
}
