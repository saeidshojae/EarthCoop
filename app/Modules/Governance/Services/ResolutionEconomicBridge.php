<?php

namespace App\Modules\Governance\Services;

use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\Resolution;
use Illuminate\Support\Facades\DB;

class ResolutionEconomicBridge
{
    public const PUBLIC_PROJECT_APPROVED = 'PUBLIC_PROJECT_APPROVED';
    public const GUILD_FUND_CREATED = 'GUILD_FUND_CREATED';
    public const PUBLIC_EXPENDITURE_APPROVED = 'PUBLIC_EXPENDITURE_APPROVED';

    private const SUPPORTED_ACTIONS = [
        self::PUBLIC_PROJECT_APPROVED,
        self::GUILD_FUND_CREATED,
        self::PUBLIC_EXPENDITURE_APPROVED,
    ];

    /**
     * Convert an adopted governance resolution into an idempotent economic
     * command. This boundary deliberately does not create projects, obligations,
     * accounts or money movements. Domain-specific consumers execute later.
     */
    public function enqueue(Resolution $resolution): EconomicAction
    {
        return DB::transaction(function () use ($resolution) {
            $locked = Resolution::whereKey($resolution->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'adopted') {
                throw new \RuntimeException('Only adopted resolutions can enter the economic bridge.');
            }
            if (! in_array($locked->effect_status, ['pending_bridge', 'queued'], true)) {
                throw new \RuntimeException('Resolution has no economic effect pending bridge execution.');
            }
            if (! $locked->eligibility_snapshot_id || ! $locked->eligibilitySnapshot()->where('status', 'finalized')->exists()) {
                throw new \RuntimeException('Economic resolutions require a finalized voter eligibility snapshot.');
            }

            $effect = (array) ($locked->financial_effect ?? []);
            $actionType = strtoupper(trim((string) ($effect['action'] ?? '')));
            if (! in_array($actionType, self::SUPPORTED_ACTIONS, true)) {
                throw new \InvalidArgumentException('Unsupported governance economic action.');
            }

            $idempotencyKey = 'governance-resolution:' . $locked->id . ':' . $actionType;
            $existing = EconomicAction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($locked->effect_status !== 'queued') {
                    $locked->update(['effect_status' => 'queued']);
                }
                return $existing;
            }

            $action = EconomicAction::create([
                'resolution_id' => $locked->id,
                'group_id' => $locked->group_id,
                'eligibility_snapshot_id' => $locked->eligibility_snapshot_id,
                'action_type' => $actionType,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'payload' => [
                    'resolution_id' => (int) $locked->id,
                    'proposal_id' => (int) $locked->proposal_id,
                    'source_group_id' => (int) $locked->group_id,
                    'eligibility_snapshot_id' => (int) $locked->eligibility_snapshot_id,
                    'eligible_voter_count' => (int) ($locked->eligible_voter_count ?? 0),
                    'financial_effect' => $effect,
                    'resolution_metadata' => (array) ($locked->metadata ?? []),
                ],
            ]);

            $metadata = (array) ($locked->metadata ?? []);
            $metadata['economic_action_id'] = (int) $action->id;
            $metadata['economic_effect_executed'] = false;
            $locked->update([
                'effect_status' => 'queued',
                'metadata' => $metadata,
            ]);

            return $action;
        }, 3);
    }
}
