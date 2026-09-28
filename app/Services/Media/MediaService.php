<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function __construct(
        private readonly MediaPolicy $policy,
    ) {
    }

    public function store(User $uploader, UploadedFile $file, string $purpose): Media
    {
        $this->policy->validate($file, $purpose);

        $publicId = (string) Str::uuid();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storageKey = 'media/'.$publicId.'/'.Str::random(40).'.'.$extension;
        $disk = 'local';

        Storage::disk($disk)->put($storageKey, $file->getContent());
        $path = Storage::disk($disk)->path($storageKey);
        $mime = strtolower((string) $file->getMimeType());
        [$width, $height] = $this->dimensions($path, $mime);

        return Media::query()->create([
            'public_id' => $publicId,
            'uploader_user_id' => $uploader->id,
            'purpose' => $purpose,
            'visibility' => 'private',
            'status' => 'ready',
            'disk' => $disk,
            'storage_key' => $storageKey,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'width' => $width,
            'height' => $height,
            'privacy_status' => str_starts_with($mime, 'image/') ? 'pending' : 'not_applicable',
            'scan_status' => 'pending',
        ]);
    }

    private function dimensions(string $path, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($path);
        if (! is_array($size)) {
            return [null, null];
        }

        return [(int) $size[0], (int) $size[1]];
    }
}
