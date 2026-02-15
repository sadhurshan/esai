<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CopilotRecordSignoffCommand extends Command
{
    protected $signature = 'copilot:record-signoff
        {--role= : Signoff role (product|engineering|qa|security)}
        {--approved=1 : Signoff state (1 approve, 0 revoke)}
        {--by= : Approver identifier (name/email/id)}
        {--note= : Optional signoff note}
        {--mode=production : Artifact mode (production or simulation)}
        {--init=0 : Initialize signoff artifact with all roles pending}
        {--signoffs= : Existing signoff artifact path to update}
        {--format=table : Output format (table or json)}
        {--output= : Optional output JSON file path}';

    protected $description = 'Initialize or update Copilot launch signoff evidence.';

    /**
     * @var array<int, string>
     */
    private array $roles = ['product', 'engineering', 'qa', 'security'];

    public function handle(): int
    {
        $init = $this->toBool((string) $this->option('init'));
        $mode = strtolower(trim((string) $this->option('mode')));

        if (! in_array($mode, ['production', 'simulation'], true)) {
            $this->error('Invalid --mode value. Use production or simulation.');

            return self::FAILURE;
        }

        $existingPath = $this->resolveSignoffPath((string) $this->option('signoffs'));
        $existing = $existingPath !== null ? $this->readJsonFile($existingPath) : null;

        $payload = $this->basePayload($existing);

        if (! $init) {
            $role = strtolower(trim((string) $this->option('role')));
            if (! in_array($role, $this->roles, true)) {
                $this->error('Provide --role with one of: product, engineering, qa, security.');

                return self::FAILURE;
            }

            $by = trim((string) $this->option('by'));
            if ($by === '') {
                $this->error('Provide --by when recording a signoff.');

                return self::FAILURE;
            }

            $approved = $this->toBool((string) $this->option('approved'));
            $note = trim((string) $this->option('note'));

            $payload['roles'][$role] = [
                'approved' => $approved,
                'by' => $by,
                'at' => now()->toIso8601String(),
                'note' => $note !== '' ? $note : null,
            ];
        }

        $payload['generated_at'] = now()->toIso8601String();
        $payload['meta'] = [
            'mode' => $mode,
            'updated_by' => 'copilot:record-signoff',
        ];
        $payload['summary'] = $this->buildSummary($payload);

        $outputPath = $this->resolveOutputPath((string) $this->option('output'));
        $this->writeOutput($payload, $outputPath);
        $this->render($payload, $outputPath);

        return self::SUCCESS;
    }

    private function basePayload(?array $existing): array
    {
        $roles = [];

        foreach ($this->roles as $role) {
            $source = is_array($existing['roles'][$role] ?? null) ? $existing['roles'][$role] : [];

            $roles[$role] = [
                'approved' => (bool) ($source['approved'] ?? false),
                'by' => isset($source['by']) && is_string($source['by']) ? $source['by'] : null,
                'at' => isset($source['at']) && is_string($source['at']) ? $source['at'] : null,
                'note' => isset($source['note']) && is_string($source['note']) ? $source['note'] : null,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'roles' => $roles,
            'summary' => [
                'approved_count' => 0,
                'required_count' => count($this->roles),
                'all_approved' => false,
            ],
        ];
    }

    private function buildSummary(array $payload): array
    {
        $approved = 0;

        foreach ($this->roles as $role) {
            $approved += (bool) ($payload['roles'][$role]['approved'] ?? false) ? 1 : 0;
        }

        $required = count($this->roles);

        return [
            'approved_count' => $approved,
            'required_count' => $required,
            'all_approved' => $approved === $required,
        ];
    }

    private function resolveSignoffPath(string $explicitPath): ?string
    {
        if (trim($explicitPath) !== '') {
            $fullPath = base_path(trim($explicitPath));

            if (is_file($fullPath)) {
                return $fullPath;
            }

            if (is_file(trim($explicitPath))) {
                return trim($explicitPath);
            }

            return null;
        }

        $matches = glob(base_path('docs/evidence/copilot-signoffs-*.json'));
        if (! is_array($matches) || $matches === []) {
            return null;
        }

        usort($matches, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $matches[0] ?? null;
    }

    private function resolveOutputPath(string $explicitPath): string
    {
        if (trim($explicitPath) !== '') {
            return str_starts_with($explicitPath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $explicitPath)
                ? $explicitPath
                : base_path(trim($explicitPath));
        }

        return base_path(sprintf('docs/evidence/copilot-signoffs-%s.json', now()->toDateString()));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonFile(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeOutput(array $payload, string $outputPath): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $this->error('Failed to encode signoff output JSON.');

            return;
        }

        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($outputPath, $encoded);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function render(array $payload, string $outputPath): void
    {
        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));
            $this->info(sprintf('Signoff artifact written to %s', $this->toRelativePath($outputPath)));

            return;
        }

        $rows = [];
        foreach ($this->roles as $role) {
            $row = $payload['roles'][$role] ?? [];
            $rows[] = [
                $role,
                (bool) ($row['approved'] ?? false) ? 'approved' : 'pending',
                (string) ($row['by'] ?? ''),
                (string) ($row['at'] ?? ''),
            ];
        }

        $this->table(['Role', 'State', 'By', 'At'], $rows);
        $summary = $payload['summary'] ?? [];
        $this->line(sprintf(
            'Summary: approved=%d/%d all_approved=%s',
            (int) ($summary['approved_count'] ?? 0),
            (int) ($summary['required_count'] ?? count($this->roles)),
            (bool) ($summary['all_approved'] ?? false) ? 'true' : 'false'
        ));
        $this->info(sprintf('Signoff artifact written to %s', $this->toRelativePath($outputPath)));
    }

    private function toBool(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function toRelativePath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $base)) {
            return str_replace('\\', '/', substr($path, strlen($base)));
        }

        return str_replace('\\', '/', $path);
    }
}
