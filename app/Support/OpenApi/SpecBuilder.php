<?php

namespace App\Support\OpenApi;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class SpecBuilder
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * Compile the root OpenAPI spec and fragments into a single associative array.
     *
     * @return array<string, mixed>
     */
    public function compile(): array
    {
        $rootPath = $this->rootSpecPath();

        if (! $this->files->exists($rootPath)) {
            throw new RuntimeException("OpenAPI root spec not found at {$rootPath}");
        }

        $spec = $this->parseYaml($rootPath);

        $fragmentOrder = Arr::get($spec, 'x-fragments.order', []);
        $fragments = $this->resolveFragmentPaths($fragmentOrder);

        foreach ($fragments as $fragmentPath) {
            $fragment = $this->parseYaml($fragmentPath);
            $spec = $this->mergeRecursiveDistinct($spec, $fragment);
        }

        unset($spec['x-fragments']);

        $spec = $this->sortSpec($spec);

        return $spec;
    }

    /**
     * Validate the compiled spec with cebe/php-openapi and return the error list.
     *
     * @param  array<string, mixed>  $spec
     * @return array<int, string>
     */
    public function validate(array $spec): array
    {
        $validationSpec = $spec;

        $rawVersion = $spec['openapi'] ?? null;

        if (is_string($rawVersion) && str_starts_with($rawVersion, '3.1')) {
            // cebe/php-openapi only validates 3.0.x, so downgrade the version just for validation.
            $validationSpec['openapi'] = '3.0.3';
        }

        $json = json_encode($validationSpec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Unable to encode OpenAPI document to JSON.');
        }

        /** @var OpenApi $document */
        $document = Reader::readFromJson($json, OpenApi::class, true);

        if ($document->validate()) {
            return [];
        }

        return $document->getErrors();
    }

    public function rootSpecPath(): string
    {
        return base_path('docs/openapi/openapi.yaml');
    }

    public function fragmentDirectory(): string
    {
        return base_path('docs/openapi/fragments');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $path): array
    {
        try {
            $contents = $this->files->get($path);
        } catch (FileNotFoundException $exception) {
            throw new RuntimeException("Failed to read fragment at {$path}: {$exception->getMessage()}");
        }

        $parsed = Yaml::parse($contents);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param  array<int, string>|string|null  $order
     * @return array<int, string>
     */
    private function resolveFragmentPaths(array|string|null $order): array
    {
        $directory = $this->fragmentDirectory();

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        if (is_array($order) && $order !== []) {
            return collect($order)
                ->map(fn (string $filename): string => $directory.DIRECTORY_SEPARATOR.trim($filename))
                ->filter(fn (string $path): bool => $this->files->exists($path))
                ->values()
                ->all();
        }

        return collect($this->files->files($directory))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.yaml'))
            ->sortBy(fn ($file) => $file->getFilename())
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $append
     * @return array<string, mixed>
     */
    private function mergeRecursiveDistinct(array $base, array $append): array
    {
        foreach ($append as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function sortSpec(array $spec): array
    {
        if (isset($spec['paths']) && is_array($spec['paths'])) {
            ksort($spec['paths']);

            foreach ($spec['paths'] as $path => $operations) {
                if (is_array($operations)) {
                    ksort($operations);
                    $spec['paths'][$path] = $operations;
                }
            }
        }

        if (isset($spec['components']) && is_array($spec['components'])) {
            foreach ($spec['components'] as $section => $entries) {
                if (is_array($entries)) {
                    ksort($entries);
                    $spec['components'][$section] = $entries;
                }
            }
        }

        return $spec;
    }
}
