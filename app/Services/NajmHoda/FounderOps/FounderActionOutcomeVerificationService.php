<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderAnnouncementDraft;
use App\Models\SupportReplyDraft;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\DB;
use Throwable;

class FounderActionOutcomeVerificationService
{
    public function __construct(protected RuntimeEventBus $events) {}

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
            // Outcome telemetry is secondary to an already-committed canonical mutation.
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
}
