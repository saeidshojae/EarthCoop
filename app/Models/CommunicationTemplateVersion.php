<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_template_id', 'version', 'locale', 'subject', 'body',
        'variables_schema', 'communication_sender_identity_id', 'published_at',
        'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'variables_schema' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    public function senderIdentity(): BelongsTo
    {
        return $this->belongsTo(CommunicationSenderIdentity::class, 'communication_sender_identity_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }
}
