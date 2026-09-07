<?php

namespace App\Services\NajmHoda\Context;

use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRelation;
use App\Modules\Secretariat\Services\SecretariatGovernanceIntegrationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * S7 evidence-grounded execution-report preparation.
 *
 * The assistant does not invent execution facts. Preview is built only from a
 * completed Action Item and the formal Registry chain already enforced in S3:
 * Action Item -> approved/registered Meeting Minute <- decision_of <- formal
 * Governance Resolution. Human confirmation delegates the mutation to the S3
 * adapter, which revalidates the whole chain before creating/reusing a Draft.
 */
class NajmHodaSecretariatExecutionReportAssistant
{
    public function __construct(private readonly SecretariatGovernanceIntegrationService $governance)
    {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        $officeId = $this->officeId($pageContext);
        if ($officeId <= 0 || ! str_starts_with((string) ($pageContext['page_kind'] ?? ''), 'secretariat_')) {
            return null;
        }

        $office = SecretariatOffice::query()->find($officeId);
        if (! $office) {
            return null;
        }

        $pendingKey = $this->pendingKey($actor->id, $conversationId, $office->id);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->response('پیشنهاد گزارش اجرا لغو شد و هیچ رکوردی ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isSaveConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->saveConfirmed($actor, $office, $pending);
        }

        if (! $this->looksLikeRequest($message)) {
            return null;
        }

        [$actionId, $resolutionRecordId] = $this->parseIds($message);
        if ($actionId <= 0 || $resolutionRecordId <= 0) {
            return $this->response(
                "برای گزارش اجرای مبتنی بر شواهد، شناسه اقدام و شناسه رکورد رسمی مصوبه را مشخص کنید.\n"
                . "مثال: «گزارش اجرای مصوبه آماده کن | اقدام: 12 | رکورد مصوبه: 34»",
                'needs_input'
            );
        }

        $evidence = $this->resolveEvidence($actor, $office, $actionId, $resolutionRecordId);
        if ($evidence === null) {
            return $this->response(
                'زنجیره شواهد معتبر و مجاز برای این اقدام و مصوبه پیدا نشد؛ هیچ گزارشی پیشنهاد نشد.',
                'blocked'
            );
        }

        Cache::put($pendingKey, [
            'actor_id' => (int) $actor->id,
            'office_id' => (int) $office->id,
            'action_id' => $actionId,
            'resolution_record_id' => $resolutionRecordId,
            'action_updated_at' => $evidence['action_updated_at'],
            'resolution_version_id' => $evidence['resolution_version_id'],
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($evidence)
            . "\n\nاین متن فقط از شواهد ثبت‌شده ساخته شده و هنوز هیچ Draftی ایجاد نشده است. "
            . "برای ایجاد Draft گزارش، «ذخیره گزارش اجرا» بفرستید. برای انصراف «لغو» بفرستید.",
            'awaiting_confirmation',
            ['evidence' => $evidence]
        );
    }

    /** @param array<string,mixed> $pending */
    private function saveConfirmed(User $actor, SecretariatOffice $office, array $pending): array
    {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id
            || (int) ($pending['office_id'] ?? 0) !== (int) $office->id) {
            return $this->response('درخواست ذخیره گزارش معتبر نیست؛ لطفاً دوباره از مرحله پیش‌نمایش شروع کنید.', 'blocked');
        }

        $action = NajmHodaGroupActionItem::query()->find((int) ($pending['action_id'] ?? 0));
        $resolutionRecord = SecretariatRecord::query()->find((int) ($pending['resolution_record_id'] ?? 0));
        if (! $action || ! $resolutionRecord) {
            return $this->response('شواهد گزارش دیگر در دسترس نیست؛ هیچ Draftی ایجاد نشد.', 'blocked');
        }

        // Stale-preview protection: execution evidence may have changed after
        // preview. Confirmation never trusts the cached snapshot as authority.
        if ((string) optional($action->updated_at)->toJSON() !== (string) ($pending['action_updated_at'] ?? '')
            || (int) ($resolutionRecord->current_version_id ?? 0) !== (int) ($pending['resolution_version_id'] ?? 0)) {
            return $this->response('شواهد از زمان پیش‌نمایش تغییر کرده است؛ لطفاً گزارش را دوباره آماده کنید.', 'stale_preview');
        }

        if ($this->resolveEvidence($actor, $office, (int) $action->id, (int) $resolutionRecord->id) === null) {
            return $this->response('مجوز یا زنجیره شواهد دیگر معتبر نیست؛ هیچ Draftی ایجاد نشد.', 'blocked');
        }

        // Canonical S3 mutation boundary rechecks status, provenance, official
        // minute/resolution registration and decision_of linkage transactionally.
        $record = $this->governance->proposeCompletedActionExecutionReport($action, $resolutionRecord, $actor);

        return $this->response(
            "Draft گزارش اجرای مصوبه ایجاد شد (Draft #{$record->id}). این گزارش هنوز ثبت رسمی، تأیید یا منتشر نشده است.",
            'draft_saved',
            [
                'record_id' => (int) $record->id,
                'record_status' => (string) $record->status,
                'record_type' => (string) $record->record_type,
                'action_id' => (int) $action->id,
                'resolution_record_id' => (int) $resolutionRecord->id,
            ]
        );
    }

