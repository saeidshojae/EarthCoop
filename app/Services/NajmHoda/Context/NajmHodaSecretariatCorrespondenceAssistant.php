<?php

namespace App\Services\NajmHoda\Context;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * S7 guided correspondence preparation.
 *
 * This first correspondence slice intentionally supports only outgoing external
 * letters from a group-scoped Secretariat office. It can preview and create a
 * Draft; it cannot submit, register, dispatch, send or publish anything.
 */
class NajmHodaSecretariatCorrespondenceAssistant
{
    public function __construct(
        private readonly SecretariatCorrespondenceService $correspondence,
        private readonly SecretariatAclService $acl,
    ) {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(
        User $actor,
        array $pageContext,
        string $message,
        ?int $conversationId = null,
    ): ?array {
        if ((string) ($pageContext['page_kind'] ?? '') !== 'secretariat_correspondence_create') {
            return null;
        }

        $officeId = $this->officeId($pageContext);
        if ($officeId <= 0) {
            return null;
        }

        $office = SecretariatOffice::query()->find($officeId);
        if (! $office || $office->scope_type !== 'group' || $office->scope_id === null) {
            return null;
        }

        $group = Group::query()->find((int) $office->scope_id);
        if (! $group) {
            return null;
        }

        $pendingKey = $this->pendingKey($actor->id, $conversationId, $office->id);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->response(
                'پیش‌نویس نامه صادره لغو شد و هیچ مکاتبه‌ای ایجاد نشد.',
                'cancelled'
            );
        }

        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->saveConfirmed($actor, $office, $group, $pending);
        }

        if (! $this->looksLikeOutgoingDraftRequest($message)) {
            return null;
        }

