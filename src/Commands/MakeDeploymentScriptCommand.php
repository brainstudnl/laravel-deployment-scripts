<?php

namespace Brainstud\LaravelDeploymentScripts\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeDeploymentScriptCommand extends BaseCommand
{
    public $signature = 'deployment-script:make {name}';

    public $description = 'Create a new deployment script class';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle(): int
    {
        $path = $this->getTargetPath();

        $this->makeDirectory(dirname($path));

        $contents = $this->getStubContent();

        if (!$this->files->exists($path)) {
            $this->files->put($path, $contents);
            $this->info("Deployment script: {$path} created");
        } else {
            $this->info("Deployment script: {$path} already exits");
        }

        return self::SUCCESS;
    }

    /**
     * Get the content of the stub file
     *
     * @return bool|string
     */
    public function getStubContent(): bool|string
    {
        return file_get_contents(__DIR__ . '/../../stubs/deployment-script.php.stub');
    }

    /**
     * Get the full path of generated script
     *
     * @return string
     */
    public function getTargetPath(): string
    {
        return $this->getDeploymentScriptsPath()
            . DIRECTORY_SEPARATOR
            . date('Y_m_d_Hni')
            . '_'
            . Str::snake(($this->argument('name')))
            . '.php';
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param string $path
     * @return string
     */
    protected function makeDirectory(string $path): string
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0777, true, true);
        }

        return $path;
    }
}
