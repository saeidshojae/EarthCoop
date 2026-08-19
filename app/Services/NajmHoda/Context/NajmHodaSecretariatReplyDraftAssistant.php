<?php

namespace App\Services\NajmHoda\Context;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatParty;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use App\Modules\Secretariat\Services\SecretariatKnowledgeRetrievalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * S7 evidence-grounded reply drafting.
 *
 * A browser record id is only a hint: page context has already been resolved by
 * NajmHodaPageContextResolver and this service re-loads and re-authorizes every
 * source/evidence record. Preview has zero business mutation. Confirmation can
 * create only a Draft response plus the explicit responds_to relation.
 */
class NajmHodaSecretariatReplyDraftAssistant
{
    public function __construct(
        private readonly SecretariatCorrespondenceService $correspondence,
        private readonly SecretariatKnowledgeRetrievalService $knowledge,
        private readonly SecretariatAclService $acl,
    ) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if ((string) ($pageContext['resource_type'] ?? '') !== 'secretariat_record') {
            return null;
        }

        $sourceId = (int) ($pageContext['resource_id'] ?? 0);
        if ($sourceId <= 0) {
            return null;
        }

        $pendingKey = $this->pendingKey($actor->id, $conversationId, $sourceId);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->response('پیشنهاد پاسخ لغو شد و هیچ Draft یا رابطه‌ای ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->saveConfirmed($actor, $pending);
        }

        if (! $this->looksLikeReplyRequest($message)) {
            return null;
        }

        $source = SecretariatRecord::query()
            ->with(['office', 'currentVersion', 'parties', 'correspondenceDetail'])
            ->find($sourceId);

        if (! $this->validSource($source, $actor)) {
            return $this->response('نامه یا مکاتبه رسمی و مجازِ قابل پاسخ در این صفحه پیدا نشد.', 'blocked');
        }

        /** @var SecretariatOffice $office */
        $office = $source->office;
        if ($office->scope_type !== 'group' || $office->scope_id === null) {
            return $this->response('در این مرحله، پاسخ هدایت‌شده فقط برای دبیرخانه‌های گروهی فعال است.', 'blocked');
        }

        $group = Group::query()->find((int) $office->scope_id);
        $sourceSender = $source->parties->firstWhere('role', 'sender');
        if (! $group || ! $sourceSender instanceof SecretariatParty || ! $this->supportedReplyRecipient($sourceSender, $source)) {
            return $this->response('فرستنده معتبر نامه مبدأ برای ساخت پاسخ پیدا نشد.', 'blocked');
        }

