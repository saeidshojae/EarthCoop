<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionResponsibilityContractVersion extends Model
{
    protected $fillable = [
        'position', 'version', 'body', 'is_active', 'published_at', 'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('published_at') !== null) {
                throw new LogicException('Published election contract versions are immutable.');
            }
        });

        static::deleting(function (self $model): void {
            if ($model->published_at !== null) {
                throw new LogicException('Published election contract versions cannot be deleted.');
            }
        });
    }
}
