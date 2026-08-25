<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Models\SupportReplyDraft;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Database\Eloquent\Model;

class FounderDraftEditingService
{
    public function __construct(
        protected NajmHodaAutonomyApprovalService $approvals,
        protected RuntimeEventBus $events
    ) {}

    /** @return array<string,mixed> */
    public function updateSupport(SupportReplyDraft $draft, string $body, int $actorId): array
    {
        return $this->updateDraft($draft, 'support_reply_draft', ['body' => $body], $actorId);
    }

    /** @return array<string,mixed> */
    public function updateEmail(FounderEmailDraft $draft, string $subject, string $body, int $actorId): array
    {
        return $this->updateDraft($draft, 'founder_email_draft', [
            'subject' => $subject,
            'body' => $body,
        ], $actorId);
    }

    /** @return array<string,mixed> */
    public function updateContent(FounderContentDraft $draft, string $title, string $body, int $actorId, ?int $categoryId = null): array
    {
        return $this->updateDraft($draft, 'founder_content_draft', [
            'title' => $title,
            'body' => $body,
            'category_id' => $categoryId ?? $draft->category_id,
        ], $actorId);
    }

    /** @return array<string,mixed> */
    public function updateAnnouncement(FounderAnnouncementDraft $draft, string $title, string $content, int $actorId): array
    {
        return $this->updateDraft($draft, 'founder_announcement_draft', [
            'title' => $title,
            'content' => $content,
        ], $actorId);
    }

    /** @param array<string,mixed> $attributes @return array<string,mixed> */
    protected function updateDraft(Model $draft, string $entityType, array $attributes, int $actorId): array
    {
        if (! in_array($actorId, $this->founderIds(), true)) {
            return ['success' => false, 'status' => 'forbidden', 'reason' => 'founder_not_authorized'];
        }

        if ((string) $draft->getAttribute('status') !== 'draft') {
            return ['success' => false, 'status' => 'invalid_state', 'reason' => 'draft_not_editable'];
        }

        if ($this->hasPendingApproval($entityType, (int) $draft->getKey())) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'pending_approval_must_be_decided_first'];
        }

        $changedFields = array_keys(array_filter(
            $attributes,
            fn ($value, $key): bool => $draft->getAttribute($key) !== $value,
            ARRAY_FILTER_USE_BOTH
        ));
        if ($changedFields === []) {
            return [
                'success' => true,
                'status' => 'unchanged',
                'entity_type' => $entityType,
                'entity_id' => (int) $draft->getKey(),
                'edited_by' => $actorId,
            ];
        }

        $draft->fill($attributes);
        $draft->save();

        $this->events->emit('najm_hoda.founder_ops.draft.edited', [
            'entity_type' => $entityType,
            'entity_id' => (int) $draft->getKey(),
            'edited_by' => $actorId,
            'changed_fields' => $changedFields,
        ]);

        return [
            'success' => true,
            'status' => 'updated',
            'entity_type' => $entityType,
            'entity_id' => (int) $draft->getKey(),
            'edited_by' => $actorId,
            'changed_fields' => $changedFields,
        ];
    }

    protected function hasPendingApproval(string $entityType, int $entityId): bool
    {
        return collect($this->approvals->pending(200))->contains(function (array $item) use ($entityType, $entityId): bool {
            return (string) data_get($item, 'context.entity_type') === $entityType
                && (int) data_get($item, 'context.entity_id', 0) === $entityId;
        });
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            (array) config('najm-hoda-founder-action-policy.founder_approval.user_ids', [])
        )));
    }
}
