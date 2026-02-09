<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIInteraction extends Model
{
    use HasFactory;

    protected $table = 'ai_interactions';

    protected $fillable = [
        'agent_role',
        'input',
        'output',
        'model',
        'tokens_used',
        'cost',
        'response_time_ms',
        'user_id',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'cost' => 'decimal:4',
        'response_time_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * رابطه با کاربر
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope برای یک عامل خاص
     */
    public function scopeByAgent($query, string $agent)
    {
        return $query->where('agent_role', $agent);
    }

    /**
     * Scope برای امروز
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope برای این ماه
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }
}
