<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatAuditEvent;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;

class SecretariatAuditService
{
    public function append(
        SecretariatOffice $office,
        ?SecretariatRecord $record,
        ?User $actor,
        string $eventType,
        array $metadata = []
    ): SecretariatAuditEvent {
        $request = app()->bound('request') ? request() : null;

        return SecretariatAuditEvent::query()->create([
            'office_id' => $office->id,
            'record_id' => $record?->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'event_at' => now(),
            'metadata' => $metadata ?: null,
            'request_ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
