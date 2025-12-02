<?php

namespace App\Support\Security;

use App\Support\Security\Drivers\ClamAvDriver;
use App\Support\Security\Drivers\StubDriver;
use App\Support\Security\Drivers\VirusScanDriver;
use App\Support\Security\Exceptions\VirusScanFailedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class VirusScanner
{
    /**
     * @var array<string, VirusScanDriver>
     */
    private array $driverCache = [];

    /**
     * @param array<string, mixed> $context
     */
    public function assertClean(UploadedFile $file, array $context = []): void
    {
        $shouldScan = config('security.scan_uploads', true);

        if (! $shouldScan) {
            Log::warning('Virus scanning disabled via configuration.', [
                'filename' => $file->getClientOriginalName(),
                'env' => App::environment(),
                'context' => $context,
            ]);

            if (App::environment('production', 'staging')) {
                throw new VirusScanFailedException('Virus scanning is required but currently disabled.');
            }

            return;
        }

        $driverName = config('security.scan_driver', 'clamav');
        $this->driver($driverName)->assertClean($file, $context);
    }

    private function driver(string $name): VirusScanDriver
    {
        if (! isset($this->driverCache[$name])) {
            $this->driverCache[$name] = match ($name) {
                'clamav' => new ClamAvDriver(config('security.drivers.clamav', [])),
                'stub' => new StubDriver(),
                default => throw new \InvalidArgumentException("Unsupported virus scan driver [{$name}]."),
            };
        }

        return $this->driverCache[$name];
    }
}
