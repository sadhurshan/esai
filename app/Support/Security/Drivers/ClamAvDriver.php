<?php

namespace App\Support\Security\Drivers;

use App\Support\Security\Exceptions\VirusDetectedException;
use App\Support\Security\Exceptions\VirusScanFailedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ClamAvDriver implements VirusScanDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function assertClean(UploadedFile $file, array $context = []): void
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw new VirusScanFailedException('Uploaded file is not readable for scanning.');
        }

        $binary = (string) ($this->config['binary'] ?? 'clamscan');
        $arguments = $this->config['arguments'] ?? ['--infected', '--no-summary', '--stdout'];

        if (! is_array($arguments)) {
            $arguments = [$arguments];
        }

        $processArguments = array_values(array_filter(array_merge([$binary], $arguments, [$path]), static fn ($value) => $value !== null && $value !== ''));

        $process = new Process($processArguments);
        $timeout = $this->config['timeout'] ?? 60;
        if ($timeout !== null) {
            $process->setTimeout((float) $timeout);
        }

        $process->run();

        if ($process->isSuccessful()) {
            Log::debug('ClamAV scan completed.', [
                'driver' => 'clamav',
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'context' => $context,
            ]);

            return;
        }

        if ($process->getExitCode() === 1) {
            $output = trim($process->getOutput() ?: $process->getErrorOutput());

            throw new VirusDetectedException($output !== '' ? $output : 'ClamAV marked the file as infected.');
        }

        $message = trim($process->getErrorOutput() ?: $process->getOutput());

        throw new VirusScanFailedException($message !== '' ? $message : 'Unable to complete virus scan using ClamAV.');
    }
}
