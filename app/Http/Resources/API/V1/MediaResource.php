<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->public_id,
            'purpose' => $this->purpose,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'sha256' => $this->sha256,
            'status' => $this->status,
            'width' => $this->width,
            'height' => $this->height,
            'privacy_status' => $this->privacy_status,
            'scan_status' => $this->scan_status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
