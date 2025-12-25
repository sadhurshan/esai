<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiAuditPermissionsCommand extends Command
{
    protected $signature = 'ai:audit-permissions {--format=table : Output format (table or json)}';

    protected $description = 'Audit /v1/ai routes for entitlement and permission coverage.';

    public function __construct(private readonly Router $router)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $routeExpectations = collect(config('permissions.ai_route_expectations', []));
        if ($routeExpectations->isEmpty()) {
            $this->warn('No AI route expectations defined in config/permissions.php.');

            return self::SUCCESS;
        }

        $permissionMap = collect(config('permissions.middleware_permissions', []))
            ->map(fn (array $permissions): array => array_values(array_filter($permissions)));

        $aiRoutes = $this->collectAiRoutes();
        if ($aiRoutes->isEmpty()) {
            $this->warn('No /v1/ai routes registered.');

            return self::SUCCESS;
        }

        [$reportRows, $warnings] = $this->buildReport($aiRoutes, $routeExpectations, $permissionMap);

        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line(json_encode([
                'routes' => $reportRows,
                'warnings' => $warnings,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['URI', 'Methods', 'Middleware', 'Expected Permissions', 'Missing ensure.ai', 'Missing permissions'],
                array_map(static fn (array $row): array => [
                    $row['uri'],
                    implode(', ', $row['methods']),
                    implode(', ', $row['middleware']),
                    implode(', ', $row['expected_permissions']),
                    implode(', ', $row['missing_ensure']),
                    implode(', ', $row['missing_permissions']),
                ], $reportRows)
            );
        }

        if ($warnings !== []) {
            foreach ($warnings as $warning) {
                $this->warn(sprintf(
                    '%s missing ensure: [%s] permissions: [%s]',
                    $warning['uri'],
                    implode(', ', $warning['missing_ensure']),
                    implode(', ', $warning['missing_permissions'])
                ));
            }

            return self::FAILURE;
        }

        $this->info('AI RBAC audit passed: 0 warnings.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{uri:string, normalized_uri:string, route:\Illuminate\Routing\Route}>
     */
    private function collectAiRoutes(): Collection
    {
        return collect($this->router->getRoutes())
            ->filter(fn ($route) => $this->isAiRoute($route->uri()))
            ->values()
            ->map(function ($route): array {
                $uri = ltrim($route->uri(), '/');

                return [
                    'uri' => $uri,
                    'normalized_uri' => $this->normalizeAiUri($uri),
                    'route' => $route,
                ];
            });
    }

    /**
     * @param Collection<int, array{uri:string, normalized_uri:string, route:\Illuminate\Routing\Route}> $routes
     * @param Collection<int, array<string, mixed>> $routeExpectations
     * @param Collection<string, array<int, string>> $permissionMap
     * @return array{0:array<int, array<string, mixed>>,1:array<int, array<string, mixed>>}
     */
    private function buildReport(Collection $routes, Collection $routeExpectations, Collection $permissionMap): array
    {
        $rows = [];
        $warnings = [];

        foreach ($routes as $entry) {
            $uri = $entry['uri'];
            $normalizedUri = $entry['normalized_uri'];
            $route = $entry['route'];
            $expectation = $this->matchExpectation($normalizedUri, $routeExpectations);

            if ($expectation === null) {
                continue;
            }

            $middleware = $this->normalizeMiddleware($route->gatherMiddleware());
            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            $expectedEnsure = Arr::wrap($expectation['required_ensure'] ?? []);
            $expectedPermissions = Arr::wrap($expectation['required_permissions'] ?? []);

            $missingEnsure = array_values(array_diff($expectedEnsure, $middleware));
            $coveredPermissions = $this->resolvePermissions($middleware, $permissionMap);
            $missingPermissions = array_values(array_diff($expectedPermissions, $coveredPermissions));

            $row = [
                'uri' => $uri,
                'methods' => $methods,
                'middleware' => $middleware,
                'expected_permissions' => $expectedPermissions,
                'missing_ensure' => $missingEnsure,
                'missing_permissions' => $missingPermissions,
            ];
            $rows[] = $row;

            if ($missingEnsure !== [] || $missingPermissions !== []) {
                $warnings[] = [
                    'uri' => $uri,
                    'missing_ensure' => $missingEnsure,
                    'missing_permissions' => $missingPermissions,
                ];
            }
        }

        return [$rows, $warnings];
    }

    /**
     * @param Collection<int, array<string, mixed>> $routeExpectations
     */
    private function matchExpectation(string $normalizedUri, Collection $routeExpectations): ?array
    {
        foreach ($routeExpectations as $expectation) {
            $pattern = (string) ($expectation['pattern'] ?? '');
            if ($pattern !== '' && Str::is($pattern, $normalizedUri)) {
                return $expectation;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $middleware
     * @return array<int, string>
     */
    private function normalizeMiddleware(array $middleware): array
    {
        return collect($middleware)
            ->filter(fn ($name) => is_string($name))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $middleware
     * @param Collection<string, array<int, string>> $permissionMap
     * @return array<int, string>
     */
    private function resolvePermissions(array $middleware, Collection $permissionMap): array
    {
        return collect($middleware)
            ->flatMap(fn (string $alias) => $permissionMap->get($alias, []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isAiRoute(string $uri): bool
    {
        $normalized = $this->normalizeAiUri($uri);

        return Str::startsWith($normalized, 'v1/ai');
    }

    private function normalizeAiUri(string $uri): string
    {
        $trimmed = ltrim($uri, '/');

        if (Str::startsWith($trimmed, 'api/')) {
            return ltrim(Str::after($trimmed, 'api/'), '/');
        }

        return $trimmed;
    }
}
