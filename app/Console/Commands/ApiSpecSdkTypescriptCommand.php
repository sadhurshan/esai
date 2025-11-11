<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class ApiSpecSdkTypescriptCommand extends Command
{
    protected $signature = 'api:sdk:typescript {--skip-generator : Skip running openapi-generator-cli for the fetch client}';

    protected $description = 'Generate TypeScript typings (and optional fetch client) from the compiled OpenAPI spec.';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->call('api:spec:build');

        $specPath = storage_path('api/openapi.json');

        if (! $this->files->exists($specPath)) {
            $this->error('Compiled OpenAPI document not found. Run api:spec:build first.');

            return self::FAILURE;
        }

        if (! $this->runOpenapiTypescript($specPath)) {
            return self::FAILURE;
        }

        if (! $this->option('skip-generator')) {
            if (! $this->runOpenapiGenerator($specPath)) {
                return self::FAILURE;
            }
        } else {
            $this->info('Skipping openapi-generator-cli (per --skip-generator option).');
        }

        $this->info('TypeScript SDK artifacts generated successfully.');

        return self::SUCCESS;
    }

    private function runOpenapiTypescript(string $specPath): bool
    {
        $destination = resource_path('sdk/typescript/index.d.ts');
        $this->files->ensureDirectoryExists(dirname($destination));

        $this->info('Running openapi-typescript...');

        $process = new Process([
            'npm',
            'exec',
            '--',
            'openapi-typescript',
            $specPath,
            '--output',
            $destination,
        ], base_path());

        $process->setTimeout(null);

        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('openapi-typescript command failed.');

            return false;
        }

        return true;
    }

    private function runOpenapiGenerator(string $specPath): bool
    {
        $outputDir = resource_path('sdk/ts-client/generated');

        if ($this->files->isDirectory($outputDir)) {
            $this->files->deleteDirectory($outputDir);
        }

        $this->files->ensureDirectoryExists($outputDir);

        $this->info('Running openapi-generator-cli (typescript-fetch)...');

        $process = new Process([
            'npm',
            'exec',
            '--',
            'openapi-generator-cli',
            'generate',
            '-g',
            'typescript-fetch',
            '-i',
            $specPath,
            '-o',
            $outputDir,
            '--additional-properties=useSingleRequestParameter=true,supportsES6=true,withInterfaces=true',
        ], base_path());

        $process->setTimeout(null);

        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('openapi-generator-cli command failed.');

            return false;
        }

        return true;
    }
}
