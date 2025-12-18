<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyBrandingService
{
    public function __construct(
        private readonly string $disk = 'public',
        private readonly string $rootDirectory = 'company-branding'
    ) {
    }

    public function storeLogo(CompanyProfile $profile, UploadedFile $file): string
    {
        return $this->storeFile($profile, $file, 'logo');
    }

    public function storeMark(CompanyProfile $profile, UploadedFile $file): string
    {
        return $this->storeFile($profile, $file, 'mark');
    }

    public function deleteLogo(CompanyProfile $profile): void
    {
        $this->deletePath($profile->getRawOriginal('logo_url'));
    }

    public function deleteMark(CompanyProfile $profile): void
    {
        $this->deletePath($profile->getRawOriginal('mark_url'));
    }

    private function storeFile(CompanyProfile $profile, UploadedFile $file, string $type): string
    {
        $this->deletePath($profile->getRawOriginal("{$type}_url"));

        $directory = sprintf('%s/%d/%s', $this->rootDirectory, $profile->company_id, $type);
        $disk = Storage::disk($this->disk);

        $filename = sprintf('%s-%s.%s', $type, now()->format('YmdHis'), $file->getClientOriginalExtension() ?: 'bin');
        $path = ltrim($file->storeAs($directory, $filename, ['disk' => $this->disk]), '/');

        // Ensure file is publicly accessible where supported.
        if (method_exists($disk, 'setVisibility')) {
            $disk->setVisibility($path, 'public');
        }

        return $path;
    }

    private function deletePath(?string $path): void
    {
        if ($path === null || $path === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        $disk = Storage::disk($this->disk);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
