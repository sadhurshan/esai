<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CopilotSignoffHandoffCommand extends Command
{
    protected $signature = 'copilot:signoff-handoff
        {--launch= : Launch readiness artifact path}
        {--signoffs= : Signoff artifact path}
        {--output= : Output markdown path}';

    protected $description = 'Generate a signoff handoff markdown packet from current Copilot readiness evidence.';

    public function handle(): int
    {
        $launchPath = $this->resolvePath(
            (string) $this->option('launch'),
            base_path('docs/evidence/copilot-launch-readiness-*.json')
        );

        $signoffPath = $this->resolvePath(
            (string) $this->option('signoffs'),
            base_path('docs/evidence/copilot-signoffs-*.json')
        );

        if ($launchPath === null || $signoffPath === null) {
            $this->error('Could not resolve launch and/or signoff artifact path.');

            return self::FAILURE;
        }

        $launch = $this->readJson($launchPath);
        $signoffs = $this->readJson($signoffPath);

        if ($launch === null || $signoffs === null) {
            return self::FAILURE;
        }

        $roles = ['product', 'engineering', 'qa', 'security'];
        $pending = [];

        foreach ($roles as $role) {
            if (! (bool) data_get($signoffs, "roles.$role.approved", false)) {
                $pending[] = $role;
            }
        }

        $decision = (string) ($launch['decision'] ?? 'NO-GO');
        $generatedAt = (string) ($launch['generated_at'] ?? now()->toIso8601String());
        $latency = (int) data_get($launch, 'gates.latency.p95_ms', 0);
        $latencyTarget = (int) data_get($launch, 'gates.latency.target_ms', 0);
        $latencySamples = (int) data_get($launch, 'gates.latency.sample_count', 0);
        $latencyMinSamples = (int) data_get($launch, 'gates.latency.minimum_samples', 0);
        $traceCoverage = (float) data_get($launch, 'gates.trace_coverage.coverage_ratio', 0.0);
        $traceMin = (float) data_get($launch, 'gates.trace_coverage.minimum_ratio', 0.0);

        $output = (string) $this->option('output');
        if (trim($output) === '') {
            $output = base_path(sprintf('docs/evidence/copilot-signoff-handoff-%s.md', now()->toDateString()));
        } elseif (! str_starts_with($output, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:\\\\/', $output)) {
            $output = base_path($output);
        }

        $launchRelative = $this->toRelativePath($launchPath);
        $signoffRelative = $this->toRelativePath($signoffPath);

        $lines = [
            '# Copilot Launch Signoff Handoff',
            '',
            sprintf('- Generated: %s', $generatedAt),
            sprintf('- Current decision: **%s**', $decision),
            sprintf('- Launch artifact: `%s`', $launchRelative),
            sprintf('- Signoff artifact: `%s`', $signoffRelative),
            '',
            '## Technical Gate Snapshot',
            '',
            sprintf('- Runtime: %s', (bool) data_get($launch, 'gates.runtime.pass', false) ? 'PASS' : 'FAIL'),
            sprintf('- Feature rollout: %s', (bool) data_get($launch, 'gates.feature_rollout.pass', false) ? 'PASS' : 'FAIL'),
            sprintf('- Latency: %s (samples=%d min=%d p95=%dms target=%dms)', (bool) data_get($launch, 'gates.latency.pass', false) ? 'PASS' : 'FAIL', $latencySamples, $latencyMinSamples, $latency, $latencyTarget),
            sprintf('- Trace coverage: %s (coverage=%.2f min=%.2f)', (bool) data_get($launch, 'gates.trace_coverage.pass', false) ? 'PASS' : 'FAIL', $traceCoverage, $traceMin),
            '',
            '## Evidence Fingerprints (SHA-256)',
            '',
            sprintf('- Runtime: `%s`', (string) data_get($launch, 'inputs.runtime_evidence_sha256', 'n/a')),
            sprintf('- Latency: `%s`', (string) data_get($launch, 'inputs.latency_evidence_sha256', 'n/a')),
            sprintf('- Trace: `%s`', (string) data_get($launch, 'inputs.trace_evidence_sha256', 'n/a')),
            sprintf('- Signoffs: `%s`', (string) data_get($launch, 'inputs.signoff_evidence_sha256', 'n/a')),
            '',
            '## Pending Approvals',
            '',
        ];

        if ($pending === []) {
            $lines[] = '- None. All required signoffs are approved.';
        } else {
            foreach ($pending as $role) {
                $lines[] = sprintf('- %s', ucfirst($role));
            }
        }

        $lines = array_merge($lines, [
            '',
            '## Approval Commands',
            '',
            sprintf('Use this artifact path: `%s`', $signoffRelative),
            '',
            sprintf('- Product: `php artisan copilot:record-signoff --role=product --approved=1 --by="<approver>" --note="approved" --signoffs=%s --output=%s`', $signoffRelative, $signoffRelative),
            sprintf('- Engineering: `php artisan copilot:record-signoff --role=engineering --approved=1 --by="<approver>" --note="approved" --signoffs=%s --output=%s`', $signoffRelative, $signoffRelative),
            sprintf('- QA: `php artisan copilot:record-signoff --role=qa --approved=1 --by="<approver>" --note="approved" --signoffs=%s --output=%s`', $signoffRelative, $signoffRelative),
            sprintf('- Security: `php artisan copilot:record-signoff --role=security --approved=1 --by="<approver>" --note="approved" --signoffs=%s --output=%s`', $signoffRelative, $signoffRelative),
            '',
            sprintf('- One-command finalize (recommended): `php artisan copilot:finalize-launch --product-by="<approver>" --engineering-by="<approver>" --qa-by="<approver>" --security-by="<approver>" --signoffs=%s --launch-output=%s`', $signoffRelative, $launchRelative),
            '',
            sprintf('- Final check: `php artisan copilot:launch-readiness --signoffs=%s --format=json --output=%s`', $signoffRelative, $launchRelative),
            '',
        ]);

        $directory = dirname($output);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($output, implode(PHP_EOL, $lines));

        $this->info(sprintf('Signoff handoff written to %s', $this->toRelativePath($output)));

        return self::SUCCESS;
    }

    private function resolvePath(string $explicit, string $glob): ?string
    {
        if (trim($explicit) !== '') {
            $candidate = str_starts_with($explicit, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $explicit)
                ? $explicit
                : base_path($explicit);

            return is_file($candidate) ? $candidate : null;
        }

        $matches = glob($glob);
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
            $this->error(sprintf('Failed to read JSON: %s', $this->toRelativePath($path)));

            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->error(sprintf('Invalid JSON: %s', $this->toRelativePath($path)));

            return null;
        }

        return $decoded;
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
