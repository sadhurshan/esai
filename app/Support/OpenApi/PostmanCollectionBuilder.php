<?php

namespace App\Support\OpenApi;

class PostmanCollectionBuilder
{
    /**
     * Transform an OpenAPI document into a Postman v2.1 collection array.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function build(array $spec): array
    {
        $info = $spec['info'] ?? [];
        $paths = $spec['paths'] ?? [];

        $collection = [
            'info' => [
                'name' => ($info['title'] ?? 'API').' Collection',
                'version' => $info['version'] ?? 'dev',
                'description' => $info['summary'] ?? ($info['description'] ?? ''),
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{authToken}}',
                        'type' => 'string',
                    ],
                ],
            ],
            'variable' => [
                [
                    'key' => 'baseUrl',
                    'value' => 'http://localhost/api',
                    'type' => 'string',
                ],
                [
                    'key' => 'authToken',
                    'value' => '',
                    'type' => 'string',
                ],
                [
                    'key' => 'apiKey',
                    'value' => '',
                    'type' => 'string',
                ],
            ],
            'item' => [],
        ];

        foreach ($paths as $path => $definition) {
            $definition = is_array($definition) ? $definition : [];
            $commonParameters = $this->normaliseParameters($definition['parameters'] ?? []);

            foreach ($definition as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $methodLower = strtolower((string) $method);

                if (! in_array($methodLower, ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'], true)) {
                    continue;
                }

                $operationParameters = array_merge($commonParameters, $this->normaliseParameters($operation['parameters'] ?? []));

                $item = [
                    'name' => $operation['summary'] ?? strtoupper($methodLower).' '.$path,
                    'request' => [
                        'method' => strtoupper($methodLower),
                        'header' => $this->defaultHeaders($operation),
                        'url' => $this->buildUrlObject($path, $operationParameters),
                    ],
                ];

                if (isset($operation['description'])) {
                    $item['request']['description'] = $operation['description'];
                }

                if (isset($operation['requestBody']) && is_array($operation['requestBody'])) {
                    $body = $this->buildRequestBody($operation['requestBody']);

                    if ($body !== null) {
                        $item['request']['body'] = $body;
                    }
                }

                $collection['item'][] = $item;
            }
        }

        return $collection;
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $parameters
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normaliseParameters(array $parameters): array
    {
        $normalised = [
            'path' => [],
            'query' => [],
            'header' => [],
        ];

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            $location = $parameter['in'] ?? null;
            $name = $parameter['name'] ?? null;

            if (! $location || ! $name) {
                continue;
            }

            $entry = [
                'key' => $name,
                'description' => $parameter['description'] ?? null,
                'required' => (bool) ($parameter['required'] ?? false),
            ];

            $normalised[$location][] = $entry;
        }

        return $normalised;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<int, array<string, mixed>>
     */
    private function defaultHeaders(array $operation): array
    {
        $headers = [
            [
                'key' => 'Authorization',
                'value' => 'Bearer {{authToken}}',
                'type' => 'text',
            ],
            [
                'key' => 'X-API-Key',
                'value' => '{{apiKey}}',
                'type' => 'text',
                'disabled' => true,
            ],
        ];

        $parameters = $this->normaliseParameters($operation['parameters'] ?? []);

        foreach ($parameters['header'] ?? [] as $header) {
            $headers[] = [
                'key' => $header['key'],
                'value' => '',
                'type' => 'text',
                'description' => $header['description'] ?? null,
                'disabled' => ($header['required'] ?? false) === false,
            ];
        }

        return $headers;
    }

    /**
     * @param  string  $path
     * @param  array<string, array<int, array<string, mixed>>>  $parameters
     * @return array<string, mixed>
     */
    private function buildUrlObject(string $path, array $parameters): array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $segments = array_map(fn (string $segment): string => (string) preg_replace('/\{(.+?)\}/', ':$1', $segment), $segments);

        $url = [
            'raw' => '{{baseUrl}}'.$path,
            'host' => ['{{baseUrl}}'],
            'path' => $segments,
        ];

        if (($parameters['query'] ?? []) !== []) {
            $url['query'] = array_map(fn (array $parameter): array => [
                'key' => $parameter['key'],
                'value' => '',
                'description' => $parameter['description'] ?? null,
                'disabled' => ($parameter['required'] ?? false) === false,
            ], $parameters['query']);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $requestBody
     * @return array<string, mixed>|null
     */
    private function buildRequestBody(array $requestBody): ?array
    {
        $content = $requestBody['content'] ?? [];

        if (isset($content['application/json'])) {
            $example = $content['application/json']['example'] ?? null;

            if ($example === null && isset($content['application/json']['examples']) && is_array($content['application/json']['examples'])) {
                $first = reset($content['application/json']['examples']);

                if (is_array($first) && isset($first['value'])) {
                    $example = $first['value'];
                }
            }

            $raw = $example !== null ? json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : "{}";

            if ($raw === false) {
                $raw = "{}";
            }

            return [
                'mode' => 'raw',
                'raw' => $raw,
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        if (isset($content['multipart/form-data'])) {
            $schema = $content['multipart/form-data']['schema']['properties'] ?? [];

            $formData = [];

            foreach ($schema as $name => $property) {
                $formData[] = [
                    'key' => (string) $name,
                    'type' => ($property['format'] ?? null) === 'binary' ? 'file' : 'text',
                    'value' => '',
                ];
            }

            return [
                'mode' => 'formdata',
                'formdata' => $formData,
            ];
        }

        return null;
    }
}
