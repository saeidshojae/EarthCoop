<?php

namespace App\Models;

use App\Enums\Communication\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationDeliveryAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_recipient_id', 'attempt_number', 'provider', 'provider_message_id',
        'status', 'failure_class', 'failure_code', 'failure_message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CommunicationRecipient::class, 'communication_recipient_id');
    }
}
