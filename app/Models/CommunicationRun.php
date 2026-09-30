<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_rule_id', 'communication_campaign_id', 'run_key', 'status',
        'matched_count', 'eligible_count', 'suppressed_count', 'invalid_count',
        'queued_count', 'sent_count', 'failed_count', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationRule::class, 'communication_rule_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'communication_campaign_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }
}
