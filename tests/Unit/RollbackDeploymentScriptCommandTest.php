<?php

namespace Brainstud\LaravelDeploymentScripts\Tests\Unit;

use Brainstud\LaravelDeploymentScripts\Tests\TestCase;
use Illuminate\Support\Facades\File;

class RollbackDeploymentScriptCommandTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = $this->app->databasePath() . DIRECTORY_SEPARATOR . 'deployment_scripts';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    private function getMigrationName($index = 0)
    {
        return str_replace('.php', '', File::files($this->folder)[$index]->getBasename());
    }

    public function testRollingBackWithoutDeploymentScripts()
    {
        $this->artisan('deployment-script:rollback')
            ->assertSuccessful()
            ->expectsOutput('Nothing to rollback.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 0);
    }

    public function testRollingBackScriptTwice()
    {
        $this->artisan('deployment-script:make test')->assertSuccessful();
        $this->artisan('deployment-script:execute')->assertSuccessful();

        $this->artisan('deployment-script:rollback')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to rollback.');

        $this->artisan('deployment-script:rollback')
            ->assertSuccessful()
            ->expectsOutput('Nothing to rollback.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 0);
    }

    public function testRollingBackTwoScripts()
    {
        $this->artisan('deployment-script:make test-a')->assertSuccessful();
        $this->artisan('deployment-script:make test-b')->assertSuccessful();
        $this->artisan('deployment-script:execute')->assertSuccessful();

        $this->artisan('deployment-script:rollback')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to rollback.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 0);
    }

    public function testRollingBackTwoBatches()
    {
        $this->artisan('deployment-script:make test-a')->assertSuccessful();
        $this->artisan('deployment-script:execute')->assertSuccessful();
        $this->artisan('deployment-script:make test-b')->assertSuccessful();
        $this->artisan('deployment-script:execute')->assertSuccessful();

        $this->artisan('deployment-script:rollback')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to rollback.');


        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 1);
        $this->assertDatabaseHas(config('deployment-scripts.table_name'), [
            'deployment_script' => $this->getMigrationName(),
            'batch' => 1
        ]);

        $this->assertDatabaseMissing(config('deployment-scripts.table_name'), [
            'batch' => 2
        ]);

        $this->artisan('deployment-script:rollback')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to rollback.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 0);
        $this->assertDatabaseMissing(config('deployment-scripts.table_name'), [
            'batch' => 1
        ]);
    }

    public function testRollingBackDeletedScript()
    {
        $this->artisan('deployment-script:make test')->assertSuccessful();
        $this->artisan('deployment-script:execute')->assertSuccessful();
        $migrationName = $this->getMigrationName();
        File::deleteDirectory($this->folder);

        $this->artisan('deployment-script:rollback')
            ->assertSuccessful()
            ->expectsOutput('Migration not found: ' . $migrationName);

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 1);
    }

}
