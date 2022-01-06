<?php

namespace Brainstud\LaravelDeploymentScripts;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class DeploymentScript
{
    /**
     * Run the deployment script
     */
    abstract public function up(): void;

    /**
     * Roll back the deployment script
     */
    abstract public function down(): void;

    /**
     * The name of the database connection to use.
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * Enables, if supported, wrapping the deployment script within a transaction.
     *
     * @var bool
     */
    public bool $withinTransaction = true;

    /**
     * Get the deployment script connection name.
     *
     * @return string|null
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Execute a given command
     *
     * @param string $command
     * @param array $parameters
     * @return int Exit code
     */
    protected function command(string $command, array $parameters = []): int
    {
        return Artisan::call($command, $parameters);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    protected function query(string $query, array $bindings = []): bool
    {
        return DB::connection($this->getConnection())->statement($query, $bindings);
    }

    /**
     * Execute a given closure
     *
     * @param Closure $closure
     */
    protected function closure(Closure $closure)
    {
        $closure();
    }
}
