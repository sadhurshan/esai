<?php

namespace App\Console\Commands;

use App\Models\AiEvent;
use App\Models\User;
use App\Services\Ai\ChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CopilotTelemetryProbeCommand extends Command
{
    protected $signature = 'copilot:telemetry-probe
        {--user-id= : User ID to run probe as}
        {--company-id= : Company ID filter when selecting user}
        {--message=Telemetry probe ping : Probe message}
        {--thread-title=Telemetry Probe : Thread title}
        {--format=table : Output format (table or json)}
        {--output= : Optional output path for JSON evidence}';

    protected $description = 'Generate a fresh Copilot chat event and verify phase telemetry fields are present.';

    public function __construct(private readonly ChatService $chatService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startedAt = Carbon::now();
        $user = $this->resolveUser();

        if (! $user instanceof User) {
            $this->error('No eligible user found for telemetry probe.');

            return self::FAILURE;
        }

        $threadTitle = (string) $this->option('thread-title');
        $message = (string) $this->option('message');
        $companyId = (int) $user->company_id;

        $thread = $this->chatService->createThread($companyId, $user, $threadTitle);

        $sendStatus = 'success';
        $sendError = null;

        try {
            $this->chatService->sendMessage($thread, $user, $message, []);
        } catch (\Throwable $exception) {
            $sendStatus = 'error';
            $sendError = $exception->getMessage();
        }

        $event = AiEvent::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('feature', 'ai_chat_message_send')
            ->where('created_at', '>=', $startedAt)
            ->orderByDesc('id')
            ->first();

        if (! $event instanceof AiEvent) {
            $payload = [
                'generated_at' => now()->toIso8601String(),
                'probe' => [
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'thread_id' => $thread->id,
                    'send_status' => $sendStatus,
                    'send_error' => $sendError,
                ],
                'result' => [
                    'event_found' => false,
                    'telemetry_present' => false,
                ],
            ];

            $this->render($payload);
            $this->writeOutputIfRequested($payload);

            return self::FAILURE;
        }

        $responsePayload = is_array($event->response_json) ? $event->response_json : [];
        $breakdown = is_array($responsePayload['latency_breakdown_ms'] ?? null)
            ? $responsePayload['latency_breakdown_ms']
            : [];

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'probe' => [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'thread_id' => $thread->id,
                'send_status' => $sendStatus,
                'send_error' => $sendError,
            ],
            'result' => [
                'event_found' => true,
                'event_id' => (int) $event->id,
                'event_status' => (string) $event->status,
                'latency_ms' => (int) ($event->latency_ms ?? 0),
                'telemetry_present' => $breakdown !== [],
                'latency_breakdown_ms' => $breakdown,
                'created_at' => optional($event->created_at)?->toIso8601String(),
            ],
        ];

        $this->render($payload);
        $this->writeOutputIfRequested($payload);

        return ($breakdown !== []) ? self::SUCCESS : self::FAILURE;
    }

    private function resolveUser(): ?User
    {
        $explicitUserId = (int) $this->option('user-id');
        if ($explicitUserId > 0) {
            return User::query()
                ->whereKey($explicitUserId)
                ->whereNotNull('company_id')
                ->first();
        }

        $query = User::query()->whereNotNull('company_id');

        $companyId = (int) $this->option('company-id');
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        return $query->orderBy('id')->first();
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

        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $probe = is_array($payload['probe'] ?? null) ? $payload['probe'] : [];

        $this->table(['Field', 'Value'], [
            ['user_id', (string) ($probe['user_id'] ?? '')],
            ['company_id', (string) ($probe['company_id'] ?? '')],
            ['thread_id', (string) ($probe['thread_id'] ?? '')],
            ['send_status', (string) ($probe['send_status'] ?? '')],
            ['event_found', ($result['event_found'] ?? false) ? 'true' : 'false'],
            ['telemetry_present', ($result['telemetry_present'] ?? false) ? 'true' : 'false'],
            ['event_id', (string) ($result['event_id'] ?? '')],
            ['latency_ms', (string) ($result['latency_ms'] ?? '')],
        ]);
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

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $this->warn('Failed to encode telemetry probe JSON output.');

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
        $this->info(sprintf('Telemetry probe output written to %s', $this->toRelativePath($fullPath)));
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