        $replyDirection = $source->direction === 'incoming' ? 'outgoing' : 'internal';
        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => $replyDirection === 'outgoing' ? 'outgoing_letter' : 'internal_correspondence',
            'direction' => $replyDirection,
            'confidentiality' => $source->confidentiality,
        ]);
        $probe->setRelation('office', $office);
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('شما مجوز تهیه Draft پاسخ در این دفتر دبیرخانه را ندارید.', 'blocked');
        }

        if ($source->confidentiality === 'confidential') {
            $this->acl->auditSensitiveAccess($source, $actor, [
                'channel' => 'reply_draft_preview',
            ]);
        }

        $focus = $this->extractFocus($message);
        $query = trim(implode(' ', array_filter([
            (string) $source->title,
            (string) $source->subject,
            $focus,
        ])));

        $evidence = $this->knowledge->retrieve(
            $actor,
            $query !== '' ? $query : ('پاسخ ' . $source->id),
            ['office_id' => (int) $office->id],
            8,
            1200,
            6000,
        )
            ->reject(fn (array $packet) => (int) ($packet['record_id'] ?? 0) === (int) $source->id)
            ->filter(fn (array $packet) => trim((string) ($packet['registry_number'] ?? '')) !== '')
            ->take(5)
            ->values();

        $payload = $this->buildPayload($source, $sourceSender, $group, $replyDirection, $focus, $evidence->all());
        Cache::put($pendingKey, [
            'actor_id' => (int) $actor->id,
            'source_record_id' => (int) $source->id,
            'source_version_id' => (int) $source->current_version_id,
            'office_id' => (int) $office->id,
            'group_id' => (int) $group->id,
            'evidence_versions' => $evidence->mapWithKeys(function (array $packet) {
                $record = SecretariatRecord::query()->find((int) $packet['record_id']);
                return [(int) $packet['record_id'] => (int) ($record?->current_version_id ?? 0)];
            })->all(),
            'payload' => $payload,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($source, $payload, $evidence->all())
            . "\n\nاین فقط پیش‌نمایش است؛ هیچ پاسخ، رابطه یا ابلاغی ایجاد نشده است. "
            . "برای ذخیره همین نسخه فقط به‌صورت Draft و ایجاد رابطه responds_to، «ذخیره پاسخ» بفرستید.",
            'awaiting_confirmation',
            [
                'source_record_id' => (int) $source->id,
                'evidence' => $evidence->all(),
                'reply' => $payload,
            ]
        );
    }

    /** @param array<string,mixed> $pending */
    private function saveConfirmed(User $actor, array $pending): array
    {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id
            || ! is_array($pending['payload'] ?? null)
            || ! is_array($pending['evidence_versions'] ?? null)) {
            return $this->response('درخواست ذخیره پاسخ معتبر نیست؛ دوباره پیش‌نمایش بگیرید.', 'blocked');
        }

        $source = SecretariatRecord::query()->with(['office', 'parties'])->find((int) ($pending['source_record_id'] ?? 0));
        if (! $this->validSource($source, $actor)
            || (int) $source->current_version_id !== (int) ($pending['source_version_id'] ?? 0)
            || (int) $source->office_id !== (int) ($pending['office_id'] ?? 0)) {
            return $this->response('نامه مبدأ یا مجوز آن از زمان پیش‌نمایش تغییر کرده است؛ هیچ Draftی ایجاد نشد.', 'stale_preview');
        }

        foreach ((array) $pending['evidence_versions'] as $recordId => $versionId) {
            $evidence = SecretariatRecord::query()->find((int) $recordId);
            if (! $evidence
                || (int) $evidence->current_version_id !== (int) $versionId
                || trim((string) $evidence->registry_number) === ''
                || ! Gate::forUser($actor)->allows('view', $evidence)) {
                return $this->response('یکی از شواهد رسمی از زمان پیش‌نمایش تغییر کرده یا دیگر مجاز نیست؛ دوباره پیش‌نمایش بگیرید.', 'stale_preview');
            }
        }

        $payload = (array) $pending['payload'];
        $office = $source->office;
        $group = Group::query()->find((int) ($pending['group_id'] ?? 0));
        if (! $office || ! $group || (int) $office->scope_id !== (int) $group->id) {
            return $this->response('دامنه دفتر از زمان پیش‌نمایش تغییر کرده است.', 'stale_preview');
        }

        $direction = (string) ($payload['direction'] ?? '');
        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => $direction === 'outgoing' ? 'outgoing_letter' : 'internal_correspondence',
            'direction' => $direction,
            'confidentiality' => $payload['attributes']['confidentiality'] ?? $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('مجوز ایجاد Draft پاسخ دیگر معتبر نیست؛ هیچ چیزی ایجاد نشد.', 'blocked');
        }

        $sourceSender = $source->parties->firstWhere('role', 'sender');
        if (! $sourceSender instanceof SecretariatParty || ! $this->supportedReplyRecipient($sourceSender, $source)) {
            return $this->response('طرف نامه مبدأ از زمان پیش‌نمایش تغییر کرده است.', 'stale_preview');
        }

        $recipient = $this->partySnapshot($sourceSender, 'recipient');
        $record = DB::transaction(function () use ($office, $actor, $group, $direction, $payload, $recipient, $source) {
            $reply = $this->correspondence->createDraft(
                $office,
                $actor,
                $direction,
                (array) $payload['attributes'],
                [
                    [
                        'role' => 'sender',
                        'party_type' => 'group',
                        'group_id' => $group->id,
                        'display_name' => $group->name,
                    ],
                    $recipient,
                ],
            );

            $this->correspondence->linkResponse($reply, $source, $actor);
            if (in_array($reply->confidentiality, ['restricted', 'confidential'], true)) {
                $this->acl->grant($reply, 'user', $actor->id, $actor);
            }
            return $reply;
        });

        return $this->response(
            "Draft پاسخ با موفقیت ذخیره شد (Draft #{$record->id}) و رابطه responds_to با نامه مبدأ ثبت شد. پاسخ هنوز ثبت رسمی، dispatch یا ارسال نشده است.",
            'draft_saved',
            [
                'record_id' => (int) $record->id,
                'record_status' => (string) $record->status,
                'source_record_id' => (int) $source->id,
            ]
        );
    }

    private function validSource(?SecretariatRecord $source, User $actor): bool
    {
        return $source !== null
            && in_array((string) $source->direction, ['incoming', 'internal'], true)
            && in_array((string) $source->record_type, ['incoming_letter', 'internal_correspondence'], true)
            && trim((string) $source->registry_number) !== ''
            && $source->current_version_id !== null
            && Gate::forUser($actor)->allows('view', $source);
    }

    private function supportedReplyRecipient(SecretariatParty $party, SecretariatRecord $source): bool
    {
        if ($source->direction === 'incoming') {
            return in_array($party->party_type, ['external', 'user', 'group'], true);
        }
        return in_array($party->party_type, ['user', 'group'], true);
    }

    /** @return array<string,mixed> */
    private function partySnapshot(SecretariatParty $party, string $role): array
    {
        return [
            'role' => $role,
            'party_type' => $party->party_type,
            'user_id' => $party->user_id,
            'group_id' => $party->group_id,
            'display_name' => $party->display_name,
            'organization_name' => $party->organization_name,
            'email' => $party->email,
            'phone' => $party->phone,
            'address' => $party->address,
            'metadata' => ['snapshot_from_record_id' => (int) $party->record_id],
        ];
    }

    /** @param array<int,array<string,mixed>> $evidence */
    private function buildPayload(SecretariatRecord $source, SecretariatParty $sender, Group $group, string $direction, string $focus, array $evidence): array
    {
        $reference = $source->registry_number ?: ('Record #' . $source->id);
        $subject = trim((string) ($source->subject ?: $source->title));
        $lines = [
            'با سلام،',
            '',
            "در پاسخ به مکاتبه {$reference} با موضوع «{$subject}»، سوابق رسمیِ قابل مشاهده و مرتبط زیر برای بررسی مبنای پاسخ قرار گرفته‌اند:",
        ];
        foreach ($evidence as $index => $packet) {
            $number = $packet['registry_number'] ?? ('Record #' . ($packet['record_id'] ?? '?'));
            $title = trim((string) ($packet['title'] ?? ''));
            $excerpt = trim((string) ($packet['excerpt'] ?? ''));
            $lines[] = ($index + 1) . ". {$number} — {$title}";
            if ($excerpt !== '') {
                $lines[] = '   ' . mb_substr(preg_replace('/\s+/u', ' ', $excerpt) ?? $excerpt, 0, 700);
            }
        }
        if ($evidence === []) {
            $lines[] = '— سابقه رسمی مرتبط دیگری در محدوده مجاز شما بازیابی نشد.';
        }
        if ($focus !== '') {
            $lines[] = '';
            $lines[] = 'محور درخواستی برای پاسخ: ' . mb_substr($focus, 0, 1000);
        }
        $lines[] = '';
        $lines[] = 'متن فوق پیش‌نویس مستند برای بازبینی انسانی است و فقط بر سوابق نمایش‌داده‌شده تکیه دارد؛ پیش از ارسال باید نتیجه‌گیری و عبارت‌بندی نهایی توسط مسئول مجاز بررسی شود.';

        return [
            'direction' => $direction,
            'recipient_snapshot' => $this->partySnapshot($sender, 'recipient'),
            'attributes' => [
                'title' => mb_substr('پاسخ به: ' . $source->title, 0, 500),
                'subject' => $source->subject ? mb_substr('پاسخ به: ' . $source->subject, 0, 500) : null,
                'summary' => $focus !== '' ? mb_substr($focus, 0, 1500) : 'پیش‌نویس پاسخ مبتنی بر سوابق رسمی مجاز',
                'body' => implode("\n", $lines),
                'confidentiality' => $source->confidentiality,
                'channel' => $source->correspondenceDetail?->channel,
                'source_type' => 'manual',
                'record_metadata' => [
                    'prepared_by' => 'najm_hoda_s7',
                    'reply_to_record_id' => (int) $source->id,
                    'grounding_evidence' => collect($evidence)->map(fn (array $packet) => [
                        'record_id' => (int) ($packet['record_id'] ?? 0),
                        'registry_number' => $packet['registry_number'] ?? null,
                    ])->values()->all(),
                ],
                'correspondence_metadata' => ['prepared_by' => 'najm_hoda_s7', 'reply_to_record_id' => (int) $source->id],
            ],
        ];
    }

    /** @param array<string,mixed> $payload @param array<int,array<string,mixed>> $evidence */
    private function preview(SecretariatRecord $source, array $payload, array $evidence): string
    {
        $recipient = (array) ($payload['recipient_snapshot'] ?? []);
        $attributes = (array) ($payload['attributes'] ?? []);
        $lines = [
            'پیش‌نمایش پاسخ مستند:',
            'نامه مبدأ: ' . ($source->registry_number ?: ('Record #' . $source->id)),
            'گیرنده پاسخ: ' . (string) ($recipient['display_name'] ?? ''),
            'عنوان: ' . (string) ($attributes['title'] ?? ''),
            'شواهد رسمی مجاز: ' . count($evidence),
            '',
            mb_substr((string) ($attributes['body'] ?? ''), 0, 5000),
        ];
        return implode("\n", $lines);
    }

    private function extractFocus(string $message): string
    {
        if (preg_match('/(?:محور|هدف|focus)\s*[:：]\s*(.+)$/iu', $message, $match)) {
            return trim((string) $match[1]);
        }
        return '';
    }

    private function looksLikeReplyRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        foreach (['پیش‌نویس پاسخ', 'پاسخ پیشنهادی', 'پاسخ از سابقه', 'پاسخ مستند', 'reply draft', 'draft reply'] as $needle) {
            if (mb_stripos($plain, $needle) !== false) return true;
        }
        return false;
    }

    private function isSaveConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ذخیره پاسخ', 'پاسخ را ذخیره کن', 'save reply'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $sourceId): string
    {
        return 'najm_hoda:secretariat_reply:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $sourceId;
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_reply_draft',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
