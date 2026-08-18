<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatKnowledgeRetrievalService;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only S7 readiness/evidence advisor.
 *
 * It never fills a field, appends a version or changes lifecycle state. It
 * distinguishes deterministic blockers from quality suggestions and can surface
 * only permission-safe, formally registered evidence packets from S6 retrieval.
 */
class NajmHodaSecretariatDraftReadinessAssistant
{
    public function __construct(private readonly SecretariatKnowledgeRetrievalService $knowledge)
    {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message): ?array
    {
        if ((string) ($pageContext['resource_type'] ?? '') !== 'secretariat_record'
            || ! $this->looksLikeReadinessRequest($message)) {
            return null;
        }

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        $recordId = (int) ($resource['id'] ?? $pageContext['resource_id'] ?? 0);
        if ($recordId <= 0) {
            return null;
        }

        $record = SecretariatRecord::query()->with([
            'office',
            'currentVersion',
            'attachments',
            'parties',
            'correspondenceDetail',
            'outgoingRelations',
            'aclEntries',
        ])->find($recordId);

        // Browser resource identifiers are hints only; policy remains authoritative.
        if (! $record || ! Gate::forUser($actor)->allows('view', $record)) {
            return null;
        }

        $blockers = $this->blockers($record);
        $suggestions = $this->suggestions($record);
        $evidence = $this->relatedFormalEvidence($actor, $record);

        return [
            'success' => true,
            'message' => $this->render($record, $blockers, $suggestions, $evidence),
            'agent' => 'secretariat_readiness',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'read_only' => true,
            'record_id' => (int) $record->id,
            'blockers' => $blockers,
            'quality_suggestions' => $suggestions,
            'evidence' => $evidence,
        ];
    }

    /** @return array<int,string> */
    private function blockers(SecretariatRecord $record): array
    {
        $version = $record->currentVersion;
        $blockers = [];

        if ($version === null) {
            $blockers[] = 'نسخه جاری برای سند وجود ندارد.';
            return $blockers;
        }
        if (trim((string) ($version->title ?? $record->title)) === '') {
            $blockers[] = 'عنوان سند خالی است.';
        }
        if (trim((string) $version->body) === '') {
            $blockers[] = 'متن/بدنه نسخه جاری خالی است.';
        }

        if (in_array($record->record_type, ['incoming_letter', 'outgoing_letter', 'internal_correspondence'], true)) {
            if ($record->parties->where('role', 'sender')->isEmpty()) {
                $blockers[] = 'فرستنده مکاتبه ثبت نشده است.';
            }
            if ($record->parties->where('role', 'recipient')->isEmpty()) {
                $blockers[] = 'گیرنده مکاتبه ثبت نشده است.';
            }
            if ($record->correspondenceDetail === null) {
                $blockers[] = 'جزئیات مکاتبه ثبت نشده است.';
            } elseif ($record->record_type === 'incoming_letter' && $record->correspondenceDetail->received_at === null) {
                $blockers[] = 'تاریخ دریافت نامه وارده ثبت نشده است.';
            }
        }

        if ($record->record_type === 'meeting_minute'
            && ($record->source_type !== 'meeting_minute' || $record->source_id === null)) {
            $blockers[] = 'صورت‌جلسه به منبع Meeting Minute معتبر متصل نیست.';
        }
        if ($record->record_type === 'resolution'
            && ($record->source_type !== 'governance_resolution' || $record->source_id === null)) {
            $blockers[] = 'مصوبه به Governance Resolution معتبر متصل نیست.';
        }
        if ($record->record_type === 'execution_record') {
            if ($record->source_type !== 'action_item' || $record->source_id === null) {
                $blockers[] = 'گزارش اجرا به Action Item معتبر متصل نیست.';
            }
            if (! $record->outgoingRelations->contains(fn ($relation) => $relation->relation_type === 'report_of')) {
                $blockers[] = 'رابطه report_of با مصوبه رسمی ثبت نشده است.';
            }
        }

        if (in_array($record->confidentiality, ['restricted', 'confidential'], true)) {
            $activeAcl = $record->aclEntries->contains(function ($entry): bool {
                return $entry->revoked_at === null
                    && ($entry->expires_at === null || $entry->expires_at->isFuture());
            });
            if (! $activeAcl) {
                $blockers[] = 'برای سند حساس هیچ ACL فعال ثبت نشده است.';
            }
        }

        return array_values(array_unique($blockers));
    }

