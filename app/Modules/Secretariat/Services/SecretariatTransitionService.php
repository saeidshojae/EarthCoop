<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\DB;
use LogicException;

class SecretariatTransitionService
{
    private const ALLOWED = [
        'draft' => ['pending_approval', 'cancelled'],
        'pending_approval' => ['draft', 'rejected', 'registered'],
        'registered' => ['active', 'voided', 'superseded'],
        'active' => ['closed', 'superseded', 'voided'],
        'closed' => ['archived', 'superseded'],
        'archived' => [],
        'rejected' => [],
        'cancelled' => [],
        'superseded' => [],
        'voided' => [],
    ];

    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    public function assertAllowed(string $from, string $to): void
    {
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new LogicException("Forbidden Secretariat transition: {$from} -> {$to}");
        }
    }

    public function transition(SecretariatRecord $record, string $to, User $actor, array $metadata = []): SecretariatRecord
    {
        if ($to === 'registered') {
            throw new LogicException('Registration must go through SecretariatRecordService::register().');
        }

        return DB::transaction(function () use ($record, $to, $actor, $metadata) {
            /** @var SecretariatRecord $locked */
            $locked = SecretariatRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $this->assertAllowed($from, $to);

            $locked->performControlledMutation(static function (SecretariatRecord $target) use ($to): void {
                $target->forceFill(['status' => $to])->save();
            });

            $this->audit->append($locked->office, $locked, $actor, $this->eventFor($to), [
                'from' => $from,
                'to' => $to,
                ...$metadata,
            ]);

            return $locked->refresh();
        });
    }

    private function eventFor(string $status): string
    {
        return match ($status) {
            'pending_approval' => 'submitted_for_approval',
            'draft' => 'draft_updated',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            'active' => 'activated',
            'closed' => 'closed',
            'archived' => 'archived',
            'superseded' => 'superseded',
            'voided' => 'voided',
            default => 'status_changed',
        };
    }
}
