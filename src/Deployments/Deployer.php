<?php

namespace Brainstud\LaravelDeploymentScripts\Deployments;

use Brainstud\LaravelDeploymentScripts\DeploymentScript;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Deployer
{
    private Filesystem $files;

    private DeploymentRepository $repository;

    private ?string $connection;

    private Resolver $resolver;

    private ?OutputInterface $output;

    public function __construct(Filesystem $files, Resolver $resolver, DeploymentRepository $deploymentRepository)
    {
        $this->files = $files;

        $this->repository = $deploymentRepository;
        $this->resolver = $resolver;
    }

    /**
     * Execute all pending deployment scripts
     *
     * @throws FileNotFoundException|Throwable
     */
    public function run(array $paths): array
    {
        $this->requireFiles($deploymentScripts = $this->pendingDeploymentScripts(
            $this->getDeploymentScripts($paths),
            $this->repository->getRan(),
        ));

        $this->runDeploymentScripts($deploymentScripts);

        return $deploymentScripts;
    }

    /**
     * Rollback the last deployment batch
     *
     * @throws Throwable
     */
    public function rollback(array $paths): array
    {
        $deploymentScriptLogs = $this->repository->getLast();

        if (count($deploymentScriptLogs) === 0) {
            $this->note('<info>Nothing to rollback.</info>');
            return [];
        }

        return $this->rollbackDeploymentScripts($deploymentScriptLogs, $paths);
    }


    /**
     * Require all the files in a given path.
     *
     * @param array $files
     * @return void
     * @throws FileNotFoundException
     */
    public function requireFiles(array $files)
    {
        foreach ($files as $file) {
            $this->files->requireOnce($file);
        }
    }

    /**
     * Rollback the given deployment scripts.
     *
     * @param array $logs
     * @param array $paths
     * @return array
     * @throws FileNotFoundException
     * @throws Throwable
     */
    protected function rollbackDeploymentScripts(array $logs, array $paths): array
    {
        $rolledBack = [];

        $this->requireFiles($files = $this->getDeploymentScripts($paths));

        foreach ($logs as $log) {
            $log = (object)$log;

            if (!$file = Arr::get($files, $log->deployment_script)) {
                $this->note("<fg=red>Migration not found:</> {$log->deployment_script}");

                continue;
            }

            $rolledBack[] = $file;

            $this->runDown($file, $log);
        }

        return $rolledBack;
    }

    /**
     * Get the deployment scripts that have not yet run.
     *
     * @param array $files
     * @param array $ran
     * @return array
     */
    protected function pendingDeploymentScripts(array $files, array $ran): array
    {
        return Collection::make($files)
            ->reject(function ($file) use ($ran) {
                return in_array($this->getDeploymentScriptName($file), $ran);
            })->values()->all();
    }

    /**
     * Get the name of the deployment script.
     *
     * @param string $path
     * @return string
     */
    protected function getDeploymentScriptName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Get all deployment scripts in the given paths
     *
     * @param array $paths
     * @return array
     */
    protected function getDeploymentScripts(array $paths): array
    {
        return Collection::make($paths)->flatMap(function ($path) {
            return Str::endsWith($path, '.php') ? [$path] : $this->files->glob($path . '/*_*.php');
        })->filter()->values()->keyBy(function ($file) {
            return $this->getDeploymentScriptName($file);
        })->sortBy(function ($file, $key) {
            return $key;
        })->all();
    }

    /**
     * @throws Throwable
     * @throws FileNotFoundException
     */
    protected function runDeploymentScripts(array $files)
    {
        if (count($files) === 0) {
            $this->note('<info>Nothing to migrate.</info>');
            return;
        }

        $batch = $this->repository->getNextBatchNumber();

        foreach ($files as $file) {
            $this->runUp($file, $batch);
        }
    }

    /**
     * @throws FileNotFoundException
     * @throws Throwable
     */
    protected function runUp($file, $batch)
    {
        $deploymentScript = $this->resolvePath($file);
        $name = $this->getDeploymentScriptName($file);

        if (!$deploymentScript instanceof DeploymentScript) {
            $this->note("<fg=red>Deployment script {$name} is not an instance of DeploymentScript</>");
            return;
        }

        $this->note("<comment>Executing deployment script:</comment> {$name}");

        $startTime = microtime(true);
        $this->runDeploymentScript($deploymentScript, 'up');
        $runTime = number_format((microtime(true) - $startTime) * 1000, 2);

        $this->repository->log($name, $batch);

        $this->note("<info>Executed:</info>  {$name} ({$runTime}ms)");
    }

    /**
     * @throws FileNotFoundException
     * @throws Throwable
     */
    protected function runDown($file, $deploymentScript)
    {
        $instance = $this->resolvePath($file);
        $name = $this->getDeploymentScriptName($file);

        if (!$instance instanceof DeploymentScript) {
            $this->note("<fg=red>Deployment script {$name} is not an instance of DeploymentScript</>");
        }

        $this->note("<comment>Rolling back deployment script:</comment> {$name}");

        $startTime = microtime(true);
        $this->runDeploymentScript($instance, 'down');
        $runTime = number_format((microtime(true) - $startTime) * 1000, 2);

        $this->repository->delete($deploymentScript);

        $this->note("<info>Rolled back:</info>  {$name} ({$runTime}ms)");
    }

    /**
     * Resolve a deployment script instance from a path.
     *
     * @param string $path
     * @return object
     * @throws FileNotFoundException
     */
    protected function resolvePath(string $path): object
    {
        $class = $this->getDeploymentScriptClass($this->getDeploymentScriptName($path));

        if (class_exists($class) && realpath($path) == (new ReflectionClass($class))->getFileName()) {
            return new $class;
        }

        $deploymentScript = $this->files->getRequire($path);

        return is_object($deploymentScript) ? $deploymentScript : new $class;
    }

    /**
     * Run a deployment script inside a transaction if the database supports it.
     *
     * @param DeploymentScript $deploymentScript
     * @param string $method
     * @return void
     * @throws Throwable
     */
    protected function runDeploymentScript(DeploymentScript $deploymentScript, string $method): void
    {
        $connection = $this->resolveConnection(
            $deploymentScript->getConnection()
        );

        $callback = function () use ($deploymentScript, $method) {
            if (method_exists($deploymentScript, $method)) {
                $deploymentScript->{$method}();
            }
        };

        $this->getSchemaGrammar($connection)->supportsSchemaTransactions() && $deploymentScript->withinTransaction
            ? $connection->transaction($callback)
            : $callback();
    }


    /**
     * Generate a deployment script class name based on the file name.
     *
     * @param string $deploymentScriptName
     * @return string
     */
    protected function getDeploymentScriptClass(string $deploymentScriptName): string
    {
        return Str::studly(implode('_', array_slice(explode('_', $deploymentScriptName), 4)));
    }

    /**
     * Resolve the database connection instance.
     *
     * @param string|null $connection
     * @return ConnectionInterface
     */
    public function resolveConnection(?string $connection): ConnectionInterface
    {
        return $this->resolver->connection($connection ?: $this->connection);
    }


    /**
     * Get the schema grammar out of a deployment scripts connection.
     *
     * @param ConnectionInterface $connection
     * @return Grammar
     */
    protected function getSchemaGrammar(ConnectionInterface $connection): Grammar
    {
        if (is_null($grammar = $connection->getSchemaGrammar())) {
            $connection->useDefaultSchemaGrammar();

            $grammar = $connection->getSchemaGrammar();
        }

        return $grammar;
    }

    /**
     * Write a note to the console's output.
     *
     * @param string $message
     * @return void
     */
    protected function note(string $message)
    {
        $this->output?->writeln($message);
    }

    public function setConnection(?string $name): static
    {
        if (!is_null($name)) {
            $this->resolver->setDefaultConnection($name);
        }

        $this->repository->setSource($name);

        $this->connection = $name;

        return $this;
    }


    /**
     * Set the output implementation that should be used by the console.
     *
     * @param OutputInterface $output
     * @return $this
     */
    public function setOutput(OutputInterface $output): static
    {
        $this->output = $output;

        return $this;
    }
}
