<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderEmailDraft;
use App\Services\Email\EmailDeliveryService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\SystemIdentityService;

class FounderEmailDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected EmailDeliveryService $delivery,
        protected SystemIdentityService $systemIdentities,
    ) {}

    public function requestSend(FounderEmailDraft $draft, int $actorId): array
    {
        return $this->request($draft, $actorId, 'send_email');
    }

    public function requestBulkSend(FounderEmailDraft $draft, int $actorId): array
    {
        if (count((array) $draft->recipients) < 2) {
            return ['success' => false, 'status' => 'invalid_state', 'reason' => 'bulk_send_requires_multiple_recipients'];
        }

        return $this->request($draft, $actorId, 'bulk_send');
    }

    protected function request(FounderEmailDraft $draft, int $actorId, string $action): array
    {
        if ($draft->status !== 'draft') {
            return ['success' => false, 'status' => 'invalid_state'];
        }

        return $this->requests->prepare('email', $action, [
            'entity_type' => 'founder_email_draft',
            'entity_id' => $draft->id,
            'requested_by' => $actorId,
            'reason_code' => $draft->reason_code ?: 'email-draft-' . $draft->id,
        ]);
    }

    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success' => false, 'status' => 'forbidden', 'reason' => 'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId);

        $action = is_array($pending) ? (string) data_get($pending, 'plan_item.domain_action') : '';
        if (! is_array($pending)
            || (string) data_get($pending, 'plan_item.domain') !== 'email'
            || ! in_array($action, ['send_email', 'bulk_send'], true)
            || (string) data_get($pending, 'context.entity_type') !== 'founder_email_draft') {
            return ['success' => false, 'status' => 'invalid_request'];
        }

        $draft = FounderEmailDraft::query()
            ->whereKey((int) data_get($pending, 'context.entity_id', 0))
            ->where('status', 'draft')
            ->first();
        if (! $draft) {
            return ['success' => false, 'status' => 'not_found'];
        }
        if ($action === 'bulk_send' && count((array) $draft->recipients) < 2) {
            return ['success' => false, 'status' => 'invalid_state', 'reason' => 'bulk_send_requires_multiple_recipients'];
        }

        $decided = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! ($decided['success'] ?? false)) {
            return $decided;
        }
        if ($decision === 'reject') {
            $draft->update(['status' => 'rejected']);
            return ['success' => true, 'status' => 'rejected', 'draft_id' => $draft->id];
        }

        $sender = $this->systemIdentities->mailSender('management');

        return $this->execution->execute(
            'email',
            $action,
            function () use ($draft, $founderId, $sender): array {
                $delivery = $this->delivery->sendHtml(
                    (array) $draft->recipients,
                    (string) $draft->subject,
                    (string) $draft->body,
                    $sender
                );

                $draft->update([
                    'status' => 'sent',
                    'approved_by' => $founderId,
                    'sent_at' => now(),
                ]);

                return [
                    'draft_id' => $draft->id,
                    'recipient_count' => count((array) $draft->recipients),
                    'sent_count' => (int) $delivery['sent_count'],
                    'failed_count' => (int) $delivery['failed_count'],
                    'sender_address' => $sender['address'],
                    'sender_name' => $sender['name'],
                ];
            },
            $requestId,
            [
                'entity_type' => 'founder_email_draft',
                'entity_id' => $draft->id,
                'requested_by' => $founderId,
            ]
        );
    }

    protected function founderIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            (array) config('najm-hoda-founder-action-policy.founder_approval.user_ids', [])
        )));
    }
}
