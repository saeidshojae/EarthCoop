<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationRuleSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_rule_id', 'frequency', 'schedule_definition', 'timezone',
        'timezone_mode', 'next_run_at', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'schedule_definition' => 'array',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationRule::class, 'communication_rule_id');
    }
}
