<?php

namespace App\Console\Commands;

use App\Support\OpenApi\SpecBuilder;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class ApiSpecBuildCommand extends Command
{
    protected $signature = 'api:spec:build {--output= : Custom output path relative to the project root}';

    protected $description = 'Compile the canonical OpenAPI 3.1 document and validate it.';

    public function __construct(private readonly SpecBuilder $builder, private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $spec = $this->builder->compile();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $errors = $this->builder->validate($spec);

        if ($errors !== []) {
            $this->error('OpenAPI validation failed:');

            foreach ($errors as $error) {
                $this->line(" - {$error}");
            }

            return self::FAILURE;
        }

        $destination = $this->option('output') ?: storage_path('api/openapi.json');

        $this->files->ensureDirectoryExists(dirname($destination));

        $written = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($written === false) {
            $this->error('Failed to encode OpenAPI document to JSON.');

            return self::FAILURE;
        }

        $this->files->put($destination, $written);

        $this->info("OpenAPI spec written to {$destination}");

        return self::SUCCESS;
    }
}