        $payload = $this->parseOutgoing($message, $office);
        if ($payload === null) {
            return $this->response(
                "برای تهیه نامه صادره، گیرنده، عنوان و متن را مشخص کنید.\n"
                . "مثال: «پیش‌نویس نامه صادره بساز | گیرنده: سازمان نمونه | ایمیل: office@example.org | عنوان: درخواست همکاری | متن: ... | کانال: email | محرمانگی: office_members»",
                'needs_input'
            );
        }

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => 'outgoing_letter',
            'direction' => 'outgoing',
            'confidentiality' => $payload['attributes']['confidentiality'],
        ]);
        $probe->setRelation('office', $office);

        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response(
                'شما مجوز تهیه پیش‌نویس نامه در این دفتر دبیرخانه را ندارید.',
                'blocked'
            );
        }

        Cache::put($pendingKey, [
            'actor_id' => (int) $actor->id,
            'office_id' => (int) $office->id,
            'group_id' => (int) $group->id,
            'payload' => $payload,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($payload)
            . "\n\nاین فقط پیش‌نمایش است؛ هنوز هیچ رکورد یا ابلاغی ایجاد نشده است. "
            . "برای ذخیره همین نسخه فقط به‌صورت Draft، «ذخیره نامه» بفرستید. برای انصراف «لغو» بفرستید.",
            'awaiting_confirmation',
            ['correspondence' => $payload]
        );
    }

    /** @param array<string,mixed> $pending */
    private function saveConfirmed(
        User $actor,
        SecretariatOffice $office,
        Group $group,
        array $pending,
    ): array {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id
            || (int) ($pending['office_id'] ?? 0) !== (int) $office->id
            || (int) ($pending['group_id'] ?? 0) !== (int) $group->id
            || ! is_array($pending['payload'] ?? null)) {
            return $this->response(
                'درخواست ذخیره نامه معتبر نیست. لطفاً پیش‌نویس را دوباره آماده کنید.',
                'blocked'
            );
        }

        $payload = (array) $pending['payload'];
        $attributes = is_array($payload['attributes'] ?? null) ? (array) $payload['attributes'] : [];
        $recipient = is_array($payload['recipient'] ?? null) ? (array) $payload['recipient'] : [];

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => 'outgoing_letter',
            'direction' => 'outgoing',
            'confidentiality' => $attributes['confidentiality'] ?? $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);

        // Authority is re-evaluated at execution time; preview-time permission is
        // never treated as durable authority.
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response(
                'مجوز تهیه پیش‌نویس نامه دیگر معتبر نیست؛ هیچ مکاتبه‌ای ایجاد نشد.',
                'blocked'
            );
        }

        $record = $this->correspondence->createDraft(
            $office,
            $actor,
            'outgoing',
            $attributes,
            [
                [
                    'role' => 'sender',
                    'party_type' => 'group',
                    'group_id' => $group->id,
                    'display_name' => $group->name,
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'external',
                    'display_name' => (string) ($recipient['display_name'] ?? ''),
                    'organization_name' => $recipient['organization_name'] ?? null,
                    'email' => $recipient['email'] ?? null,
                ],
            ],
        );

        if (in_array($record->confidentiality, ['restricted', 'confidential'], true)) {
            $this->acl->grant($record, 'user', $actor->id, $actor);
        }

        return $this->response(
            "پیش‌نویس نامه صادره با موفقیت ذخیره شد (Draft #{$record->id}). این نامه هنوز ثبت رسمی، ارسال، ابلاغ یا منتشر نشده است.",
            'draft_saved',
            [
                'record_id' => (int) $record->id,
                'office_id' => (int) $office->id,
                'record_status' => (string) $record->status,
                'record_type' => (string) $record->record_type,
                'direction' => (string) $record->direction,
            ]
        );
    }

    /** @return array<string,mixed>|null */
    private function parseOutgoing(string $message, SecretariatOffice $office): ?array
    {
        $recipientName = $this->extract($message, ['گیرنده'], ['سازمان', 'ایمیل', 'عنوان', 'موضوع', 'خلاصه', 'متن', 'کانال', 'محرمانگی']);
        $title = $this->extract($message, ['عنوان'], ['موضوع', 'خلاصه', 'متن', 'کانال', 'محرمانگی']);
        $body = $this->extract($message, ['متن', 'بدنه'], ['کانال', 'محرمانگی']);
        if ($recipientName === '' || $title === '' || $body === '') {
            return null;
        }

        $organization = $this->extract($message, ['سازمان'], ['ایمیل', 'عنوان', 'موضوع', 'خلاصه', 'متن', 'کانال', 'محرمانگی']);
        $email = $this->extract($message, ['ایمیل'], ['عنوان', 'موضوع', 'خلاصه', 'متن', 'کانال', 'محرمانگی']);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $subject = $this->extract($message, ['موضوع'], ['خلاصه', 'متن', 'کانال', 'محرمانگی']);
        $summary = $this->extract($message, ['خلاصه'], ['متن', 'کانال', 'محرمانگی']);
        $channel = $this->tokenValue($message, ['کانال']) ?: 'internal';
        $confidentiality = $this->tokenValue($message, ['محرمانگی']) ?: (string) $office->default_confidentiality;

        if (! in_array($channel, ['internal', 'email', 'physical', 'api', 'other'], true)
            || ! in_array($confidentiality, ['public', 'office_members', 'leadership', 'restricted', 'confidential'], true)) {
            return null;
        }

        return [
            'attributes' => [
                'title' => mb_substr($title, 0, 500),
                'subject' => $subject !== '' ? mb_substr($subject, 0, 500) : null,
                'summary' => $summary !== '' ? mb_substr($summary, 0, 5000) : null,
                'body' => $body,
                'confidentiality' => $confidentiality,
                'channel' => $channel,
                // `manual` is the existing S1 descriptor source for operator-
                // prepared records. Najm Hoda provenance belongs in metadata,
                // not in an unregistered morph/source token.
                'source_type' => 'manual',
                'record_metadata' => ['prepared_by' => 'najm_hoda_s7'],
                'correspondence_metadata' => ['prepared_by' => 'najm_hoda_s7'],
            ],
            'recipient' => [
                'display_name' => mb_substr($recipientName, 0, 255),
                'organization_name' => $organization !== '' ? mb_substr($organization, 0, 255) : null,
                'email' => $email !== '' ? mb_substr($email, 0, 320) : null,
            ],
        ];
    }

    /** @param array<string,mixed> $payload */
    private function preview(array $payload): string
    {
        $attributes = (array) ($payload['attributes'] ?? []);
        $recipient = (array) ($payload['recipient'] ?? []);
        $lines = [
            'پیش‌نمایش نامه صادره:',
            'گیرنده: ' . (string) ($recipient['display_name'] ?? ''),
            'عنوان: ' . (string) ($attributes['title'] ?? ''),
            'کانال: ' . (string) ($attributes['channel'] ?? ''),
            'محرمانگی: ' . (string) ($attributes['confidentiality'] ?? ''),
        ];
        if (! empty($recipient['organization_name'])) {
            $lines[] = 'سازمان: ' . $recipient['organization_name'];
        }
        if (! empty($recipient['email'])) {
            $lines[] = 'ایمیل: ' . $recipient['email'];
        }
        if (! empty($attributes['subject'])) {
            $lines[] = 'موضوع: ' . $attributes['subject'];
        }
        if (! empty($attributes['summary'])) {
            $lines[] = 'خلاصه: ' . $attributes['summary'];
        }
        $lines[] = 'متن: ' . mb_substr((string) ($attributes['body'] ?? ''), 0, 1200);
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $pageContext */
    private function officeId(array $pageContext): int
    {
        if ((string) ($pageContext['resource_type'] ?? '') === 'secretariat_office') {
            return (int) ($pageContext['resource_id'] ?? 0);
        }
        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        return (int) ($resource['office_id'] ?? 0);
    }

    private function looksLikeOutgoingDraftRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        $letter = mb_stripos($plain, 'نامه') !== false;
        $outgoing = mb_stripos($plain, 'صادره') !== false || mb_stripos($plain, 'outgoing') !== false;
        $draft = mb_stripos($plain, 'پیش‌نویس') !== false || mb_stripos($plain, 'draft') !== false;
        $verb = mb_stripos($plain, 'بساز') !== false || mb_stripos($plain, 'آماده') !== false
            || mb_stripos($plain, 'بنویس') !== false || mb_stripos($plain, 'تهیه') !== false;
        return $letter && $outgoing && $draft && $verb;
    }

    private function isSaveConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ذخیره نامه', 'نامه را ذخیره کن', 'save letter'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $officeId): string
    {
        return 'najm_hoda:secretariat_correspondence:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $officeId;
    }

    private function extract(string $text, array $labels, array $stops): string
    {
        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            $stopPattern = $stops === [] ? '$' : '(?=\\s*[|؛;]\\s*(?:' . implode('|', array_map(fn ($s) => preg_quote($s, '/'), $stops)) . ')\\s*[:：]|$)';
            if (preg_match('/(?:^|[|؛;])\\s*' . $quoted . '\\s*[:：]\\s*(.*?)' . $stopPattern . '/us', $text, $match)) {
                return trim((string) $match[1]);
            }
        }
        return '';
    }

    private function tokenValue(string $text, array $labels): string
    {
        return trim($this->extract(
            $text,
            $labels,
            ['گیرنده','سازمان','ایمیل','عنوان','موضوع','خلاصه','متن','بدنه','کانال','محرمانگی']
        ));
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_correspondence',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
