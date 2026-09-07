<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Private, page-aware Secretariat drafting surface for Najm Hoda.
 *
 * Preview surfaces remain pure. Any deterministic domain mutation happens only
 * after explicit confirmation and a fresh server-side policy/resource check.
 */
class NajmHodaSecretariatDraftAssistant
{
    public function __construct(
        private readonly SecretariatRecordService $records,
        private readonly NajmHodaSecretariatDraftRevisionAssistant $revisions,
        private readonly NajmHodaSecretariatCorrespondenceRouter $correspondence,
        private readonly NajmHodaSecretariatCaseAssistant $cases,
        private readonly NajmHodaSecretariatEvidenceDraftAssistant $evidenceDrafts,
    ) {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if (! $this->isSecretariatPage($pageContext)) {
            return null;
        }

        if ((string) ($pageContext['resource_type'] ?? '') === 'secretariat_record') {
            $revisionResponse = $this->revisions->intercept($actor, $pageContext, $message, $conversationId);
            if (is_array($revisionResponse)) {
                return $revisionResponse;
            }
        }

        $officeId = $this->officeId($pageContext);
        if ($officeId <= 0) {
            return null;
        }

        $office = SecretariatOffice::query()->find($officeId);
        if (! $office) {
            return null;
        }

        $caseResponse = $this->cases->intercept($actor, $pageContext, $message, $conversationId);
        if (is_array($caseResponse)) {
            return $caseResponse;
        }

        $correspondenceResponse = $this->correspondence->intercept(
            $actor,
            $pageContext,
            $message,
            $conversationId
        );
        if (is_array($correspondenceResponse)) {
            return $correspondenceResponse;
        }

        $evidenceDraftResponse = $this->evidenceDrafts->intercept(
            $actor,
            $office,
            $pageContext,
            $message,
            $conversationId
        );
        if (is_array($evidenceDraftResponse)) {
            return $evidenceDraftResponse;
        }

        $pendingKey = $this->pendingKey($actor->id, $conversationId, $office->id);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->response('پیش‌نویس پیشنهادی لغو شد و هیچ سندی در دبیرخانه ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->saveConfirmed($actor, $office, $pending);
        }

        if (! $this->looksLikeDraftRequest($message)) {
            return null;
        }

        $payload = $this->parseDraft($message, $office);
        if ($payload === null) {
            return $this->response(
                "برای آماده‌سازی پیش‌نویس، دست‌کم عنوان و متن را مشخص کنید.\n"
                . "مثال: «پیش‌نویس سند بساز | عنوان: گزارش جلسه | متن: ... | نوع: official_report | محرمانگی: office_members»",
                'needs_input'
            );
        }

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'confidentiality' => $payload['confidentiality'],
        ]);
        $probe->setRelation('office', $office);

        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('شما مجوز ایجاد پیش‌نویس در این دفتر دبیرخانه را ندارید.', 'blocked');
        }

        Cache::put($pendingKey, [
            'office_id' => (int) $office->id,
            'actor_id' => (int) $actor->id,
            'payload' => $payload,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($payload)
            . "\n\nاین فقط پیش‌نمایش است و هنوز هیچ رکوردی ایجاد نشده است. "
            . "اگر می‌خواهید همین نسخه فقط به‌صورت Draft در دبیرخانه ذخیره شود، «ذخیره پیش‌نویس» بفرستید. برای انصراف «لغو» بفرستید.",
            'awaiting_confirmation',
            ['draft' => $payload]
        );
    }

    /** @param array<string,mixed> $pending */
    private function saveConfirmed(User $actor, SecretariatOffice $office, array $pending): array
    {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id
            || (int) ($pending['office_id'] ?? 0) !== (int) $office->id
            || ! is_array($pending['payload'] ?? null)) {
            return $this->response('درخواست ذخیره معتبر نیست. لطفاً پیش‌نویس را دوباره آماده کنید.', 'blocked');
        }

        $payload = (array) $pending['payload'];
        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'status' => 'draft',
            'confidentiality' => $payload['confidentiality'] ?? $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);

        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('مجوز ایجاد پیش‌نویس دیگر معتبر نیست؛ هیچ سندی ایجاد نشد.', 'blocked');
        }

        $record = $this->records->createDraft($office, $actor, $payload);

        return $this->response(
            "پیش‌نویس دبیرخانه با موفقیت ذخیره شد (Draft #{$record->id}). این سند هنوز ارسال، تأیید، ثبت رسمی یا منتشر نشده است.",
            'draft_saved',
            [
                'record_id' => (int) $record->id,
                'office_id' => (int) $office->id,
                'record_status' => (string) $record->status,
            ]
        );
    }

    /** @return array<string,mixed>|null */
    private function parseDraft(string $message, SecretariatOffice $office): ?array
    {
        $title = $this->extract($message, ['عنوان'], ['موضوع', 'خلاصه', 'متن', 'نوع', 'جهت', 'محرمانگی']);
        $body = $this->extract($message, ['متن', 'بدنه'], ['نوع', 'جهت', 'محرمانگی']);
        if ($title === '' || $body === '') {
            return null;
        }

        $subject = $this->extract($message, ['موضوع'], ['خلاصه', 'متن', 'بدنه', 'نوع', 'جهت', 'محرمانگی']);
        $summary = $this->extract($message, ['خلاصه'], ['متن', 'بدنه', 'نوع', 'جهت', 'محرمانگی']);
        $recordType = $this->tokenValue($message, ['نوع']) ?: 'official_note';
        $direction = $this->tokenValue($message, ['جهت']) ?: 'internal';
        $confidentiality = $this->tokenValue($message, ['محرمانگی']) ?: (string) $office->default_confidentiality;

        $allowedTypes = [
            'incoming_letter','outgoing_letter','internal_correspondence','meeting_minute','resolution',
            'formal_decision','contract','memorandum_of_understanding','agreement','policy','directive',
            'official_report','notice','official_note','financial_record','execution_record','election_record',
            'case_record','other',
        ];
        $allowedDirections = ['incoming','outgoing','internal','none'];
        $allowedConfidentialities = ['public','office_members','leadership','restricted','confidential'];

        if (! in_array($recordType, $allowedTypes, true)
            || ! in_array($direction, $allowedDirections, true)
            || ! in_array($confidentiality, $allowedConfidentialities, true)) {
            return null;
        }

        return [
            'record_type' => $recordType,
            'direction' => $direction,
            'title' => mb_substr($title, 0, 500),
            'subject' => $subject !== '' ? mb_substr($subject, 0, 1000) : null,
            'summary' => $summary !== '' ? mb_substr($summary, 0, 5000) : null,
            'body' => $body,
            'confidentiality' => $confidentiality,
        ];
    }

    /** @param array<string,mixed> $payload */
    private function preview(array $payload): string
    {
        $lines = [
            'پیش‌نمایش پیش‌نویس دبیرخانه:',
            'عنوان: ' . $payload['title'],
            'نوع: ' . $payload['record_type'],
            'جهت: ' . $payload['direction'],
            'محرمانگی: ' . $payload['confidentiality'],
        ];
        if (! empty($payload['subject'])) {
            $lines[] = 'موضوع: ' . $payload['subject'];
        }
        if (! empty($payload['summary'])) {
            $lines[] = 'خلاصه: ' . $payload['summary'];
        }
        $lines[] = 'متن: ' . mb_substr((string) $payload['body'], 0, 1200);
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $pageContext */
    private function isSecretariatPage(array $pageContext): bool
    {
        return str_starts_with((string) ($pageContext['page_kind'] ?? ''), 'secretariat_');
    }

    /** @param array<string,mixed> $pageContext */
    private function officeId(array $pageContext): int
    {
        $resourceType = (string) ($pageContext['resource_type'] ?? '');
        if (in_array($resourceType, ['secretariat_office', 'office'], true)) {
            return (int) ($pageContext['resource_id'] ?? 0);
        }

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        return (int) ($resource['office_id'] ?? $pageContext['office_id'] ?? 0);
    }

    private function looksLikeDraftRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        $draft = mb_stripos($plain, 'پیش‌نویس') !== false || mb_stripos($plain, 'draft') !== false;
        $verb = mb_stripos($plain, 'بساز') !== false || mb_stripos($plain, 'آماده') !== false
            || mb_stripos($plain, 'بنویس') !== false || mb_stripos($plain, 'تهیه') !== false;
        return $draft && $verb;
    }

    private function isSaveConfirmation(string $message): bool
    {
        $plain = trim(mb_strtolower($message));
        return in_array($plain, ['ذخیره پیش‌نویس', 'پیش‌نویس را ذخیره کن', 'save draft'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $officeId): string
    {
        return 'najm_hoda:secretariat_draft:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $officeId;
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
        $value = $this->extract($text, $labels, ['عنوان','موضوع','خلاصه','متن','بدنه','نوع','جهت','محرمانگی']);
        return trim($value);
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_draft',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
