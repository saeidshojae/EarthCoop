<?php

namespace App\Models;

use App\Enums\Communication\CommunicationClassification;
use App\Enums\Communication\CommunicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Communication extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type', 'source_id', 'communication_rule_id', 'communication_campaign_id',
        'communication_run_id', 'communication_template_version_id', 'classification',
        'priority', 'context_snapshot', 'status', 'deduplication_key', 'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'classification' => CommunicationClassification::class,
            'context_snapshot' => 'array',
            'status' => CommunicationStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationRule::class, 'communication_rule_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'communication_campaign_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CommunicationRun::class, 'communication_run_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplateVersion::class, 'communication_template_version_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }
}
