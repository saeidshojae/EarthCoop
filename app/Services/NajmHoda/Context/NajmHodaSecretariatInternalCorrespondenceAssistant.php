<?php

namespace App\Services\NajmHoda\Context;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * S7 guided internal correspondence preparation.
 *
 * The browser/user supplies only a recipient user id hint. Membership and the
 * recipient identity snapshot are resolved again from the server before preview
 * and again before persistence. This service only creates a Draft.
 */
class NajmHodaSecretariatInternalCorrespondenceAssistant
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
            return $this->response('پیش‌نویس مکاتبه داخلی لغو شد و هیچ رکوردی ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->saveConfirmed($actor, $office, $group, $pending);
        }

        if (! $this->looksLikeInternalRequest($message)) {
            return null;
        }

        $payload = $this->parseInternal($message, $office, $group);
        if ($payload === null) {
            return $this->response(
                "برای تهیه مکاتبه داخلی، شناسه کاربر گیرنده، عنوان و متن را مشخص کنید.\n"
                . "مثال: «پیش‌نویس مکاتبه داخلی بساز | گیرنده کاربر: 42 | عنوان: پیگیری مصوبه | متن: ... | محرمانگی: office_members»",
                'needs_input'
            );
        }

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => 'internal_correspondence',
            'direction' => 'internal',
            'confidentiality' => $payload['attributes']['confidentiality'],
        ]);
        $probe->setRelation('office', $office);
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('شما مجوز تهیه مکاتبه داخلی در این دفتر را ندارید.', 'blocked');
        }

        Cache::put($pendingKey, [
            'actor_id' => (int) $actor->id,
            'office_id' => (int) $office->id,
            'group_id' => (int) $group->id,
            'payload' => $payload,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($payload)
            . "\n\nاین فقط پیش‌نمایش است و هنوز هیچ مکاتبه‌ای ایجاد نشده است. "
            . "برای ذخیره همین نسخه فقط به‌صورت Draft، «ذخیره مکاتبه» بفرستید. برای انصراف «لغو» بفرستید.",
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
            return $this->response('درخواست ذخیره مکاتبه معتبر نیست. لطفاً دوباره آن را آماده کنید.', 'blocked');
        }

        $payload = (array) $pending['payload'];
        $attributes = is_array($payload['attributes'] ?? null) ? (array) $payload['attributes'] : [];
        $recipientId = (int) ($payload['recipient_user_id'] ?? 0);
        $recipient = $this->groupMember($group, $recipientId);
        if (! $recipient) {
            return $this->response('گیرنده دیگر عضو این گروه نیست؛ هیچ مکاتبه‌ای ایجاد نشد.', 'blocked');
        }

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'record_type' => 'internal_correspondence',
            'direction' => 'internal',
            'confidentiality' => $attributes['confidentiality'] ?? $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('مجوز تهیه مکاتبه دیگر معتبر نیست؛ هیچ رکوردی ایجاد نشد.', 'blocked');
        }

        $record = $this->correspondence->createDraft(
            $office,
            $actor,
            'internal',
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
                    'party_type' => 'user',
                    'user_id' => $recipient->id,
                    'display_name' => $this->displayName($recipient),
                    'email' => $recipient->email,
                ],
            ],
        );

        if (in_array($record->confidentiality, ['restricted', 'confidential'], true)) {
            $this->acl->grant($record, 'user', $actor->id, $actor);
        }

        return $this->response(
            "پیش‌نویس مکاتبه داخلی با موفقیت ذخیره شد (Draft #{$record->id}). این مکاتبه هنوز ثبت رسمی، ارسال، ارجاع یا ابلاغ نشده است.",
            'draft_saved',
            [
                'record_id' => (int) $record->id,
                'office_id' => (int) $office->id,
                'recipient_user_id' => (int) $recipient->id,
                'record_status' => (string) $record->status,
            ]
        );
    }

    /** @return array<string,mixed>|null */
    private function parseInternal(string $message, SecretariatOffice $office, Group $group): ?array
    {
        $recipientRaw = $this->extract($message, ['گیرنده کاربر'], ['عنوان', 'موضوع', 'خلاصه', 'متن', 'کانال', 'محرمانگی']);
        $title = $this->extract($message, ['عنوان'], ['موضوع', 'خلاصه', 'متن', 'کانال', 'محرمانگی']);
        $body = $this->extract($message, ['متن', 'بدنه'], ['کانال', 'محرمانگی']);
        if (! ctype_digit($recipientRaw) || (int) $recipientRaw <= 0 || $title === '' || $body === '') {
            return null;
        }

        $recipient = $this->groupMember($group, (int) $recipientRaw);
        if (! $recipient) {
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
                'source_type' => 'manual',
                'record_metadata' => ['prepared_by' => 'najm_hoda_s7'],
                'correspondence_metadata' => ['prepared_by' => 'najm_hoda_s7'],
            ],
            'recipient_user_id' => (int) $recipient->id,
            'recipient_display_name' => $this->displayName($recipient),
        ];
    }

    private function groupMember(Group $group, int $userId): ?User
    {
        if ($userId <= 0 || ! DB::table('group_user')
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->exists()) {
            return null;
        }
        return User::query()->find($userId);
    }

    private function displayName(User $user): string
    {
        $name = trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? ''));
        return $name !== '' ? $name : (string) ($user->email ?? ('User #' . $user->id));
    }

    /** @param array<string,mixed> $payload */
    private function preview(array $payload): string
    {
        $attributes = (array) ($payload['attributes'] ?? []);
        $lines = [
            'پیش‌نمایش مکاتبه داخلی:',
            'گیرنده: ' . (string) ($payload['recipient_display_name'] ?? ''),
            'شناسه گیرنده: ' . (string) ($payload['recipient_user_id'] ?? ''),
            'عنوان: ' . (string) ($attributes['title'] ?? ''),
            'کانال: ' . (string) ($attributes['channel'] ?? ''),
            'محرمانگی: ' . (string) ($attributes['confidentiality'] ?? ''),
        ];
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

    private function looksLikeInternalRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        $internal = mb_stripos($plain, 'مکاتبه داخلی') !== false || mb_stripos($plain, 'نامه داخلی') !== false
            || mb_stripos($plain, 'internal correspondence') !== false;
        $draft = mb_stripos($plain, 'پیش‌نویس') !== false || mb_stripos($plain, 'draft') !== false;
        $verb = mb_stripos($plain, 'بساز') !== false || mb_stripos($plain, 'آماده') !== false
            || mb_stripos($plain, 'بنویس') !== false || mb_stripos($plain, 'تهیه') !== false;
        return $internal && $draft && $verb;
    }

    private function isSaveConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ذخیره مکاتبه', 'مکاتبه را ذخیره کن', 'save correspondence'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $officeId): string
    {
        return 'najm_hoda:secretariat_internal_correspondence:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $officeId;
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
            ['گیرنده کاربر','عنوان','موضوع','خلاصه','متن','بدنه','کانال','محرمانگی']
        ));
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_internal_correspondence',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
