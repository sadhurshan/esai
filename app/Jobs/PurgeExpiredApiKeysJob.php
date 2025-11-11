<?php

namespace App\Jobs;

use App\Models\ApiKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeExpiredApiKeysJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        ApiKey::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('active', true)
            ->chunkById(200, static function ($keys): void {
                foreach ($keys as $key) {
                    $key->forceFill(['active' => false])->save();
                }
            });
    }
}
