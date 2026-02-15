<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CopilotFinalizeLaunchCommand extends Command
{
    protected $signature = 'copilot:finalize-launch
        {--product-by= : Product approver identifier}
        {--engineering-by= : Engineering approver identifier}
        {--qa-by= : QA approver identifier}
        {--security-by= : Security approver identifier}
        {--mode=production : Signoff artifact mode (production|simulation)}
        {--note=approved : Shared signoff note}
        {--signoffs= : Signoff artifact path}
        {--launch-output= : Launch readiness output path}
        {--verification-output= : Readiness verification output path}
        {--allow-simulation-signoffs=0 : Allow simulation artifact mode in launch computation}
        {--format=table : Output format (table or json)}';

    protected $description = 'Record all launch signoffs and recompute Copilot launch readiness in one command.';

    public function handle(): int
    {
        $approvers = [
            'product' => trim((string) $this->option('product-by')),
            'engineering' => trim((string) $this->option('engineering-by')),
            'qa' => trim((string) $this->option('qa-by')),
            'security' => trim((string) $this->option('security-by')),
        ];

        $missing = array_values(array_keys(array_filter($approvers, static fn (string $value): bool => $value === '')));
        if ($missing !== []) {
            $this->error(sprintf('Missing approver options: %s', implode(', ', $missing)));

            return self::FAILURE;
        }

        $mode = strtolower(trim((string) $this->option('mode')));
        if (! in_array($mode, ['production', 'simulation'], true)) {
            $this->error('Invalid --mode value. Use production or simulation.');

            return self::FAILURE;
        }

        $signoffPath = $this->resolvePath((string) $this->option('signoffs'), sprintf('docs/evidence/copilot-signoffs-%s.json', now()->toDateString()));
        $launchOutputPath = $this->resolvePath((string) $this->option('launch-output'), sprintf('docs/evidence/copilot-launch-readiness-%s.json', now()->toDateString()));
        $verificationOutputPath = $this->resolvePath((string) $this->option('verification-output'), sprintf('docs/evidence/copilot-readiness-verification-%s.json', now()->toDateString()));

        $signoffExit = Artisan::call('copilot:bulk-signoff', [
            '--product-by' => $approvers['product'],
            '--engineering-by' => $approvers['engineering'],
            '--qa-by' => $approvers['qa'],
            '--security-by' => $approvers['security'],
            '--mode' => $mode,
            '--note' => (string) $this->option('note'),
            '--signoffs' => $this->toRelativePath($signoffPath),
            '--output' => $this->toRelativePath($signoffPath),
            '--format' => 'json',
        ]);

        if ($signoffExit !== self::SUCCESS) {
            $this->error('Bulk signoff step failed.');

            return self::FAILURE;
        }

        $allowSimulation = $this->toBool((string) $this->option('allow-simulation-signoffs'));

        $launchExit = Artisan::call('copilot:launch-readiness', [
            '--signoffs' => $this->toRelativePath($signoffPath),
            '--allow-simulation-signoffs' => $allowSimulation ? '1' : '0',
            '--format' => 'json',
            '--output' => $this->toRelativePath($launchOutputPath),
        ]);

        $launchPayload = $this->readJson($launchOutputPath);
        if ($launchPayload === null) {
            $this->error('Could not read launch output payload.');

            return self::FAILURE;
        }

        $verificationExit = Artisan::call('copilot:verify-readiness-artifact', [
            '--artifact' => $this->toRelativePath($launchOutputPath),
            '--format' => 'json',
            '--output' => $this->toRelativePath($verificationOutputPath),
        ]);

        $verificationPayload = $this->readJson($verificationOutputPath);
        if ($verificationPayload === null) {
            $this->error('Could not read verification output payload.');

            return self::FAILURE;
        }

        $verificationResult = (string) data_get($verificationPayload, 'summary.result', 'fail');
        $verificationPass = $verificationExit === self::SUCCESS && $verificationResult === 'pass';

        $decision = (string) ($launchPayload['decision'] ?? 'NO-GO');

        $result = [
            'generated_at' => now()->toIso8601String(),
            'decision' => $decision,
            'signoff_artifact' => $this->toRelativePath($signoffPath),
            'launch_artifact' => $this->toRelativePath($launchOutputPath),
            'verification_artifact' => $this->toRelativePath($verificationOutputPath),
            'mode' => $mode,
            'allow_simulation_signoffs' => $allowSimulation,
            'approvers' => $approvers,
            'launch_exit_code' => $launchExit,
            'verification_exit_code' => $verificationExit,
            'verification_result' => $verificationResult,
            'missing_roles' => data_get($launchPayload, 'gates.signoffs.missing_roles', []),
        ];

        $this->render($result);

        return $decision === 'GO' && $verificationPass ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePath(string $path, string $fallbackRelative): string
    {
        if (trim($path) === '') {
            return base_path($fallbackRelative);
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
        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function render(array $result): void
    {
        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT));

            return;
        }

        $this->table(['Field', 'Value'], [
            ['decision', (string) $result['decision']],
            ['verification_result', (string) $result['verification_result']],
            ['mode', (string) $result['mode']],
            ['allow_simulation_signoffs', (bool) $result['allow_simulation_signoffs'] ? 'true' : 'false'],
            ['signoff_artifact', (string) $result['signoff_artifact']],
            ['launch_artifact', (string) $result['launch_artifact']],
            ['verification_artifact', (string) $result['verification_artifact']],
            ['product_by', (string) data_get($result, 'approvers.product', '')],
            ['engineering_by', (string) data_get($result, 'approvers.engineering', '')],
            ['qa_by', (string) data_get($result, 'approvers.qa', '')],
            ['security_by', (string) data_get($result, 'approvers.security', '')],
            ['missing_roles', implode(',', is_array($result['missing_roles']) ? $result['missing_roles'] : [])],
        ]);
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
