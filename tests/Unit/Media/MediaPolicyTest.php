<?php

namespace Tests\Unit\Media;

use App\Services\Media\MediaPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaPolicyTest extends TestCase
{
    public function test_profile_avatar_rejects_file_whose_client_mime_claim_does_not_match_real_content(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'm5-media-');
        file_put_contents($path, 'this is plain text, not a jpeg image');

        $file = new UploadedFile(
            $path,
            'avatar.jpg',
            'image/jpeg',
            null,
            true,
        );

        try {
            $this->expectException(ValidationException::class);
            app(MediaPolicy::class)->validate($file, 'profile.avatar');
        } finally {
            @unlink($path);
        }
    }
}
