<?php

use Symfony\Component\Yaml\Yaml;

it('keeps workflow templates in sync across stacks', function (): void {
    $laravelTemplates = config('ai_workflows.templates', []);
    expect($laravelTemplates)->not()->toBeEmpty();

    $templatesPath = base_path('ai_microservice/config/workflow_templates.yaml');
    expect(is_file($templatesPath))->toBeTrue();

    $microservicePayload = Yaml::parseFile($templatesPath);
    $microserviceTemplates = $microservicePayload['templates'] ?? [];
    expect($microserviceTemplates)->not()->toBeEmpty();

    ksort($laravelTemplates);
    ksort($microserviceTemplates);

    expect(array_keys($microserviceTemplates))->toEqualCanonicalizing(array_keys($laravelTemplates));

    $normalizer = static function (array $steps): array {
        return array_map(static function (array $step): array {
            return [
                'action_type' => $step['action_type'] ?? null,
                'name' => $step['name'] ?? null,
            ];
        }, $steps);
    };

    foreach ($laravelTemplates as $templateKey => $laravelSteps) {
        $microSteps = $microserviceTemplates[$templateKey] ?? null;
        expect($microSteps)->not()->toBeNull();

        $expectedSequence = $normalizer($laravelSteps);
        $actualSequence = $normalizer($microSteps);

        expect($actualSequence)->toEqual($expectedSequence);
    }
});
