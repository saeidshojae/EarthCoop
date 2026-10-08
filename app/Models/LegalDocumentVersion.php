<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class LegalDocumentVersion extends Model
{
    protected $fillable = [
        'legal_document_id', 'version_label', 'language', 'status',
        'content_snapshot', 'content_sha256', 'published_at', 'published_by',
    ];

    protected $casts = ['published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('published_at') === null) {
                return;
            }

            foreach (['legal_document_id', 'version_label', 'language', 'content_snapshot', 'content_sha256', 'published_at', 'published_by'] as $field) {
                if ($version->isDirty($field)) {
                    throw new LogicException('Published legal document snapshots are immutable.');
                }
            }

            if ($version->isDirty('status') && ! ($version->getOriginal('status') === 'published' && $version->status === 'retired')) {
                throw new LogicException('Published legal document status cannot be rewritten.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->published_at !== null) {
                throw new LogicException('Published legal document versions cannot be deleted.');
            }
        });
    }

    public function document()
    {
        return $this->belongsTo(LegalDocument::class);
    }
}