    /** @return array<string,mixed>|null */
    private function resolveEvidence(User $actor, SecretariatOffice $office, int $actionId, int $resolutionRecordId): ?array
    {
        $action = NajmHodaGroupActionItem::query()->find($actionId);
        $resolutionRecord = SecretariatRecord::query()->with(['currentVersion'])->find($resolutionRecordId);
        if (! $action || ! $resolutionRecord || $action->status !== 'done') {
            return null;
        }

        if ($office->scope_type !== 'group' || (int) $office->scope_id !== (int) $action->group_id) {
            return null;
        }

        if ((int) $resolutionRecord->office_id !== (int) $office->id
            || $resolutionRecord->record_type !== 'resolution'
            || $resolutionRecord->source_type !== 'governance_resolution'
            || $resolutionRecord->registry_number === null
            || ! in_array($resolutionRecord->status, ['registered', 'active', 'closed', 'archived'], true)) {
            return null;
        }

        if (! Gate::forUser($actor)->allows('view', $resolutionRecord)) {
            return null;
        }

        $probe = new SecretariatRecord([
            'office_id' => $office->id,
            'record_type' => 'execution_record',
            'direction' => 'internal',
            'status' => 'draft',
            'confidentiality' => $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return null;
        }

        $meta = is_array($action->meta) ? $action->meta : [];
        $minuteId = (int) ($meta['meeting_minute_id'] ?? 0);
        if ($minuteId <= 0) {
            return null;
        }

        $minuteRecord = SecretariatRecord::query()
            ->where('office_id', $office->id)
            ->where('record_type', 'meeting_minute')
            ->where('source_type', 'meeting_minute')
            ->where('source_id', $minuteId)
            ->whereNotNull('registry_number')
            ->whereIn('status', ['registered', 'active', 'closed', 'archived'])
            ->first();
        if (! $minuteRecord || ! Gate::forUser($actor)->allows('view', $minuteRecord)) {
            return null;
        }

        if (! SecretariatRelation::query()
            ->where('source_record_id', $resolutionRecord->id)
            ->where('target_record_id', $minuteRecord->id)
            ->where('relation_type', 'decision_of')
            ->exists()) {
            return null;
        }

        $sourceResolution = Resolution::query()->find($resolutionRecord->source_id);
        if (! $sourceResolution || (int) $sourceResolution->group_id !== (int) $action->group_id) {
            return null;
        }

        return [
            'action_id' => (int) $action->id,
            'action_title' => (string) $action->title,
            'action_details' => (string) ($action->details ?? ''),
            'action_status' => (string) $action->status,
            'action_updated_at' => optional($action->updated_at)->toJSON(),
            'meeting_minute_record_id' => (int) $minuteRecord->id,
            'meeting_minute_registry_number' => (string) $minuteRecord->registry_number,
            'resolution_record_id' => (int) $resolutionRecord->id,
            'resolution_registry_number' => (string) $resolutionRecord->registry_number,
            'resolution_version_id' => (int) ($resolutionRecord->current_version_id ?? 0),
        ];
    }

    /** @param array<string,mixed> $evidence */
    private function preview(array $evidence): string
    {
        return implode("\n", [
            'پیش‌نمایش گزارش اجرای مبتنی بر شواهد:',
            'اقدام: ' . $evidence['action_title'],
            'وضعیت اقدام: ' . $evidence['action_status'],
            'جزئیات ثبت‌شده اقدام: ' . mb_substr((string) $evidence['action_details'], 0, 1800),
            'صورت‌جلسه رسمی: ' . $evidence['meeting_minute_registry_number'],
            'مصوبه رسمی: ' . $evidence['resolution_registry_number'],
        ]);
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

    private function looksLikeRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        return mb_stripos($plain, 'گزارش') !== false
            && mb_stripos($plain, 'اجرا') !== false
            && (mb_stripos($plain, 'مصوبه') !== false || mb_stripos($plain, 'resolution') !== false)
            && (mb_stripos($plain, 'آماده') !== false || mb_stripos($plain, 'بساز') !== false || mb_stripos($plain, 'تهیه') !== false);
    }

    /** @return array{0:int,1:int} */
    private function parseIds(string $message): array
    {
        $actionId = 0;
        $resolutionId = 0;
        if (preg_match('/(?:اقدام|action)\s*[:：]\s*(\d+)/iu', $message, $match)) {
            $actionId = (int) $match[1];
        }
        if (preg_match('/(?:رکورد\s*مصوبه|resolution\s*record)\s*[:：]\s*(\d+)/iu', $message, $match)) {
            $resolutionId = (int) $match[1];
        }
        return [$actionId, $resolutionId];
    }

    private function isSaveConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ذخیره گزارش اجرا', 'گزارش اجرا را ذخیره کن', 'save execution report'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $officeId): string
    {
        return 'najm_hoda:secretariat_execution_report:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $officeId;
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_execution_report',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
