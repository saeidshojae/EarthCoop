<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'public_id',
        'uploader_user_id',
        'purpose',
        'visibility',
        'status',
        'disk',
        'storage_key',
        'original_name',
        'mime_type',
        'size',
        'sha256',
        'width',
        'height',
        'privacy_status',
        'scan_status',
    ];

    protected $hidden = [
        'disk',
        'storage_key',
        'original_name',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function storagePath(): string
    {
        return Storage::disk($this->disk)->path($this->storage_key);
    }
}
