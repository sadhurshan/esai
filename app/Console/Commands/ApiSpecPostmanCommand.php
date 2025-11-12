<?php

namespace App\Console\Commands;

use App\Support\OpenApi\PostmanCollectionBuilder;
use App\Support\OpenApi\SpecBuilder;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class ApiSpecPostmanCommand extends Command
{
    protected $signature = 'api:spec:postman {--output= : Custom output path for the generated Postman collection}';

    protected $description = 'Generate a Postman collection from the compiled OpenAPI spec.';

    public function __construct(
        private readonly SpecBuilder $builder,
        private readonly PostmanCollectionBuilder $collectionBuilder,
        private readonly Filesystem $files
    )
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
            $this->error('OpenAPI validation failed. Cannot generate Postman collection.');

            foreach ($errors as $error) {
                $this->line(" - {$error}");
            }

            return self::FAILURE;
        }

        $collection = $this->collectionBuilder->build($spec);

        $destination = $this->option('output') ?: storage_path('api/postman.json');
        $this->files->ensureDirectoryExists(dirname($destination));

        $json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->error('Failed to encode Postman collection.');

            return self::FAILURE;
        }

        $this->files->put($destination, $json);

        $this->info("Postman collection written to {$destination}");

        return self::SUCCESS;
    }

}
