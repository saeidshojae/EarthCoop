<?php

namespace App\Console\Commands;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalSupportService;
use Illuminate\Console\Command;

class ReconcilePendingResidenceProposalSupport extends Command
{
    protected $signature = 'location:reconcile-residence-support
                            {--apply : Persist missing committed-residence support}';

    protected $description = 'Audit or backfill proposal support implied by active pending residence intents';

    public function handle(LocationProposalSupportService $supportService): int
    {
        $apply = (bool) $this->option('apply');
        $checked = 0;
        $missing = 0;
        $created = 0;

        PendingResidenceIntent::query()
            ->where('status', 'pending')
            ->with('locationProposal')
            ->orderBy('id')
            ->chunkById(200, function ($intents) use ($supportService, $apply, &$checked, &$missing, &$created): void {
                foreach ($intents as $intent) {
                    $proposal = $intent->locationProposal;
                    if ($proposal === null || ! in_array($proposal->status, [
                        LocationProposalStatus::Pending,
                        LocationProposalStatus::ReadyForReview,
                        LocationProposalStatus::NeedsEvidence,
                    ], true)) {
                        continue;
                    }

                    $checked++;
                    $alreadySupported = $proposal->evidence()
                        ->where('user_id', $intent->user_id)
                        ->exists();

                    if ($alreadySupported) {
                        continue;
                    }

                    $missing++;
                    if (! $apply) {
                        continue;
                    }

                    $user = User::query()->find($intent->user_id);
                    if ($user === null) {
                        continue;
                    }

                    $supportService->recordCommitted($proposal, $user, [
                        'source' => 'residence_commit_reconcile',
                        'pending_residence_intent_id' => $intent->id,
                        'anchor_relationship_id' => $intent->anchor_relationship_id,
                    ]);
                    $created++;
                }
            });

        $this->line('mode: '.($apply ? 'apply' : 'dry-run'));
        $this->line('checked: '.$checked);
        $this->line('missing: '.$missing);
        $this->line('created: '.$created);

        return self::SUCCESS;
    }
}
