<?php

namespace App\Models;

use App\Enums\Communication\CommunicationClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'status', 'audience_definition', 'communication_template_id',
        'communication_sender_identity_id', 'classification', 'priority', 'scheduled_at',
        'confirmed_at', 'paused_at', 'cancelled_at', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'audience_definition' => 'array',
            'classification' => CommunicationClassification::class,
            'scheduled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function runs(): HasMany
    {
        return $this->hasMany(CommunicationRun::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }
}
