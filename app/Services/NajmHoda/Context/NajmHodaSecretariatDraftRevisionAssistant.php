<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Safe revision surface for an existing Secretariat Draft.
 *
 * Browser values are lookup hints only. The record is loaded again from the
 * database and policy-checked both when previewing and when applying the exact
 * cached revision. Applying creates a new append-only version through the
 * Secretariat domain service; it never submits or registers the record.
 */
class NajmHodaSecretariatDraftRevisionAssistant
{
    public function __construct(private readonly SecretariatRecordService $records)
    {
    }

    /** @param array<string,mixed> $browserPage */
    public function intercept(User $actor, array $browserPage, string $message, ?int $conversationId = null): ?array
    {
        $record = $this->resolveDraft($actor, $browserPage);
        if (! $record) {
            return null;
        }

        $key = $this->pendingKey($actor->id, $conversationId, $record->id);
        $pending = Cache::get($key);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($key);
            return $this->response('اصلاح پیشنهادی لغو شد و هیچ نسخه جدیدی ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($key);
            return $this->applyConfirmed($actor, $record->id, $pending);
        }

        if (! $this->looksLikeRevisionRequest($message)) {
            return null;
        }

        $content = $this->buildRevisionContent($record, $message);
        if ($content === null) {
            return $this->response(
                "برای اصلاح Draft حداقل یکی از «عنوان»، «موضوع»، «خلاصه» یا «متن» را صریحاً مشخص کنید.\n"
                . "مثال: «اصلاح پیش‌نویس | متن: نسخه اصلاح‌شده گزارش ...»",
                'needs_input'
            );
        }

        Cache::put($key, [
            'actor_id' => (int) $actor->id,
            'record_id' => (int) $record->id,
            'expected_current_version_id' => (int) $record->current_version_id,
            'content' => $content,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($record, $content)
            . "\n\nاین فقط پیش‌نمایش نسخه بعدی است. برای ساخت همین Version روی Draft، «ذخیره اصلاحات» بفرستید. برای انصراف «لغو» بفرستید.",
            'awaiting_confirmation',
            ['record_id' => (int) $record->id, 'revision' => $content]
        );
    }

    /** @param array<string,mixed> $pending */
    private function applyConfirmed(User $actor, int $recordId, array $pending): array
    {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id
            || (int) ($pending['record_id'] ?? 0) !== $recordId
            || ! is_array($pending['content'] ?? null)) {
            return $this->response('درخواست اصلاح معتبر نیست؛ لطفاً اصلاح را دوباره آماده کنید.', 'blocked');
        }

        $record = SecretariatRecord::query()->with('currentVersion')->find($recordId);
        if (! $record || $record->status !== 'draft' || ! Gate::forUser($actor)->allows('update', $record)) {
            return $this->response('Draft دیگر قابل ویرایش نیست یا مجوز شما تغییر کرده است؛ هیچ تغییری اعمال نشد.', 'blocked');
        }

        // Optimistic stale-preview guard: confirmation must apply to the exact
        // version that was previewed, never on top of a newer human/agent edit.
        if ((int) $record->current_version_id !== (int) ($pending['expected_current_version_id'] ?? 0)) {
            return $this->response('Draft پس از این پیش‌نمایش تغییر کرده است. برای جلوگیری از بازنویسی ناخواسته، اصلاح را دوباره پیش‌نمایش کنید.', 'stale_preview');
        }

        $beforeVersion = (int) optional($record->currentVersion)->version_number;
        $updated = $this->records->editDraft(
            $record,
            $actor,
            (array) $pending['content'],
            'Najm Hoda confirmed draft revision'
        )->load('currentVersion');

        return $this->response(
            'اصلاحات به‌صورت Version جدید روی Draft ذخیره شد. سند همچنان Draft است و هنوز ارسال، تأیید یا ثبت رسمی نشده است.',
            'revision_saved',
            [
                'record_id' => (int) $updated->id,
                'record_status' => (string) $updated->status,
                'previous_version' => $beforeVersion,
                'current_version' => (int) optional($updated->currentVersion)->version_number,
            ]
        );
    }

    /** @param array<string,mixed> $browserPage */
    private function resolveDraft(User $actor, array $browserPage): ?SecretariatRecord
    {
        $route = trim((string) ($browserPage['route_name'] ?? ''));
        $resourceType = trim((string) ($browserPage['resource_type'] ?? ''));
        $rawId = $browserPage['resource_id'] ?? null;
        $recordId = is_numeric($rawId) ? (int) $rawId : 0;

        if ($recordId <= 0
            || ! str_starts_with($route, 'secretariat.records.show')
            || ! in_array($resourceType, ['secretariat_record', 'record'], true)) {
            return null;
        }

        $record = SecretariatRecord::query()->with(['office', 'currentVersion'])->find($recordId);
        if (! $record || $record->status !== 'draft' || ! Gate::forUser($actor)->allows('update', $record)) {
            return null;
        }

        return $record;
    }

    /** @return array<string,mixed>|null */
    private function buildRevisionContent(SecretariatRecord $record, string $message): ?array
    {
        $version = $record->currentVersion;
        $fields = [
            'title' => $this->extract($message, ['عنوان'], ['موضوع', 'خلاصه', 'متن', 'بدنه']),
            'subject' => $this->extract($message, ['موضوع'], ['عنوان', 'خلاصه', 'متن', 'بدنه']),
            'summary' => $this->extract($message, ['خلاصه'], ['عنوان', 'موضوع', 'متن', 'بدنه']),
            'body' => $this->extract($message, ['متن', 'بدنه'], ['عنوان', 'موضوع', 'خلاصه']),
        ];

        if (collect($fields)->every(fn (string $value): bool => $value === '')) {
            return null;
        }

        return [
            'title' => $fields['title'] !== '' ? mb_substr($fields['title'], 0, 500) : (string) ($version?->title ?? $record->title),
            'subject' => $fields['subject'] !== '' ? mb_substr($fields['subject'], 0, 1000) : $version?->subject,
            'summary' => $fields['summary'] !== '' ? mb_substr($fields['summary'], 0, 5000) : $version?->summary,
            'body' => $fields['body'] !== '' ? $fields['body'] : $version?->body,
        ];
    }

    /** @param array<string,mixed> $content */
    private function preview(SecretariatRecord $record, array $content): string
    {
        return implode("\n", [
            'پیش‌نمایش اصلاح Draft #' . $record->id . ':',
            'Version فعلی: ' . (int) optional($record->currentVersion)->version_number,
            'عنوان: ' . (string) $content['title'],
            'موضوع: ' . (string) ($content['subject'] ?? ''),
            'خلاصه: ' . (string) ($content['summary'] ?? ''),
            'متن: ' . mb_substr((string) ($content['body'] ?? ''), 0, 1200),
        ]);
    }

    private function looksLikeRevisionRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        $draft = mb_stripos($plain, 'پیش‌نویس') !== false || mb_stripos($plain, 'draft') !== false;
        $verb = mb_stripos($plain, 'اصلاح') !== false || mb_stripos($plain, 'ویرایش') !== false
            || mb_stripos($plain, 'تغییر') !== false || mb_stripos($plain, 'بازنویسی') !== false;
        return $draft && $verb;
    }

    private function isSaveConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ذخیره اصلاحات', 'اصلاحات را ذخیره کن', 'save revision'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $recordId): string
    {
        return 'najm_hoda:secretariat_revision:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $recordId;
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

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_draft_revision',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
