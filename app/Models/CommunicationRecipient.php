<?php

namespace App\Models;

use App\Enums\Communication\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_id', 'user_id', 'email', 'locale', 'communication_template_version_id',
        'preference_decision', 'status', 'queued_at', 'sent_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'preference_decision' => 'array',
            'status' => DeliveryStatus::class,
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplateVersion::class, 'communication_template_version_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CommunicationDeliveryAttempt::class);
    }
}
