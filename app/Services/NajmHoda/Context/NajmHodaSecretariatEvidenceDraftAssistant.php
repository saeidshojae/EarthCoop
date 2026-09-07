<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatKnowledgeRetrievalService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Generic evidence-grounded S7 drafting surface.
 *
 * It is intentionally deterministic: evidence excerpts are assembled into a
 * reviewable Draft skeleton. No conclusion or fact is invented by an LLM.
 */
class NajmHodaSecretariatEvidenceDraftAssistant
{
    public function __construct(
        private readonly SecretariatKnowledgeRetrievalService $knowledge,
        private readonly SecretariatRecordService $records,
        private readonly SecretariatAclService $acl,
    ) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, SecretariatOffice $office, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        $key = $this->pendingKey($actor->id, $conversationId, $office->id);
        $pending = Cache::get($key);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($key);
            return $this->response('پیش‌نویس مستند لغو شد و هیچ سندی ایجاد نشد.', 'cancelled');
        }
        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($key);
            return $this->saveConfirmed($actor, $office, $pending);
        }
        if (! $this->looksLikeRequest($message)) {
            return null;
        }

        $topic = $this->extractTopic($message);
        if ($topic === '') {
            return $this->response('موضوع پیش‌نویس مستند را مشخص کنید؛ مثال: «پیش‌نویس مستند از شواهد بساز | موضوع: برنامه آب محله».', 'needs_input');
        }

        $recordType = $this->extractToken($message, 'نوع') ?: 'official_report';
        if (! in_array($recordType, ['official_report', 'official_note'], true)) {
            return $this->response('در این capability فقط official_report یا official_note پشتیبانی می‌شود.', 'needs_input');
        }
        $confidentiality = $this->extractToken($message, 'محرمانگی') ?: (string) $office->default_confidentiality;
        if (! in_array($confidentiality, ['public','office_members','leadership','restricted','confidential'], true)) {
            return $this->response('سطح محرمانگی پیشنهادی معتبر نیست.', 'needs_input');
        }

        $probe = new SecretariatRecord([
            'office_id'=>$office->id,'record_type'=>$recordType,'direction'=>'none','status'=>'draft','confidentiality'=>$confidentiality,
        ]);
        $probe->setRelation('office', $office);
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('شما مجوز ایجاد Draft مستند در این دفتر را ندارید.', 'blocked');
        }

        $packets = $this->knowledge->retrieve($actor, $topic, ['office_id'=>(int)$office->id], 8, 1400, 7000)
            ->filter(fn (array $packet) => trim((string)($packet['registry_number'] ?? '')) !== '')
            ->take(6)
            ->values();

        if ($packets->isEmpty()) {
            return $this->response('هیچ سابقه رسمیِ مجاز و مرتبطی برای این موضوع پیدا نشد؛ نجم هدا بدون evidence پیش‌نویس مستند تولید نمی‌کند.', 'no_evidence');
        }

        $payload = $this->buildPayload($topic, $recordType, $confidentiality, $packets->all());
        $versions = $packets->mapWithKeys(function (array $packet) {
            $record = SecretariatRecord::query()->find((int)$packet['record_id']);
            return [(int)$packet['record_id'] => (int)($record?->current_version_id ?? 0)];
        })->all();

        Cache::put($key, [
            'actor_id'=>(int)$actor->id,
            'office_id'=>(int)$office->id,
            'evidence_versions'=>$versions,
            'payload'=>$payload,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($payload, $packets->all())
            . "\n\nاین فقط پیش‌نمایش مبتنی بر evidence رسمیِ مجاز است و هنوز هیچ رکوردی ایجاد نشده. برای ذخیره فقط به‌صورت Draft، «ذخیره پیش‌نویس مستند» بفرستید.",
            'awaiting_confirmation',
            ['evidence'=>$packets->all(), 'draft'=>$payload]
        );
    }

    /** @param array<string,mixed> $pending */
    private function saveConfirmed(User $actor, SecretariatOffice $office, array $pending): array
    {
        if ((int)($pending['actor_id'] ?? 0) !== (int)$actor->id
            || (int)($pending['office_id'] ?? 0) !== (int)$office->id
            || !is_array($pending['payload'] ?? null)
            || !is_array($pending['evidence_versions'] ?? null)) {
            return $this->response('درخواست ذخیره معتبر نیست؛ دوباره پیش‌نمایش بگیرید.', 'blocked');
        }

        foreach ((array)$pending['evidence_versions'] as $recordId=>$versionId) {
            $evidence = SecretariatRecord::query()->find((int)$recordId);
            if (!$evidence
                || (int)$evidence->office_id !== (int)$office->id
                || trim((string)$evidence->registry_number) === ''
                || (int)$evidence->current_version_id !== (int)$versionId
                || !Gate::forUser($actor)->allows('view', $evidence)) {
                return $this->response('یکی از شواهد از زمان پیش‌نمایش تغییر کرده یا دیگر مجاز نیست؛ هیچ Draftی ایجاد نشد.', 'stale_preview');
            }
        }

        $payload = (array)$pending['payload'];
        $probe = new SecretariatRecord([
            'office_id'=>$office->id,
            'record_type'=>$payload['record_type'] ?? 'official_report',
            'direction'=>'none','status'=>'draft',
            'confidentiality'=>$payload['confidentiality'] ?? $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);
        if (!Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('مجوز ایجاد Draft دیگر معتبر نیست؛ هیچ سندی ایجاد نشد.', 'blocked');
        }

        $record = $this->records->createDraft($office, $actor, $payload);
        if (in_array($record->confidentiality, ['restricted','confidential'], true)) {
            $this->acl->grant($record, 'user', $actor->id, $actor);
        }

        return $this->response(
            "Draft مستند با موفقیت ایجاد شد (Draft #{$record->id}). این سند هنوز submit، تأیید یا ثبت رسمی نشده است.",
            'draft_saved',
            ['record_id'=>(int)$record->id,'record_status'=>(string)$record->status]
        );
    }

    /** @param array<int,array<string,mixed>> $packets */
    private function buildPayload(string $topic, string $recordType, string $confidentiality, array $packets): array
    {
        $lines = [
            'پیش‌نویس مستند بر پایه سوابق رسمی مجاز',
            'موضوع: '.$topic,
            '',
            'شواهد مبنا:',
        ];
        $evidenceMeta = [];
        foreach ($packets as $i=>$packet) {
            $number = $packet['registry_number'] ?? ('Record #'.($packet['record_id'] ?? '?'));
            $title = trim((string)($packet['title'] ?? ''));
            $excerpt = trim((string)($packet['excerpt'] ?? ''));
            $lines[] = ($i+1).'. '.$number.' — '.$title;
            if ($excerpt !== '') {
                $lines[] = '   '.mb_substr(preg_replace('/\s+/u',' ',$excerpt) ?? $excerpt, 0, 850);
            }
            $evidenceMeta[] = [
                'record_id'=>(int)($packet['record_id'] ?? 0),
                'registry_number'=>$packet['registry_number'] ?? null,
            ];
        }
        $lines[] = '';
        $lines[] = 'جمع‌بندی و نتیجه‌گیری نهایی عمداً به مسئول انسانی واگذار شده است. نجم هدا در این Draft فقط شواهد رسمیِ مجاز را گردآوری و ساختاربندی کرده است.';

        return [
            'record_type'=>$recordType,
            'direction'=>'none',
            'title'=>mb_substr('پیش‌نویس مستند: '.$topic, 0, 500),
            'subject'=>mb_substr($topic, 0, 1000),
            'summary'=>'گردآوری ساختاریافته سوابق رسمی مجاز برای بررسی انسانی',
            'body'=>implode("\n", $lines),
            'confidentiality'=>$confidentiality,
            'source_type'=>'manual',
            'metadata'=>[
                'prepared_by'=>'najm_hoda_s7',
                'grounding_evidence'=>$evidenceMeta,
                'grounding_mode'=>'deterministic_authorized_registry',
            ],
        ];
    }

    /** @param array<string,mixed> $payload @param array<int,array<string,mixed>> $packets */
    private function preview(array $payload, array $packets): string
    {
        return implode("\n", [
            'پیش‌نمایش Draft مستند:',
            'نوع: '.(string)$payload['record_type'],
            'عنوان: '.(string)$payload['title'],
            'محرمانگی: '.(string)$payload['confidentiality'],
            'تعداد شواهد رسمی مجاز: '.count($packets),
            '',
            mb_substr((string)$payload['body'], 0, 6000),
        ]);
    }

    private function looksLikeRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        foreach (['پیش‌نویس مستند از شواهد','پیش نویس مستند از شواهد','draft from evidence','evidence grounded draft'] as $needle) {
            if (mb_stripos($plain, $needle) !== false) return true;
        }
        return false;
    }

    private function extractTopic(string $message): string
    {
        if (preg_match('/(?:موضوع|محور|topic)\s*[:：]\s*([^|؛;]+)/iu', $message, $match)) {
            return trim((string)$match[1]);
        }
        return '';
    }

    private function extractToken(string $message, string $label): string
    {
        if (preg_match('/'.preg_quote($label,'/').'\s*[:：]\s*([A-Za-z_]+)/iu', $message, $match)) {
            return trim((string)$match[1]);
        }
        return '';
    }

    private function isSaveConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ذخیره پیش‌نویس مستند','ذخیره پیش نویس مستند','save evidence draft'], true);
    }
    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو','انصراف','cancel'], true);
    }
    private function pendingKey(int $actorId, ?int $conversationId, int $officeId): string
    {
        return 'najm_hoda:secretariat_evidence_draft:'.$actorId.':'.($conversationId?:0).':'.$officeId;
    }
    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra=[]): array
    {
        return array_merge(['success'=>true,'message'=>$message,'agent'=>'secretariat_evidence_draft','agent_name'=>'نجم‌هدا','agent_icon'=>'✦','suggestions'=>[],'grounded'=>true,'status'=>$status], $extra);
    }
}
