<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CopilotVerifyReadinessArtifactCommand extends Command
{
    protected $signature = 'copilot:verify-readiness-artifact
        {--artifact= : Launch readiness artifact path}
        {--format=table : Output format (table or json)}
        {--output= : Optional verification output path}';

    protected $description = 'Verify launch-readiness artifact evidence hashes still match source evidence files.';

    public function handle(): int
    {
        $artifactPath = $this->resolveArtifactPath((string) $this->option('artifact'));
        if ($artifactPath === null) {
            $this->error('Could not resolve launch readiness artifact path.');

            return self::FAILURE;
        }

        $artifact = $this->readJson($artifactPath);
        if ($artifact === null) {
            return self::FAILURE;
        }

        $checks = [];
        $checks[] = $this->verifyHashPair($artifact, 'runtime_evidence', 'runtime_evidence_sha256');
        $checks[] = $this->verifyHashPair($artifact, 'latency_evidence', 'latency_evidence_sha256');
        $checks[] = $this->verifyHashPair($artifact, 'trace_evidence', 'trace_evidence_sha256');
        $checks[] = $this->verifyHashPair($artifact, 'signoff_evidence', 'signoff_evidence_sha256');

        $failures = array_values(array_filter($checks, static fn (array $row): bool => $row['status'] === 'fail'));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'artifact' => $this->toRelativePath($artifactPath),
            'summary' => [
                'total' => count($checks),
                'failed' => count($failures),
                'result' => $failures === [] ? 'pass' : 'fail',
            ],
            'checks' => $checks,
        ];

        $this->render($payload, $checks);
        $this->writeOutputIfRequested($payload);

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<string, mixed> $artifact
     * @return array<string, mixed>
     */
    private function verifyHashPair(array $artifact, string $pathKey, string $hashKey): array
    {
        $relativePath = data_get($artifact, "inputs.$pathKey");
        $expectedHash = data_get($artifact, "inputs.$hashKey");

        if (! is_string($relativePath) || trim($relativePath) === '') {
            return [
                'evidence' => $pathKey,
                'status' => 'warn',
                'path' => null,
                'expected_sha256' => $expectedHash,
                'actual_sha256' => null,
                'message' => 'No evidence path present in artifact.',
            ];
        }

        $fullPath = str_starts_with($relativePath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $relativePath)
            ? $relativePath
            : base_path($relativePath);

        if (! is_file($fullPath)) {
            return [
                'evidence' => $pathKey,
                'status' => 'fail',
                'path' => $relativePath,
                'expected_sha256' => $expectedHash,
                'actual_sha256' => null,
                'message' => 'Evidence file not found.',
            ];
        }

        $actualHash = @hash_file('sha256', $fullPath);
        $actualHash = is_string($actualHash) ? $actualHash : null;

        if (! is_string($expectedHash) || trim($expectedHash) === '') {
            return [
                'evidence' => $pathKey,
                'status' => 'warn',
                'path' => $relativePath,
                'expected_sha256' => $expectedHash,
                'actual_sha256' => $actualHash,
                'message' => 'Expected SHA-256 missing in artifact.',
            ];
        }

        $pass = $actualHash !== null && hash_equals(strtolower($expectedHash), strtolower($actualHash));

        return [
            'evidence' => $pathKey,
            'status' => $pass ? 'pass' : 'fail',
            'path' => $relativePath,
            'expected_sha256' => $expectedHash,
            'actual_sha256' => $actualHash,
            'message' => $pass ? 'Hash matches artifact.' : 'Hash mismatch detected.',
        ];
    }

    private function resolveArtifactPath(string $explicit): ?string
    {
        if (trim($explicit) !== '') {
            $candidate = str_starts_with($explicit, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $explicit)
                ? $explicit
                : base_path($explicit);

            return is_file($candidate) ? $candidate : null;
        }

        $matches = glob(base_path('docs/evidence/copilot-launch-readiness-*.json'));
        if (! is_array($matches) || $matches === []) {
            return null;
        }

        usort($matches, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $matches[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            $this->error(sprintf('Failed to read artifact: %s', $this->toRelativePath($path)));

            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->error(sprintf('Invalid JSON artifact: %s', $this->toRelativePath($path)));

            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $checks
     */
    private function render(array $payload, array $checks): void
    {
        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

            return;
        }

        $this->table(
            ['Evidence', 'Status', 'Path', 'Message'],
            array_map(static fn (array $row): array => [
                (string) $row['evidence'],
                (string) $row['status'],
                (string) ($row['path'] ?? ''),
                (string) ($row['message'] ?? ''),
            ], $checks)
        );

        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $this->line(sprintf(
            'Verification: result=%s failed=%d/%d',
            (string) ($summary['result'] ?? 'fail'),
            (int) ($summary['failed'] ?? 0),
            (int) ($summary['total'] ?? 0)
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeOutputIfRequested(array $payload): void
    {
        $output = (string) $this->option('output');
        if ($output === '') {
            return;
        }

        $fullPath = str_starts_with($output, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $output)
            ? $output
            : base_path($output);

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $this->warn('Failed to encode verification output JSON.');

            return;
        }

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, $encoded);
        $this->info(sprintf('Verification output written to %s', $this->toRelativePath($fullPath)));
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
