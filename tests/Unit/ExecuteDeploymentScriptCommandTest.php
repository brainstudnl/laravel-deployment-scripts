<?php

namespace Brainstud\LaravelDeploymentScripts\Tests\Unit;

use Brainstud\LaravelDeploymentScripts\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ExecuteDeploymentScriptCommandTest extends TestCase
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

    public function testExecutingWithoutDeploymentScripts()
    {
        $this->artisan('deployment-script:execute')
            ->assertSuccessful()
            ->expectsOutput('Nothing to migrate.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 0);
    }

    public function testExecutingSingleDeploymentScriptTwice()
    {
        $this->artisan('deployment-script:make test')->assertSuccessful();

        $this->artisan('deployment-script:execute')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to migrate.');

        $this->artisan('deployment-script:execute')
            ->assertSuccessful()
            ->expectsOutput('Nothing to migrate.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 1);
        $this->assertDatabaseHas(config('deployment-scripts.table_name'), [
            'deployment_script' => str_replace('.php', '', File::files($this->folder)[0]->getBasename()),
            'batch' => 1
        ]);
    }

    public function testExecutingTwoDeploymentScripts()
    {
        $this->artisan('deployment-script:make test-a')->assertSuccessful();
        $this->artisan('deployment-script:make test-b')->assertSuccessful();

        $this->artisan('deployment-script:execute')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to migrate.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 2);
        $this->assertDatabaseHas(config('deployment-scripts.table_name'), [
            'deployment_script' => str_replace('.php', '', File::files($this->folder)[0]->getBasename()),
            'batch' => 1
        ]);
        $this->assertDatabaseHas(config('deployment-scripts.table_name'), [
            'deployment_script' => str_replace('.php', '', File::files($this->folder)[1]->getBasename()),
            'batch' => 1
        ]);
    }

    public function testExecutingTwoBatches()
    {
        $this->artisan('deployment-script:make test-a')->assertSuccessful();

        $this->artisan('deployment-script:execute')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to migrate.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 1);
        $this->assertDatabaseHas(config('deployment-scripts.table_name'), [
            'deployment_script' => str_replace('.php', '', File::files($this->folder)[0]->getBasename()),
            'batch' => 1
        ]);

        $this->artisan('deployment-script:make test-b')->assertSuccessful();

        $this->artisan('deployment-script:execute')
            ->assertSuccessful()
            ->doesntExpectOutput('Nothing to migrate.');

        $this->assertDatabaseCount(config('deployment-scripts.table_name'), 2);
        $this->assertDatabaseHas(config('deployment-scripts.table_name'), [
            'deployment_script' => str_replace('.php', '', File::files($this->folder)[1]->getBasename()),
            'batch' => 2
        ]);
    }

    public function testDeployingNonDeploymentScript()
    {
        File::makeDirectory($this->folder);
        File::put($this->folder . '/0000_00_00_000000_test.php', "<?php class Test {}");
        $this->artisan('deployment-script:execute')
            ->assertSuccessful()
            ->expectsOutput("Deployment script 0000_00_00_000000_test is not an instance of DeploymentScript");
    }

}
