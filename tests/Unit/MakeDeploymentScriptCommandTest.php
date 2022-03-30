<?php

namespace Brainstud\LaravelDeploymentScripts\Tests\Unit;

use Brainstud\LaravelDeploymentScripts\DeploymentScript;
use Brainstud\LaravelDeploymentScripts\Tests\TestCase;
use Illuminate\Support\Facades\File;

class MakeDeploymentScriptCommandTest extends TestCase
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

    public function testMakeDeploymentScript()
    {
        $this->artisan('deployment-script:make test-a')->assertSuccessful();

        $this->assertCount(1, File::files($this->folder));

        $script = File::getRequire(File::files($this->folder)[0]->getPathname());
        $this->assertInstanceOf(DeploymentScript::class, $script);
        $this->assertTrue(method_exists($script, 'up'));
        $this->assertTrue(method_exists($script, 'down'));

    }

    public function testMakeDuplicateDeploymentScript()
    {
        $this->artisan('deployment-script:make test-b')->assertSuccessful();
        $this->artisan('deployment-script:make test-b')->assertSuccessful();
        $this->assertCount(1, File::files($this->folder));
    }

    public function testMakeMultipleDeploymentScripts()
    {
        $this->artisan('deployment-script:make test-c')->assertSuccessful();
        $this->artisan('deployment-script:make test-d')->assertSuccessful();
        $this->assertCount(2, File::files($this->folder));
    }
}
