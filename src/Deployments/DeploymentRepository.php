<?php

namespace Brainstud\LaravelDeploymentScripts\Deployments;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Query\Builder;

class DeploymentRepository
{
    private Resolver $resolver;

    protected ?string $connection;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getRan(): array
    {
        return $this->table()
            ->orderBy('batch')
            ->orderBy('deployment_script')
            ->pluck('deployment_script')->all();
    }

    /**
     * Log that a deployment script was run.
     *
     * @param string $file
     * @param int $batch
     * @return void
     */
    public function log(string $file, int $batch)
    {
        $record = ['deployment_script' => $file, 'batch' => $batch];

        $this->table()->insert($record);
    }

    /**
     * Remove a deployment script from the log.
     *
     * @param object $deploymentScript
     * @return void
     */
    public function delete(object $deploymentScript)
    {
        $this->table()->where('deployment_script', $deploymentScript->deployment_script)->delete();
    }

    /**
     * Get the next deployment script batch number.
     *
     * @return int
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last deployment script batch number.
     *
     * @return int
     */
    public function getLastBatchNumber(): int
    {
        return $this->table()->max('batch') ?? 0;
    }

    /**
     * Get the last deployment script batch.
     *
     * @return array
     */
    public function getLast(): array
    {
        $query = $this->table()->where('batch', $this->getLastBatchNumber());

        return $query->orderBy('deployment_script', 'desc')->get()->all();
    }

    protected function table(): Builder
    {
        return $this->getConnection()->table(config('deployment-scripts.table_name'))->useWritePdo();
    }

    /**
     * Resolve the database connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->resolver->connection($this->connection);
    }

    public function setSource(?string $name): void
    {
        $this->connection = $name;
    }
}
