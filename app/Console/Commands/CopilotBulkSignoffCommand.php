<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CopilotBulkSignoffCommand extends Command
{
    protected $signature = 'copilot:bulk-signoff
        {--product-by= : Product approver identifier}
        {--engineering-by= : Engineering approver identifier}
        {--qa-by= : QA approver identifier}
        {--security-by= : Security approver identifier}
        {--mode=production : Artifact mode (production or simulation)}
        {--note=approved : Shared signoff note}
        {--signoffs= : Signoff artifact path}
        {--output= : Output signoff artifact path}
        {--format=table : Output format (table or json)}';

    protected $description = 'Record all required Copilot launch signoffs in one command.';

    public function handle(): int
    {
        $signoffPath = $this->resolvePath((string) $this->option('signoffs'));
        $outputPath = $this->resolvePath((string) $this->option('output'));
        $mode = strtolower(trim((string) $this->option('mode')));

        if (! in_array($mode, ['production', 'simulation'], true)) {
            $this->error('Invalid --mode value. Use production or simulation.');

            return self::FAILURE;
        }

        $payload = $this->readJson($signoffPath) ?? $this->emptyPayload();
        $roles = ['product', 'engineering', 'qa', 'security'];

        $approvers = [
            'product' => trim((string) $this->option('product-by')),
            'engineering' => trim((string) $this->option('engineering-by')),
            'qa' => trim((string) $this->option('qa-by')),
            'security' => trim((string) $this->option('security-by')),
        ];

        foreach ($roles as $role) {
            if ($approvers[$role] === '') {
                $this->error(sprintf('Missing --%s-by approver value.', $role));

                return self::FAILURE;
            }
        }

        $note = trim((string) $this->option('note'));

        foreach ($roles as $role) {
            $payload['roles'][$role] = [
                'approved' => true,
                'by' => $approvers[$role],
                'at' => now()->toIso8601String(),
                'note' => $note !== '' ? $note : null,
            ];
        }

        $payload['generated_at'] = now()->toIso8601String();
        $payload['meta'] = [
            'mode' => $mode,
            'updated_by' => 'copilot:bulk-signoff',
        ];
        $payload['summary'] = [
            'approved_count' => 4,
            'required_count' => 4,
            'all_approved' => true,
        ];

        $this->writeJson($outputPath, $payload);
        $this->render($payload);
        $this->info(sprintf('Bulk signoff written to %s', $this->toRelativePath($outputPath)));

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (trim($path) === '') {
            return base_path(sprintf('docs/evidence/copilot-signoffs-%s.json', now()->toDateString()));
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)
            ? $path
            : base_path($path);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'roles' => [
                'product' => ['approved' => false, 'by' => null, 'at' => null, 'note' => null],
                'engineering' => ['approved' => false, 'by' => null, 'at' => null, 'note' => null],
                'qa' => ['approved' => false, 'by' => null, 'at' => null, 'note' => null],
                'security' => ['approved' => false, 'by' => null, 'at' => null, 'note' => null],
            ],
            'summary' => ['approved_count' => 0, 'required_count' => 4, 'all_approved' => false],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode bulk signoff JSON.');
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($path, $encoded);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function render(array $payload): void
    {
        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

            return;
        }

        $rows = [];
        foreach (['product', 'engineering', 'qa', 'security'] as $role) {
            $entry = is_array($payload['roles'][$role] ?? null) ? $payload['roles'][$role] : [];
            $rows[] = [
                $role,
                (bool) ($entry['approved'] ?? false) ? 'approved' : 'pending',
                (string) ($entry['by'] ?? ''),
                (string) ($entry['at'] ?? ''),
            ];
        }

        $this->table(['Role', 'State', 'By', 'At'], $rows);
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