    /** @return array<int,string> */
    private function suggestions(SecretariatRecord $record): array
    {
        $version = $record->currentVersion;
        $suggestions = [];

        if ($version !== null && trim((string) ($version->subject ?? $record->subject)) === '') {
            $suggestions[] = 'موضوع کوتاه و روشن برای بازیابی و ارجاع بهتر اضافه شود.';
        }
        if ($version !== null && trim((string) ($version->summary ?? $record->summary)) === '') {
            $suggestions[] = 'خلاصه اجرایی کوتاه اضافه شود تا مرور بعدی آسان‌تر باشد.';
        }
        if (in_array($record->record_type, ['contract', 'agreement', 'memorandum_of_understanding', 'financial_record', 'execution_record'], true)
            && $record->attachments->isEmpty()) {
            $suggestions[] = 'در صورت وجود مدرک پشتیبان، پیوست رسمی به نسخه مرتبط اضافه شود.';
        }
        if ($record->status !== 'draft') {
            $suggestions[] = 'این سند دیگر Draft نیست؛ این بررسی صرفاً اطلاعاتی است و ویرایش مستقیم مجاز نیست.';
        }

        return array_values(array_unique($suggestions));
    }

    /** @return array<int,array<string,mixed>> */
    private function relatedFormalEvidence(User $actor, SecretariatRecord $record): array
    {
        $version = $record->currentVersion;
        $query = collect([
            $version?->title ?? $record->title,
            $version?->subject ?? $record->subject,
            $version?->summary ?? $record->summary,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->implode(' ');

        if (trim($query) === '') {
            return [];
        }

        $packets = $this->knowledge->retrieve(
            $actor,
            mb_substr($query, 0, 1800),
            ['office_id' => (int) $record->office_id],
            12,
            900,
            5000,
        );

        return $packets
            ->filter(fn (array $packet) => (int) ($packet['record_id'] ?? 0) !== (int) $record->id)
            ->filter(fn (array $packet) => trim((string) ($packet['registry_number'] ?? '')) !== '')
            ->take(5)
            ->map(fn (array $packet): array => [
                'record_id' => (int) $packet['record_id'],
                'registry_number' => (string) $packet['registry_number'],
                'record_type' => (string) ($packet['record_type'] ?? ''),
                'title' => (string) ($packet['title'] ?? ''),
                'excerpt' => mb_substr((string) ($packet['excerpt'] ?? ''), 0, 700),
            ])
            ->values()
            ->all();
    }

    /** @param array<int,string> $blockers @param array<int,string> $suggestions @param array<int,array<string,mixed>> $evidence */
    private function render(SecretariatRecord $record, array $blockers, array $suggestions, array $evidence): string
    {
        $lines = ['بررسی آمادگی سند #' . $record->id . ' (فقط خواندنی):'];

        if ($blockers === []) {
            $lines[] = '✓ مانع ساختاری شناخته‌شده‌ای در بررسی فعلی پیدا نشد.';
        } else {
            $lines[] = 'مواردی که قبل از ادامه بهتر است برطرف شوند:';
            foreach ($blockers as $item) {
                $lines[] = '• ' . $item;
            }
        }

        if ($suggestions !== []) {
            $lines[] = 'پیشنهادهای تکمیلی:';
            foreach ($suggestions as $item) {
                $lines[] = '• ' . $item;
            }
        }

        if ($evidence !== []) {
            $lines[] = 'اسناد رسمیِ مجاز و احتمالاً مرتبط (فقط برای بررسی انسانی):';
            foreach ($evidence as $packet) {
                $lines[] = sprintf(
                    '• %s — %s — %s',
                    $packet['registry_number'],
                    $packet['record_type'],
                    $packet['title'] !== '' ? $packet['title'] : ('Record #' . $packet['record_id'])
                );
            }
        } else {
            $lines[] = 'سند رسمیِ مرتبط و مجاز دیگری در retrieval فعلی پیشنهاد نشد.';
        }

        $lines[] = 'نجم هدا هیچ فیلدی را خودکار پر نکرد و هیچ تغییری در سند نداد.';
        return implode("\n", $lines);
    }

    private function looksLikeReadinessRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        $recordIntent = mb_stripos($plain, 'پیش‌نویس') !== false
            || mb_stripos($plain, 'سند') !== false
            || mb_stripos($plain, 'draft') !== false;
        $reviewIntent = mb_stripos($plain, 'کم') !== false
            || mb_stripos($plain, 'ناقص') !== false
            || mb_stripos($plain, 'آماده') !== false
            || mb_stripos($plain, 'بررسی') !== false
            || mb_stripos($plain, 'شواهد') !== false
            || mb_stripos($plain, 'مدرک') !== false
            || mb_stripos($plain, 'readiness') !== false;

        return $recordIntent && $reviewIntent;
    }
}
