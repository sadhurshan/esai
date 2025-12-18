<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserAvatarService
{
    public function __construct(
        private readonly string $disk = 'public',
        private readonly string $directory = 'avatars'
    ) {
    }

    public function store(User $user, UploadedFile $file): string
    {
        $this->deleteExisting($user);

        return Storage::disk($this->disk)->putFile($this->buildDirectory($user), $file, 'public');
    }

    public function delete(User $user): void
    {
        $this->deleteExisting($user);
    }

    private function deleteExisting(User $user): void
    {
        $path = $user->avatar_path;

        if (! $path) {
            return;
        }

        $disk = Storage::disk($this->disk);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function buildDirectory(User $user): string
    {
        return trim(sprintf('%s/%d', $this->directory, $user->getKey()), '/');
    }
}
