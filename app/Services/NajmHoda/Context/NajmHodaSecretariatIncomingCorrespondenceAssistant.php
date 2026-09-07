<?php

namespace App\Services\NajmHoda\Context;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * S7 guided incoming correspondence preparation.
 *
 * Creates only an incoming correspondence Draft through the existing S4
 * aggregate. It cannot register, dispatch, refer, acknowledge or publish it.
 */
class NajmHodaSecretariatIncomingCorrespondenceAssistant
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
            return $this->response('پیش‌نویس نامه وارده لغو شد و هیچ مکاتبه‌ای ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->saveConfirmed($actor, $office, $group, $pending);
        }

        if (! $this->looksLikeIncomingDraftRequest($message)) {
            return null;
        }

        $payload = $this->parseIncoming($message, $office);
        if ($payload === null) {
            return $this->response(
                "برای ثبت پیش‌نویس نامه وارده، فرستنده، عنوان، متن و زمان دریافت را مشخص کنید.\n"
                . "مثال: «پیش‌نویس نامه وارده بساز | فرستنده: سازمان نمونه | ایمیل: office@example.org | عنوان: درخواست همکاری | متن: ... | دریافت: 2026-08-19T12:00:00+04:00 | شماره خارجی: EXT-123 | کانال: email»",
                'needs_input'
            );
        }

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => 'incoming_letter',
            'direction' => 'incoming',
            'confidentiality' => $payload['attributes']['confidentiality'],
        ]);
        $probe->setRelation('office', $office);

        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('شما مجوز تهیه پیش‌نویس نامه وارده در این دفتر را ندارید.', 'blocked');
        }

        Cache::put($pendingKey, [
            'actor_id' => (int) $actor->id,
            'office_id' => (int) $office->id,
            'group_id' => (int) $group->id,
            'payload' => $payload,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($payload)
            . "\n\nاین فقط پیش‌نمایش است و هنوز هیچ نامه‌ای در دبیرخانه ثبت نشده است. "
            . "برای ذخیره همین نسخه فقط به‌صورت Draft، «ذخیره نامه» بفرستید. برای انصراف «لغو» بفرستید.",
            'awaiting_confirmation',
            ['correspondence' => $payload]
        );
    }

    /** @param array<string,mixed> $pending */
    private function saveConfirmed(User $actor, SecretariatOffice $office, Group $group, array $pending): array
    {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id
            || (int) ($pending['office_id'] ?? 0) !== (int) $office->id
            || (int) ($pending['group_id'] ?? 0) !== (int) $group->id
            || ! is_array($pending['payload'] ?? null)) {
            return $this->response('درخواست ذخیره نامه معتبر نیست. لطفاً پیش‌نویس را دوباره آماده کنید.', 'blocked');
        }

        $payload = (array) $pending['payload'];
        $attributes = is_array($payload['attributes'] ?? null) ? (array) $payload['attributes'] : [];
        $sender = is_array($payload['sender'] ?? null) ? (array) $payload['sender'] : [];

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => 'incoming_letter',
            'direction' => 'incoming',
            'confidentiality' => $attributes['confidentiality'] ?? $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);

        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('مجوز تهیه پیش‌نویس نامه دیگر معتبر نیست؛ هیچ مکاتبه‌ای ایجاد نشد.', 'blocked');
        }

        $record = $this->correspondence->createDraft(
            $office,
            $actor,
            'incoming',
            $attributes,
            [
                [
                    'role' => 'sender',
                    'party_type' => 'external',
                    'display_name' => (string) ($sender['display_name'] ?? ''),
                    'organization_name' => $sender['organization_name'] ?? null,
                    'email' => $sender['email'] ?? null,
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'group',
                    'group_id' => $group->id,
                    'display_name' => $group->name,
                ],
            ],
        );

        if (in_array($record->confidentiality, ['restricted', 'confidential'], true)) {
            $this->acl->grant($record, 'user', $actor->id, $actor);
        }

        return $this->response(
            "پیش‌نویس نامه وارده با موفقیت ذخیره شد (Draft #{$record->id}). این نامه هنوز ثبت رسمی، ارجاع، ابلاغ یا منتشر نشده است.",
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
    private function parseIncoming(string $message, SecretariatOffice $office): ?array
    {
        $senderName = $this->extract($message, ['فرستنده'], ['سازمان', 'ایمیل', 'عنوان', 'موضوع', 'خلاصه', 'متن', 'دریافت', 'شماره خارجی', 'کانال', 'محرمانگی']);
        $title = $this->extract($message, ['عنوان'], ['موضوع', 'خلاصه', 'متن', 'دریافت', 'شماره خارجی', 'کانال', 'محرمانگی']);
        $body = $this->extract($message, ['متن', 'بدنه'], ['دریافت', 'شماره خارجی', 'کانال', 'محرمانگی']);
        $receivedRaw = $this->extract($message, ['دریافت'], ['شماره خارجی', 'کانال', 'محرمانگی']);
        if ($senderName === '' || $title === '' || $body === '' || $receivedRaw === '') {
            return null;
        }

        try {
            $receivedAt = CarbonImmutable::parse($receivedRaw);
        } catch (Throwable) {
            return null;
        }

        $organization = $this->extract($message, ['سازمان'], ['ایمیل', 'عنوان', 'موضوع', 'خلاصه', 'متن', 'دریافت', 'شماره خارجی', 'کانال', 'محرمانگی']);
        $email = $this->extract($message, ['ایمیل'], ['عنوان', 'موضوع', 'خلاصه', 'متن', 'دریافت', 'شماره خارجی', 'کانال', 'محرمانگی']);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $subject = $this->extract($message, ['موضوع'], ['خلاصه', 'متن', 'دریافت', 'شماره خارجی', 'کانال', 'محرمانگی']);
        $summary = $this->extract($message, ['خلاصه'], ['متن', 'دریافت', 'شماره خارجی', 'کانال', 'محرمانگی']);
        $externalReference = $this->extract($message, ['شماره خارجی'], ['کانال', 'محرمانگی']);
        $channel = $this->tokenValue($message, ['کانال']) ?: 'other';
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
                'received_at' => $receivedAt,
                'external_reference_number' => $externalReference !== '' ? mb_substr($externalReference, 0, 255) : null,
                'source_type' => 'external_document',
                'record_metadata' => ['prepared_by' => 'najm_hoda_s7'],
                'correspondence_metadata' => ['prepared_by' => 'najm_hoda_s7'],
            ],
            'sender' => [
                'display_name' => mb_substr($senderName, 0, 255),
                'organization_name' => $organization !== '' ? mb_substr($organization, 0, 255) : null,
                'email' => $email !== '' ? mb_substr($email, 0, 320) : null,
            ],
            'received_at_iso' => $receivedAt->toIso8601String(),
        ];
    }

    /** @param array<string,mixed> $payload */
    private function preview(array $payload): string
    {
        $attributes = (array) ($payload['attributes'] ?? []);
        $sender = (array) ($payload['sender'] ?? []);
        $lines = [
            'پیش‌نمایش نامه وارده:',
            'فرستنده: ' . (string) ($sender['display_name'] ?? ''),
            'عنوان: ' . (string) ($attributes['title'] ?? ''),
            'دریافت: ' . (string) ($payload['received_at_iso'] ?? ''),
            'کانال: ' . (string) ($attributes['channel'] ?? ''),
            'محرمانگی: ' . (string) ($attributes['confidentiality'] ?? ''),
        ];
        if (! empty($attributes['external_reference_number'])) {
            $lines[] = 'شماره خارجی: ' . $attributes['external_reference_number'];
        }
        if (! empty($sender['organization_name'])) {
            $lines[] = 'سازمان: ' . $sender['organization_name'];
        }
        if (! empty($sender['email'])) {
            $lines[] = 'ایمیل: ' . $sender['email'];
        }
        if (! empty($attributes['subject'])) {
            $lines[] = 'موضوع: ' . $attributes['subject'];
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

    private function looksLikeIncomingDraftRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        $letter = mb_stripos($plain, 'نامه') !== false;
        $incoming = mb_stripos($plain, 'وارده') !== false || mb_stripos($plain, 'incoming') !== false;
        $draft = mb_stripos($plain, 'پیش‌نویس') !== false || mb_stripos($plain, 'draft') !== false;
        $verb = mb_stripos($plain, 'بساز') !== false || mb_stripos($plain, 'آماده') !== false
            || mb_stripos($plain, 'ثبت') !== false || mb_stripos($plain, 'تهیه') !== false;
        return $letter && $incoming && $draft && $verb;
    }

    private function isSaveConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ذخیره نامه', 'ذخیره نامه وارده', 'نامه را ذخیره کن', 'save letter'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $officeId): string
    {
        return 'najm_hoda:secretariat_incoming_correspondence:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $officeId;
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
            ['فرستنده','سازمان','ایمیل','عنوان','موضوع','خلاصه','متن','بدنه','دریافت','شماره خارجی','کانال','محرمانگی']
        ));
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_incoming_correspondence',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
