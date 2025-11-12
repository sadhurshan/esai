<?php

use App\Support\OpenApi\SpecBuilder;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;

function normalizeContractPath(string $path): string
{
    $normalized = '/' . ltrim($path, '/');
    $normalized = preg_replace('#\\{[^}/]+\\??\\}#', '{}', $normalized) ?? $normalized;
    $normalized = preg_replace('#//+#', '/', $normalized) ?? $normalized;

    return $normalized;
}

test('All public API routes are represented in the OpenAPI document', function (): void {
    /** @var SpecBuilder $builder */
    $builder = app(SpecBuilder::class);
    $spec = $builder->compile();

    $documentedCombos = collect($spec['paths'] ?? [])
        ->map(function (array $operations, string $path): array {
            $combos = [];

            foreach ($operations as $method => $definition) {
                if (! in_array(strtolower((string) $method), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $combos[] = strtoupper((string) $method) . ' ' . normalizeContractPath($path);
            }

            return $combos;
        })
        ->flatten()
        ->values();

    $documentedSet = array_fill_keys($documentedCombos->all(), true);

    $routes = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(function (Route $route): bool {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                return false;
            }

            $action = $route->getActionName();
            if ($action === 'Closure') {
                return false;
            }

            return str_starts_with($action, 'App\\Http\\Controllers\\Api\\')
                || str_starts_with($action, 'App\\Http\\Controllers\\Admin\\');
        });

    $routeCombos = $routes->flatMap(function (Route $route): Collection {
        $methods = collect($route->methods())
            ->map(fn (string $method): string => strtoupper($method))
            ->filter(fn (string $method): bool => in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']))
            ->values();

        return $methods->map(function (string $method) use ($route): string {
            $path = '/' . ltrim($route->uri(), '/');

            return $method . ' ' . normalizeContractPath($path);
        });
    })->values();

    $missing = $routeCombos->reject(function (string $combo) use ($documentedSet): bool {
        if (isset($documentedSet[$combo])) {
            return true;
        }

        [$method, $path] = explode(' ', $combo, 2);

        if (str_starts_with($path, '/api/admin')) {
            return true;
        }

        return false;
    })->values();

    $message = $missing->isEmpty()
        ? ''
        : 'Routes missing from OpenAPI spec:' . PHP_EOL . $missing->implode(PHP_EOL);

    expect($missing->all())->toBeEmpty($message);
});
