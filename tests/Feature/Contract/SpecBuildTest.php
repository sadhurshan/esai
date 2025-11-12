<?php

use App\Support\OpenApi\SpecBuilder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use function Pest\Laravel\artisan;

test('OpenAPI builder compiles spec with required components', function (): void {
    /** @var SpecBuilder $builder */
    $builder = app(SpecBuilder::class);

    $fragmentsDirectory = base_path('docs/openapi/fragments');
    $fragmentFiles = (new Filesystem())->files($fragmentsDirectory);

    $yamlFragments = collect($fragmentFiles)
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.yaml'))
        ->values();

    expect($yamlFragments->all())
        ->not->toBeEmpty('Expected at least one fragment under docs/openapi/fragments.');

    $spec = $builder->compile();

    expect($spec)
        ->toBeArray()
        ->and($spec)->toHaveKey('openapi')
        ->and($spec)->toHaveKey('paths')
        ->and($spec)->toHaveKey('components');

    $validationErrors = $builder->validate($spec);
    expect($validationErrors)->toBeEmpty('Compiled spec must validate without errors.');

    $components = Arr::get($spec, 'components', []);
    expect($components)
        ->toBeArray()
        ->and($components)->toHaveKey('securitySchemes');

    $schemas = Arr::get($components, 'schemas', []);
    expect($schemas)
        ->toBeArray()
        ->and($schemas)->toHaveKeys(['SuccessEnvelope', 'ErrorEnvelope']);

    $outputPath = storage_path('api/contract-openapi.json');
    @unlink($outputPath);

    artisan('api:spec:build', ['--output' => $outputPath])->assertExitCode(0);

    $encoded = file_get_contents($outputPath);
    expect($encoded)->not->toBeFalse('Expected the generated OpenAPI JSON to be readable.');

    $decoded = json_decode($encoded, true);
    expect($decoded)
        ->toBeArray()
        ->and($decoded)->toHaveKey('paths');
});
