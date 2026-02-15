<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CopilotLaunchReadinessCommand extends Command
{
    protected $signature = 'copilot:launch-readiness
        {--runtime= : Runtime gate evidence JSON path}
        {--latency= : Latency evidence JSON path}
        {--trace= : Latency trace-attribution evidence JSON path}
        {--signoffs= : Signoff evidence JSON path}
        {--p95-target-ms=5000 : P95 latency threshold for GO}
        {--latency-min-samples=10 : Minimum latency sample count required for latency gate}
        {--require-rollout-flag=1 : Require feature flag rollout check to be pass (1 or 0)}
        {--require-trace-coverage=1 : Require phase timing coverage check to be pass (1 or 0)}
        {--trace-coverage-min=0.1 : Minimum acceptable timing signal coverage ratio (0..1)}
        {--allow-simulation-signoffs=0 : Allow launch computation with simulation signoff artifact mode}
        {--product-signoff=0 : Product owner signoff (1 or 0)}
        {--engineering-signoff=0 : Engineering signoff (1 or 0)}
        {--qa-signoff=0 : QA signoff (1 or 0)}
        {--security-signoff=0 : Security/compliance signoff (1 or 0)}
        {--format=table : Output format (table or json)}
        {--output= : Optional output JSON file path}';

    protected $description = 'Compute consolidated Copilot GO/NO-GO readiness from evidence artifacts and signoffs.';

    public function handle(): int
    {
        $runtimePath = $this->resolveEvidencePath(
            (string) $this->option('runtime'),
            base_path('docs/evidence/copilot-runtime-gates-*.json')
        );

        $latencyPath = $this->resolveLatencyEvidencePath((string) $this->option('latency'));

        $tracePath = $this->resolveEvidencePath(
            (string) $this->option('trace'),
            base_path('docs/evidence/copilot-latency-trace-attribution-*.json')
        );

        $signoffPath = $this->resolveSignoffEvidencePath((string) $this->option('signoffs'));

        if ($runtimePath === null || $latencyPath === null) {
            $this->error('Could not resolve runtime and/or latency evidence file.');
            $this->line('Provide --runtime and --latency or ensure evidence files exist in docs/evidence/.');

            return self::FAILURE;
        }

        $runtime = $this->readJsonFile($runtimePath);
        $latency = $this->readJsonFile($latencyPath);

        if ($runtime === null || $latency === null) {
            return self::FAILURE;
        }

        $runtimeSummary = is_array($runtime['summary'] ?? null) ? $runtime['summary'] : [];
        $runtimeChecks = is_array($runtime['checks'] ?? null) ? $runtime['checks'] : [];

        $runtimeFailed = (int) ($runtimeSummary['failed'] ?? 0);
        $runtimeResult = (string) ($runtimeSummary['result'] ?? 'fail');
        $runtimeGate = $runtimeResult === 'pass' && $runtimeFailed === 0;

        $rolloutCheck = collect($runtimeChecks)
            ->first(static fn ($row): bool => is_array($row) && (($row['check'] ?? null) === 'feature_flags.ai_rollout'));

        $rolloutStatus = is_array($rolloutCheck) ? (string) ($rolloutCheck['status'] ?? 'warn') : 'warn';
        $requireRollout = $this->toBool((string) $this->option('require-rollout-flag'));
        $rolloutGate = ! $requireRollout || $rolloutStatus === 'pass';

        $overall = is_array($latency['overall'] ?? null) ? $latency['overall'] : [];
        $latencySampleCount = (int) ($overall['count'] ?? 0);
        $p95 = (int) ($overall['p95_ms'] ?? 0);
        $p95Target = max(1, (int) $this->option('p95-target-ms'));
        $latencyMinSamples = max(1, (int) $this->option('latency-min-samples'));
        $latencyGate = $latencySampleCount >= $latencyMinSamples && $p95 > 0 && $p95 <= $p95Target;

        $requireTraceCoverage = $this->toBool((string) $this->option('require-trace-coverage'));
        $traceCoverageMin = max(0.0, min(1.0, (float) $this->option('trace-coverage-min')));
        $trace = null;

        if ($tracePath !== null) {
            $trace = $this->readJsonFile($tracePath);
            if ($trace === null) {
                return self::FAILURE;
            }
        }

        if ($requireTraceCoverage && $tracePath === null) {
            $this->error('Trace coverage gate is enabled, but no trace evidence file was resolved.');
            $this->line('Provide --trace or generate docs/evidence/copilot-latency-trace-attribution-*.json.');

            return self::FAILURE;
        }

        $traceCoverage = is_array($trace['timing_signal_coverage'] ?? null)
            ? (float) (($trace['timing_signal_coverage']['coverage_ratio'] ?? 0))
            : 0.0;
        $traceSamples = is_array($trace) ? (int) ($trace['sample_size'] ?? 0) : 0;
        $traceCoverageGate = ! $requireTraceCoverage || ($traceCoverage >= $traceCoverageMin);

        $signoffEvidence = null;
        if ($signoffPath !== null) {
            $signoffEvidence = $this->readJsonFile($signoffPath);
            if ($signoffEvidence === null) {
                return self::FAILURE;
            }
        }

        $signoffsFromEvidence = [
            'product' => (bool) data_get($signoffEvidence, 'roles.product.approved', false),
            'engineering' => (bool) data_get($signoffEvidence, 'roles.engineering.approved', false),
            'qa' => (bool) data_get($signoffEvidence, 'roles.qa.approved', false),
            'security' => (bool) data_get($signoffEvidence, 'roles.security.approved', false),
        ];

        $signoffMode = strtolower((string) data_get($signoffEvidence, 'meta.mode', 'unknown'));
        $allowSimulationSignoffs = $this->toBool((string) $this->option('allow-simulation-signoffs'));
        $signoffArtifactGate = $allowSimulationSignoffs || $signoffMode !== 'simulation';

        $signoffs = [
            'product' => $signoffsFromEvidence['product'],
            'engineering' => $signoffsFromEvidence['engineering'],
            'qa' => $signoffsFromEvidence['qa'],
            'security' => $signoffsFromEvidence['security'],
        ];

        if ($this->input->hasParameterOption('--product-signoff')) {
            $signoffs['product'] = $this->toBool((string) $this->option('product-signoff'));
        }

        if ($this->input->hasParameterOption('--engineering-signoff')) {
            $signoffs['engineering'] = $this->toBool((string) $this->option('engineering-signoff'));
        }

        if ($this->input->hasParameterOption('--qa-signoff')) {
            $signoffs['qa'] = $this->toBool((string) $this->option('qa-signoff'));
        }

        if ($this->input->hasParameterOption('--security-signoff')) {
            $signoffs['security'] = $this->toBool((string) $this->option('security-signoff'));
        }

        $signoffGate = ! in_array(false, $signoffs, true);
        $missingSignoffs = array_values(array_keys(array_filter(
            $signoffs,
            static fn (bool $approved): bool => $approved === false
        )));

        $go = $runtimeGate && $rolloutGate && $latencyGate && $traceCoverageGate && $signoffArtifactGate && $signoffGate;

        $signoffRelativePath = $signoffPath !== null ? $this->toRelativePath($signoffPath) : null;

        $blockers = [];
        if (! $runtimeGate) {
            $blockers[] = 'runtime';
        }
        if (! $rolloutGate) {
            $blockers[] = 'feature_rollout';
        }
        if (! $latencyGate) {
            $blockers[] = sprintf('latency(sample_count=%d,min=%d,p95=%d,target=%d)', $latencySampleCount, $latencyMinSamples, $p95, $p95Target);
        }
        if (! $traceCoverageGate) {
            $blockers[] = sprintf('trace_coverage(coverage=%.2f,min=%.2f)', $traceCoverage, $traceCoverageMin);
        }
        if (! $signoffArtifactGate) {
            $blockers[] = sprintf('signoff_artifact(mode=%s,allow_simulation=%s)', $signoffMode, $allowSimulationSignoffs ? '1' : '0');
        }
        if (! $signoffGate) {
            $blockers[] = sprintf('signoffs(missing=%s)', implode(',', $missingSignoffs));
        }

        $nextActions = [];

        if (! $signoffGate && $signoffRelativePath !== null) {
            foreach ($missingSignoffs as $role) {
                $nextActions[] = sprintf(
                    'php artisan copilot:record-signoff --role=%s --approved=1 --by="<approver>" --note="approved" --signoffs=%s --output=%s',
                    $role,
                    $signoffRelativePath,
                    $signoffRelativePath
                );
            }

            $nextActions[] = sprintf(
                'php artisan copilot:finalize-launch --product-by="<approver>" --engineering-by="<approver>" --qa-by="<approver>" --security-by="<approver>" --signoffs=%s --launch-output=%s',
                $signoffRelativePath,
                'docs/evidence/copilot-launch-readiness-'.now()->toDateString().'.json'
            );
        }

        if (! $latencyGate) {
            $nextActions[] = 'php artisan copilot:latency-evidence --hours=1 --format=json --output=docs/evidence/copilot-latency-'.now()->toDateString().'.json';
        }

        $outputArtifact = trim((string) $this->option('output'));
        if ($outputArtifact !== '') {
            $outputArtifactFullPath = str_starts_with($outputArtifact, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $outputArtifact)
                ? $outputArtifact
                : base_path($outputArtifact);

            $nextActions[] = sprintf(
                'php artisan copilot:verify-readiness-artifact --artifact=%s --format=json --output=docs/evidence/copilot-readiness-verification-%s.json',
                $this->toRelativePath($outputArtifactFullPath),
                now()->toDateString()
            );
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'decision' => $go ? 'GO' : 'NO-GO',
            'inputs' => [
                'runtime_evidence' => $this->toRelativePath($runtimePath),
                'runtime_evidence_sha256' => $this->fileSha256($runtimePath),
                'latency_evidence' => $this->toRelativePath($latencyPath),
                'latency_evidence_sha256' => $this->fileSha256($latencyPath),
                'trace_evidence' => $tracePath !== null ? $this->toRelativePath($tracePath) : null,
                'trace_evidence_sha256' => $tracePath !== null ? $this->fileSha256($tracePath) : null,
                'signoff_evidence' => $signoffRelativePath,
                'signoff_evidence_sha256' => $signoffPath !== null ? $this->fileSha256($signoffPath) : null,
                'p95_target_ms' => $p95Target,
                'latency_min_samples' => $latencyMinSamples,
                'require_rollout_flag' => $requireRollout,
                'require_trace_coverage' => $requireTraceCoverage,
                'trace_coverage_min' => $traceCoverageMin,
                'allow_simulation_signoffs' => $allowSimulationSignoffs,
            ],
            'blockers' => $blockers,
            'next_actions' => $nextActions,
            'gates' => [
                'runtime' => [
                    'pass' => $runtimeGate,
                    'failed_checks' => $runtimeFailed,
                    'result' => $runtimeResult,
                ],
                'feature_rollout' => [
                    'pass' => $rolloutGate,
                    'status' => $rolloutStatus,
                    'required' => $requireRollout,
                ],
                'latency' => [
                    'pass' => $latencyGate,
                    'sample_count' => $latencySampleCount,
                    'minimum_samples' => $latencyMinSamples,
                    'p95_ms' => $p95,
                    'target_ms' => $p95Target,
                ],
                'trace_coverage' => [
                    'pass' => $traceCoverageGate,
                    'required' => $requireTraceCoverage,
                    'coverage_ratio' => $traceCoverage,
                    'minimum_ratio' => $traceCoverageMin,
                    'sample_size' => $traceSamples,
                ],
                'signoff_artifact' => [
                    'pass' => $signoffArtifactGate,
                    'mode' => $signoffMode,
                    'allow_simulation' => $allowSimulationSignoffs,
                ],
                'signoffs' => [
                    'pass' => $signoffGate,
                    'product' => $signoffs['product'],
                    'engineering' => $signoffs['engineering'],
                    'qa' => $signoffs['qa'],
                    'security' => $signoffs['security'],
                    'missing_roles' => $missingSignoffs,
                ],
            ],
        ];

        $this->render($payload);
        $this->writeOutput($payload);

        return $go ? self::SUCCESS : self::FAILURE;
    }

    private function resolveEvidencePath(string $explicitPath, string $globPattern): ?string
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

        $matches = glob($globPattern);
        if (! is_array($matches) || $matches === []) {
            return null;
        }

        usort($matches, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $matches[0] ?? null;
    }

    private function resolveLatencyEvidencePath(string $explicitPath): ?string
    {
        if (trim($explicitPath) !== '') {
            return $this->resolveEvidencePath($explicitPath, base_path('docs/evidence/copilot-latency-*.json'));
        }

        $matches = glob(base_path('docs/evidence/copilot-latency-*.json'));
        if (! is_array($matches) || $matches === []) {
            return null;
        }

        $filtered = array_values(array_filter($matches, static function (string $path): bool {
            $name = strtolower(basename($path));

            return str_starts_with($name, 'copilot-latency-')
                && ! str_contains($name, '-breakdown-')
                && ! str_contains($name, '-trace-');
        }));

        if ($filtered === []) {
            return null;
        }

        usort($filtered, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $filtered[0] ?? null;
    }

    private function resolveSignoffEvidencePath(string $explicitPath): ?string
    {
        if (trim($explicitPath) !== '') {
            return $this->resolveEvidencePath($explicitPath, base_path('docs/evidence/copilot-signoffs-*.json'));
        }

        $matches = glob(base_path('docs/evidence/copilot-signoffs-*.json'));
        if (! is_array($matches) || $matches === []) {
            return null;
        }

        $filtered = array_values(array_filter($matches, static function (string $path): bool {
            $name = strtolower(basename($path));

            return str_starts_with($name, 'copilot-signoffs-')
                && ! str_contains($name, '-sim-');
        }));

        if ($filtered === []) {
            return null;
        }

        usort($filtered, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $filtered[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonFile(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            $this->error(sprintf('Failed to read evidence file: %s', $this->toRelativePath($path)));

            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->error(sprintf('Invalid JSON evidence file: %s', $this->toRelativePath($path)));

            return null;
        }

        return $decoded;
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

        $gates = $payload['gates'];

        $rows = [
            ['Runtime checks', $gates['runtime']['pass'] ? 'PASS' : 'FAIL', sprintf('failed=%d', (int) $gates['runtime']['failed_checks'])],
            ['Feature rollout', $gates['feature_rollout']['pass'] ? 'PASS' : 'FAIL', sprintf('status=%s', (string) $gates['feature_rollout']['status'])],
            ['Latency p95', $gates['latency']['pass'] ? 'PASS' : 'FAIL', sprintf('samples=%d min=%d p95=%dms target=%dms', (int) $gates['latency']['sample_count'], (int) $gates['latency']['minimum_samples'], (int) $gates['latency']['p95_ms'], (int) $gates['latency']['target_ms'])],
            ['Trace coverage', $gates['trace_coverage']['pass'] ? 'PASS' : 'FAIL', sprintf('coverage=%.2f min=%.2f samples=%d', (float) $gates['trace_coverage']['coverage_ratio'], (float) $gates['trace_coverage']['minimum_ratio'], (int) $gates['trace_coverage']['sample_size'])],
            ['Signoff artifact', $gates['signoff_artifact']['pass'] ? 'PASS' : 'FAIL', sprintf('mode=%s allow_simulation=%s', (string) $gates['signoff_artifact']['mode'], ((bool) $gates['signoff_artifact']['allow_simulation']) ? '1' : '0')],
            [
                'Signoffs',
                $gates['signoffs']['pass'] ? 'PASS' : 'FAIL',
                sprintf(
                    'product=%s eng=%s qa=%s security=%s missing=%s',
                    $gates['signoffs']['product'] ? '1' : '0',
                    $gates['signoffs']['engineering'] ? '1' : '0',
                    $gates['signoffs']['qa'] ? '1' : '0',
                    $gates['signoffs']['security'] ? '1' : '0',
                    implode(',', is_array($gates['signoffs']['missing_roles'] ?? null) ? $gates['signoffs']['missing_roles'] : [])
                ),
            ],
        ];

        $this->table(['Gate', 'Status', 'Details'], $rows);
        $this->line(sprintf('Decision: %s', (string) $payload['decision']));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeOutput(array $payload): void
    {
        $output = (string) $this->option('output');
        if ($output === '') {
            return;
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $this->warn('Failed to encode launch readiness JSON output.');

            return;
        }

        $fullPath = str_starts_with($output, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $output)
            ? $output
            : base_path($output);

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, $encoded);
        $this->info(sprintf('Launch readiness output written to %s', $this->toRelativePath($fullPath)));
    }

    private function toBool(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function fileSha256(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $hash = @hash_file('sha256', $path);

        return is_string($hash) && $hash !== '' ? $hash : null;
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
