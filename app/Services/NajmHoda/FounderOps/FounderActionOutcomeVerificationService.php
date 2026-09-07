<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Models\FounderNajmBaharTransactionIntent;
use App\Models\Setting;
use App\Models\SupportReplyDraft;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Services\Moderation\ReportManagementService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Services\ReferenceData\ReferenceDataApprovalService;
use Illuminate\Support\Facades\DB;
use Throwable;

class FounderActionOutcomeVerificationService
{
    public function __construct(
        protected RuntimeEventBus $events,
        protected ReferenceDataApprovalService $references,
        protected ReportManagementService $reports
    ) {}

    /**
     * Verify the persisted outcome of an already-authorized canonical mutation.
     * Verification is read-only and fail-closed: unsupported actions are never
     * reported as verified. Verification faults are contained because the
     * canonical mutation may already have committed successfully.
     *
     * @param array<string,mixed> $result
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function verify(string $domain, string $action, array $result, array $context = []): array
    {
        try {
            $verification = match ($domain . '.' . $action) {
                'support.send_reply' => $this->verifySupportReply($result),
                'notifications.publish_announcement' => $this->verifyAnnouncement($result),
                'blog.publish_post' => $this->verifyBlogPublication($result),
                'email.send_email', 'email.bulk_send' => $this->verifyEmailSend($result),
                'reference_data.approve', 'locations.approve' => $this->verifyReferenceApproval($result),
                'reports_moderation.resolve_report' => $this->verifyModerationResolution($result),
                'admin_settings.change_setting' => $this->verifyAdminSetting($result),
                'secretariat.register_formal_record' => $this->verifySecretariatRegistration($result),
                'secretariat.close_case' => $this->verifySecretariatCaseClosure($result),
                'najm_bahar.execute_transaction' => $this->verifyNajmBaharTransaction($result),
                'najm_bahar.change_monetary_policy' => $this->verifyNajmBaharMonetaryPolicy($result),
                'stock.settle_auction' => $this->verifyStockSettlement($result),
                default => [
                    'verified' => false,
                    'status' => 'not_configured',
                    'reason' => 'no_canonical_outcome_verifier',
                ],
            };
        } catch (Throwable $e) {
            $verification = [
                'verified' => false,
                'status' => 'verification_error',
                'reason' => 'outcome_verifier_failed',
                'exception_class' => $e::class,
            ];
        }

        $payload = [
            'domain' => $domain,
            'action' => $action,
            'verified' => (bool) ($verification['verified'] ?? false),
            'status' => (string) ($verification['status'] ?? 'unknown'),
            'entity_type' => is_scalar($context['entity_type'] ?? null) ? (string) $context['entity_type'] : null,
            'entity_id' => is_numeric($context['entity_id'] ?? null) ? (int) $context['entity_id'] : null,
        ];

        try {
            $this->events->emit(
                ($verification['verified'] ?? false)
                    ? 'najm_hoda.founder_ops.outcome.verified'
                    : 'najm_hoda.founder_ops.outcome.unverified',
                $payload
            );
        } catch (Throwable) {
            if ((bool) ($verification['verified'] ?? false)) {
                $verification = [
                    'verified' => false,
                    'status' => 'verification_error',
                    'reason' => 'outcome_verification_telemetry_failed',
                    'evidence' => $verification['evidence'] ?? [],
                ];
            }
        }

        return $verification;
    }

    /** @param array<string,mixed> $result */
    protected function verifySupportReply(array $result): array
    {
        $draftId = (int) ($result['draft_id'] ?? 0);
        $ticketId = (int) ($result['ticket_id'] ?? 0);
        $commentId = (int) ($result['comment_id'] ?? 0);

        $draft = $draftId > 0 ? SupportReplyDraft::query()->find($draftId) : null;
        $commentExists = $commentId > 0 && $ticketId > 0
            ? DB::table('ticket_comments')->where('id', $commentId)->where('ticket_id', $ticketId)->exists()
            : false;

        $verified = $draft !== null
            && (string) $draft->status === 'sent'
            && (int) $draft->ticket_id === $ticketId
            && $commentExists;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'evidence' => [
                'draft_id' => $draftId,
                'draft_status' => $draft?->status,
                'ticket_id' => $ticketId,
                'comment_id' => $commentId,
                'comment_persisted' => $commentExists,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyAnnouncement(array $result): array
    {
        $draftId = (int) ($result['draft_id'] ?? 0);
        $announcementId = (int) ($result['announcement_id'] ?? 0);

        $draft = $draftId > 0 ? FounderAnnouncementDraft::query()->find($draftId) : null;
        $announcementExists = $announcementId > 0
            ? DB::table('announcements')->where('id', $announcementId)->exists()
            : false;

        $verified = $draft !== null
            && (string) $draft->status === 'published'
            && (int) ($draft->announcement_id ?? 0) === $announcementId
            && $announcementExists;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'evidence' => [
                'draft_id' => $draftId,
                'draft_status' => $draft?->status,
                'announcement_id' => $announcementId,
                'announcement_persisted' => $announcementExists,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyBlogPublication(array $result): array
    {
        $draftId = (int) ($result['draft_id'] ?? 0);
        $blogId = (int) ($result['blog_id'] ?? 0);
        $draft = $draftId > 0 ? FounderContentDraft::query()->find($draftId) : null;
        $blogExists = $blogId > 0 ? DB::table('blogs')->where('id', $blogId)->exists() : false;
        $verified = $draft !== null && (string) $draft->status === 'published' && $blogExists;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'evidence' => [
                'draft_id' => $draftId,
                'draft_status' => $draft?->status,
                'blog_id' => $blogId,
                'blog_persisted' => $blogExists,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyEmailSend(array $result): array
    {
        $draftId = (int) ($result['draft_id'] ?? 0);
        $recipientCount = (int) ($result['recipient_count'] ?? 0);
        $sentCount = (int) ($result['sent_count'] ?? 0);
        $failedCount = (int) ($result['failed_count'] ?? 0);
        $draft = $draftId > 0 ? FounderEmailDraft::query()->find($draftId) : null;
        $countsConsistent = $recipientCount > 0 && ($sentCount + $failedCount) === $recipientCount;
        $verified = $draft !== null && (string) $draft->status === 'sent' && $countsConsistent;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => 'canonical_send_attempt_only',
            'external_delivery_confirmed' => false,
            'evidence' => [
                'draft_id' => $draftId,
                'draft_status' => $draft?->status,
                'recipient_count' => $recipientCount,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'counts_consistent' => $countsConsistent,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyReferenceApproval(array $result): array
    {
        $type = (string) ($result['type'] ?? '');
        $id = (int) ($result['id'] ?? 0);
        $model = $type !== '' && $id > 0 ? $this->references->find($type, $id) : null;
        $persistedStatus = $model?->getAttribute('status');
        $verified = $model !== null && (int) $persistedStatus === 1;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => 'canonical_reference_status',
            'evidence' => [
                'type' => $type,
                'id' => $id,
                'persisted_status' => $persistedStatus,
                'operation_status' => $result['status'] ?? null,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyModerationResolution(array $result): array
    {
        $sourceType = (string) ($result['source_type'] ?? '');
        $sourceId = (int) ($result['source_id'] ?? 0);
        $report = $sourceType !== '' && $sourceId > 0 ? $this->reports->find($sourceType, $sourceId) : null;
        $expected = $sourceType === 'report' ? 'resolved' : 'resolved_by_admin';
        $persistedStatus = $report?->getAttribute('status');
        $verified = $report !== null && (string) $persistedStatus === $expected;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => 'canonical_moderation_resolution',
            'evidence' => [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'expected_status' => $expected,
                'persisted_status' => $persistedStatus,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyAdminSetting(array $result): array
    {
        $key = (string) ($result['setting_key'] ?? '');
        $expected = $result['new_value'] ?? null;
        $settings = Setting::query()->find(1);
        $actual = $settings?->getAttribute($key);
        $verified = $key !== '' && $settings !== null && $actual === $expected;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'evidence' => [
                'setting_key' => $key,
                'expected_value' => $expected,
                'persisted_value' => $actual,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifySecretariatRegistration(array $result): array
    {
        $recordId = (int) ($result['record_id'] ?? 0);
        $registryNumber = (string) ($result['registry_number'] ?? '');
        $record = $recordId > 0 ? SecretariatRecord::query()->find($recordId) : null;
        $verified = $record !== null
            && $registryNumber !== ''
            && (string) ($record->registry_number ?? '') === $registryNumber
            && (string) $record->status === (string) ($result['record_status'] ?? '');

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => 'internal_formal_registration',
            'external_dispatch_confirmed' => false,
            'evidence' => [
                'record_id' => $recordId,
                'registry_number' => $registryNumber,
                'persisted_registry_number' => $record?->registry_number,
                'persisted_status' => $record?->status,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifySecretariatCaseClosure(array $result): array
    {
        $caseId = (int) ($result['case_id'] ?? 0);
        $closedBy = (int) ($result['closed_by'] ?? 0);
        $case = $caseId > 0 ? SecretariatCase::query()->find($caseId) : null;
        $verified = $case !== null
            && (string) $case->status === 'closed'
            && $closedBy > 0
            && (int) ($case->closed_by ?? 0) === $closedBy;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => 'internal_case_lifecycle',
            'evidence' => [
                'case_id' => $caseId,
                'persisted_status' => $case?->status,
                'closed_by' => $closedBy,
                'persisted_closed_by' => $case?->closed_by,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyNajmBaharTransaction(array $result): array
    {
        $intentId = (int) ($result['intent_id'] ?? 0);
        $transactionId = (int) ($result['transaction_id'] ?? 0);
        $intent = $intentId > 0 ? FounderNajmBaharTransactionIntent::query()->find($intentId) : null;
        $transactionExists = $transactionId > 0
            ? DB::table('najm_transactions')->where('id', $transactionId)->exists()
            : false;
        $verified = $intent !== null
            && (string) $intent->status === 'executed'
            && (int) ($intent->transaction_id ?? 0) === $transactionId
            && $transactionExists;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => 'canonical_internal_ledger_transaction',
            'evidence' => [
                'intent_id' => $intentId,
                'intent_status' => $intent?->status,
                'transaction_id' => $transactionId,
                'transaction_persisted' => $transactionExists,
                'tracking_number' => $result['tracking_number'] ?? null,
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyNajmBaharMonetaryPolicy(array $result): array
    {
        $policyVersionId = (int) ($result['policy_version_id'] ?? 0);
        $version = (int) ($result['version'] ?? 0);
        $approvedBy = (int) ($result['approved_by'] ?? 0);
        $policy = $policyVersionId > 0 ? MonetaryPolicyVersion::query()->find($policyVersionId) : null;

        $verified = $policy !== null
            && (string) $policy->status === 'active'
            && (int) $policy->version === $version
            && $approvedBy > 0
            && (int) ($policy->approved_by ?? 0) === $approvedBy
            && $policy->approved_at !== null
            && $policy->effective_from !== null;

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => 'versioned_monetary_policy_activation',
            'evidence' => [
                'policy_version_id' => $policyVersionId,
                'version' => $version,
                'persisted_status' => $policy?->status,
                'approved_by' => $approvedBy,
                'persisted_approved_by' => $policy?->approved_by,
                'approved_at' => $policy?->approved_at?->toISOString(),
                'effective_from' => $policy?->effective_from?->toISOString(),
            ],
        ];
    }

    /** @param array<string,mixed> $result */
    protected function verifyStockSettlement(array $result): array
    {
        $auctionId = (int) ($result['auction_id'] ?? 0);
        $auction = $auctionId > 0 ? Auction::query()->find($auctionId) : null;
        $statusVerified = $auction !== null && (string) $auction->status === 'settled';
        $canonical = $auction?->hasCanonicalGolPricing() ?? false;
        $allocationCount = 0;
        $unsettledAllocationCount = 0;
        $reconciliationRequiredCount = 0;

        if ($canonical && $auction !== null) {
            $allocations = StockSettlementAllocation::query()
                ->where('auction_id', $auction->id)
                ->where('state', '!=', StockSettlementAllocation::CANCELLED);
            $allocationCount = (clone $allocations)->count();
            $unsettledAllocationCount = (clone $allocations)
                ->where('state', '!=', StockSettlementAllocation::SETTLED)
                ->count();
            $reconciliationRequiredCount = (clone $allocations)
                ->where('state', StockSettlementAllocation::RECONCILIATION_REQUIRED)
                ->count();
        }

        $verified = $statusVerified
            && (! $canonical || ($allocationCount > 0 && $unsettledAllocationCount === 0 && $reconciliationRequiredCount === 0));

        return [
            'verified' => $verified,
            'status' => $verified ? 'verified' : 'failed',
            'verification_scope' => $canonical ? 'canonical_gol_settlement' : 'legacy_internal_settlement',
            'evidence' => [
                'auction_id' => $auctionId,
                'persisted_status' => $auction?->status,
                'canonical_gol_pricing' => $canonical,
                'allocation_count' => $allocationCount,
                'unsettled_allocation_count' => $unsettledAllocationCount,
                'reconciliation_required_count' => $reconciliationRequiredCount,
            ],
        ];
    }
}
