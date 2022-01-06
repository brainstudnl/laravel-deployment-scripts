<?php

namespace Brainstud\LaravelDeploymentScripts\Commands;

use Brainstud\LaravelDeploymentScripts\Deployments\Deployer;

class ExecuteDeploymentScriptCommand extends BaseCommand
{
    public $signature = 'deployment-script:execute {--database= : The database connection to use}';

    public $description = 'Execute the deployment scripts';

    protected ?string $connection = null;

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
            ->run([$this->getDeploymentScriptsPath()]);

        return self::SUCCESS;
    }
}
