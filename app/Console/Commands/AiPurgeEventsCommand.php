<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AiPurgeEventsCommand extends Command
{
    protected $signature = 'ai:purge-events {--dry-run : Report eligible rows without deleting them}';

    protected $description = 'Purge AI events and chat messages that exceed the configured retention window.';

    public function handle(): int
    {
        $retention = (array) config('ai.retention', []);
        $mode = $this->normalizeMode((string) ($retention['mode'] ?? 'delete'));
        $dryRun = (bool) $this->option('dry-run');

        $eventsCutoff = Carbon::now()->subDays(max(1, (int) ($retention['events_days'] ?? 90)));
        $chatCutoff = Carbon::now()->subDays(max(1, (int) ($retention['chat_messages_days'] ?? 90)));

        $summary = [
            'ai_events' => $this->purgeTable('ai_events', $eventsCutoff, $mode, $dryRun, $retention),
            'ai_chat_messages' => $this->purgeTable('ai_chat_messages', $chatCutoff, $mode, $dryRun, $retention),
        ];

        $this->table(
            ['Table', 'Cutoff', 'Eligible', 'Purged', 'Archived', 'Archive Path'],
            collect($summary)
                ->map(fn (array $result, string $table): array => [
                    $table,
                    $result['cutoff']->toDateTimeString(),
                    $result['eligible'],
                    $result['purged'],
                    $result['archived'],
                    $result['archive_path'],
                ])->values()->all()
        );

        if ($dryRun) {
            $this->comment('Dry run complete — no rows were modified.');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $retention
     * @return array{cutoff: Carbon, eligible: int, purged: int, archived: int, archive_path: ?string}
     */
    private function purgeTable(string $table, Carbon $cutoff, string $mode, bool $dryRun, array $retention): array
    {
        $query = DB::table($table)
            ->whereNull('deleted_at')
            ->where('created_at', '<', $cutoff);

        $eligible = (clone $query)->count();

        if ($eligible === 0) {
            return [
                'cutoff' => $cutoff,
                'eligible' => 0,
                'purged' => 0,
                'archived' => 0,
                'archive_path' => null,
            ];
        }

        if ($dryRun) {
            return [
                'cutoff' => $cutoff,
                'eligible' => $eligible,
                'purged' => 0,
                'archived' => 0,
                'archive_path' => null,
            ];
        }

        $archivePath = null;
        $archived = 0;

        if ($mode === 'archive') {
            [$archivePath, $archived] = $this->archiveRows($table, $cutoff, $retention);
        }

        $purged = $query->delete();

        return [
            'cutoff' => $cutoff,
            'eligible' => $eligible,
            'purged' => $purged,
            'archived' => $archived,
            'archive_path' => $archivePath,
        ];
    }

    /**
     * @param array<string, mixed> $retention
     * @return array{0: ?string, 1: int}
     */
    private function archiveRows(string $table, Carbon $cutoff, array $retention): array
    {
        $diskName = (string) ($retention['archive_disk'] ?? 'local');
        $directory = trim((string) ($retention['archive_directory'] ?? 'ai/archives'), '/');
        $disk = Storage::disk($diskName);

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $timestamp = Carbon::now()->format('Ymd_His');
        $filePath = $directory.'/'.$table.'_'.$timestamp.'.jsonl';
        $archived = 0;

        DB::table($table)
            ->whereNull('deleted_at')
            ->where('created_at', '<', $cutoff)
            ->chunkById(500, function ($rows) use ($disk, $filePath, &$archived): void {
                $archivedAt = Carbon::now()->toISOString();

                foreach ($rows as $row) {
                    $payload = (array) $row;
                    $payload['archived_at'] = $archivedAt;

                    $disk->append($filePath, json_encode($payload, JSON_UNESCAPED_SLASHES));
                    $archived++;
                }
            });

        return [$archived > 0 ? $filePath : null, $archived];
    }

    private function normalizeMode(string $mode): string
    {
        $normalized = strtolower($mode);

        if (! in_array($normalized, ['delete', 'archive'], true)) {
            $this->warn(sprintf('Unknown AI retention mode "%s"; defaulting to delete.', $mode));

            return 'delete';
        }

        return $normalized;
    }
}
