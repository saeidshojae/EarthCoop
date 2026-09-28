<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MediaPolicy
{
    private const PURPOSES = [
        'profile.avatar' => [
            'max_bytes' => 5_242_880,
            'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'group.post' => [
            'max_bytes' => 20_971_520,
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        ],
        'project.attachment' => [
            'max_bytes' => 26_214_400,
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        ],
    ];

    private const EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    public function validate(UploadedFile $file, string $purpose): void
    {
        $policy = self::PURPOSES[$purpose] ?? null;
        if ($policy === null) {
            throw ValidationException::withMessages(['purpose' => ['Unsupported media purpose.']]);
        }

        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => ['Uploaded file is invalid.']]);
        }

        $mime = strtolower((string) $file->getClientMimeType());
        if (! in_array($mime, $policy['mimes'], true)) {
            throw ValidationException::withMessages(['file' => ['Unsupported media MIME type.']]);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, self::EXTENSIONS[$mime] ?? [], true)) {
            throw ValidationException::withMessages(['file' => ['File extension does not match its declared MIME type.']]);
        }

        if ((int) $file->getSize() > $policy['max_bytes']) {
            throw ValidationException::withMessages(['file' => ['Uploaded file is too large for this purpose.']]);
        }
    }
}
