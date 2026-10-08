<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocumentVersion extends Model
{
    protected $fillable = [
        'legal_document_id', 'version_label', 'language', 'status',
        'content_snapshot', 'content_sha256', 'published_at', 'published_by',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(LegalDocument::class);
    }
}
