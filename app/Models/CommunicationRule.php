<?php

namespace App\Models;

use App\Enums\Communication\CommunicationClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommunicationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'name', 'trigger_type', 'event_key', 'condition_definition',
        'audience_definition', 'communication_template_id', 'communication_sender_identity_id',
        'classification', 'priority', 'delay_seconds', 'is_active', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'condition_definition' => 'array',
            'audience_definition' => 'array',
            'classification' => CommunicationClassification::class,
            'is_active' => 'boolean',
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

    public function schedule(): HasOne
    {
        return $this->hasOne(CommunicationRuleSchedule::class);
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
