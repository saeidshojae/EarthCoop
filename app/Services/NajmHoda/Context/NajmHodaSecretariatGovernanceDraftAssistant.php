<?php

namespace App\Services\NajmHoda\Context;

use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatGovernanceIntegrationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Guided S7 wrapper around the already-validated S3 Governance adapters.
 * Source-domain IDs are hints only; confirmation always re-resolves authority.
 */
class NajmHodaSecretariatGovernanceDraftAssistant
{
    public function __construct(private readonly SecretariatGovernanceIntegrationService $integration) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        $officeId = $this->officeId($pageContext);
        if ($officeId <= 0 || ! str_starts_with((string) ($pageContext['page_kind'] ?? ''), 'secretariat_')) return null;

        $office = SecretariatOffice::query()->find($officeId);
        if (! $office || $office->scope_type !== 'group' || $office->scope_id === null) return null;

        $minuteKey = $this->pendingKey('minute', $actor->id, $conversationId, $office->id);
        $resolutionKey = $this->pendingKey('resolution', $actor->id, $conversationId, $office->id);

        if ($this->isCancellation($message)) {
            $hadPending = Cache::has($minuteKey) || Cache::has($resolutionKey);
            Cache::forget($minuteKey); Cache::forget($resolutionKey);
            return $hadPending ? $this->response('پیشنهاد ثبت دبیرخانه‌ای لغو شد و هیچ Draftی ایجاد نشد.', 'cancelled') : null;
        }
        if ($this->isMinuteConfirmation($message) && is_array($pending = Cache::get($minuteKey))) {
            Cache::forget($minuteKey); return $this->saveMinute($actor, $office, $pending);
        }
        if ($this->isResolutionConfirmation($message) && is_array($pending = Cache::get($resolutionKey))) {
            Cache::forget($resolutionKey); return $this->saveResolution($actor, $office, $pending);
        }

        // Resolution must win before Minute because a valid resolution request may
        // also contain "رکورد صورتجلسه" as its optional decision_of relation.
        if ($this->looksLikeResolutionRequest($message)) {
            $resolutionId = $this->extractId($message, ['مصوبه', 'resolution']);
            $minuteRecordId = $this->extractId($message, ['رکورد صورتجلسه', 'minute record']);
            if ($resolutionId <= 0) return $this->response('شناسه Governance Resolution را مشخص کنید؛ مثال: «مصوبه رسمی آماده کن | مصوبه: 20».', 'needs_input');
            return $this->previewResolution($actor, $office, $resolutionId, $minuteRecordId, $resolutionKey);
        }
        if ($this->looksLikeMinuteRequest($message)) {
            $minuteId = $this->extractId($message, ['صورتجلسه', 'minute']);
            if ($minuteId <= 0) return $this->response('شناسه صورتجلسه تأییدشده را مشخص کنید؛ مثال: «صورتجلسه رسمی آماده کن | صورتجلسه: 12».', 'needs_input');
            return $this->previewMinute($actor, $office, $minuteId, $minuteKey);
        }
        return null;
    }

    private function previewMinute(User $actor, SecretariatOffice $office, int $minuteId, string $key): array
    {
        $minute = NajmHodaGroupMeetingMinute::query()->with('session')->find($minuteId);
        if (! $minute || (int) $minute->group_id !== (int) $office->scope_id || $minute->status !== 'approved' || $minute->approved_by === null || $minute->approved_at === null || $minute->session === null)
            return $this->response('صورتجلسه تأییدشده و سازگار با این دفتر پیدا نشد.', 'blocked');
        if (! $this->canCreate($actor, $office, 'meeting_minute')) return $this->response('شما مجوز ایجاد Draft صورتجلسه در این دفتر را ندارید.', 'blocked');

        Cache::put($key, ['actor_id'=>(int)$actor->id,'office_id'=>(int)$office->id,'source_id'=>(int)$minute->id,'source_updated_at'=>optional($minute->updated_at)->toJSON()], now()->addMinutes(15));
        return $this->response(implode("\n", [
            'پیش‌نمایش ثبت صورتجلسه در دبیرخانه:',
            'جلسه: '.(string)$minute->session->title,
            'خلاصه: '.mb_substr((string)($minute->summary ?? ''),0,1000),
            'متن مصوب: '.mb_substr((string)($minute->minutes ?? ''),0,1600),
            'زمان تأیید: '.(string)optional($minute->approved_at)->toIso8601String(), '',
            'این فقط snapshot پیشنهادی است. برای ساخت Draft دبیرخانه «ذخیره صورتجلسه» بفرستید.'
        ]), 'awaiting_confirmation', ['source_type'=>'meeting_minute','source_id'=>(int)$minute->id]);
    }

    private function previewResolution(User $actor, SecretariatOffice $office, int $resolutionId, int $minuteRecordId, string $key): array
    {
        $resolution = Resolution::query()->with('proposal')->find($resolutionId);
        if (! $resolution || (int)$resolution->group_id !== (int)$office->scope_id || $resolution->status !== 'adopted' || $resolution->adopted_at === null || $resolution->proposal === null)
            return $this->response('مصوبه adopted و سازگار با این دفتر پیدا نشد.', 'blocked');
        if (! $this->canCreate($actor, $office, 'resolution')) return $this->response('شما مجوز ایجاد Draft مصوبه در این دفتر را ندارید.', 'blocked');

        $minuteRecord = null;
        if ($minuteRecordId > 0) {
            $minuteRecord = SecretariatRecord::query()->with('office')->find($minuteRecordId);
            if (! $minuteRecord || (int)$minuteRecord->office_id !== (int)$office->id || $minuteRecord->record_type !== 'meeting_minute' || ! Gate::forUser($actor)->allows('view', $minuteRecord))
                return $this->response('رکورد صورتجلسه معتبر و مجاز برای relation پیدا نشد.', 'blocked');
        }
        Cache::put($key, ['actor_id'=>(int)$actor->id,'office_id'=>(int)$office->id,'source_id'=>(int)$resolution->id,'source_updated_at'=>optional($resolution->updated_at)->toJSON(),'minute_record_id'=>$minuteRecord?->id,'minute_record_version_id'=>$minuteRecord?->current_version_id], now()->addMinutes(15));
        $lines = ['پیش‌نمایش ثبت مصوبه در دبیرخانه:','عنوان: '.(string)$resolution->proposal->title,'خلاصه: '.mb_substr((string)($resolution->proposal->summary ?? ''),0,1000),'شرح مصوبه: '.mb_substr((string)($resolution->proposal->description ?? ''),0,1600),'زمان تصویب: '.(string)optional($resolution->adopted_at)->toIso8601String()];
        if ($minuteRecord) $lines[] = 'رابطه پیشنهادی decision_of با رکورد صورتجلسه #'.$minuteRecord->id;
        $lines[]=''; $lines[]='برای ساخت Draft دبیرخانه «ذخیره مصوبه» بفرستید.';
        return $this->response(implode("\n",$lines),'awaiting_confirmation',['source_type'=>'governance_resolution','source_id'=>(int)$resolution->id,'minute_record_id'=>$minuteRecord?->id]);
    }

    /** @param array<string,mixed> $pending */
    private function saveMinute(User $actor, SecretariatOffice $office, array $pending): array
    {
        if (! $this->validPending($actor,$office,$pending)) return $this->response('درخواست ذخیره معتبر نیست؛ دوباره پیش‌نمایش بگیرید.','blocked');
        $minute=NajmHodaGroupMeetingMinute::query()->find((int)$pending['source_id']);
        if (! $minute || (string)optional($minute->updated_at)->toJSON() !== (string)($pending['source_updated_at'] ?? '') || ! $this->canCreate($actor,$office,'meeting_minute'))
            return $this->response('صورتجلسه یا مجوز از زمان پیش‌نمایش تغییر کرده است؛ هیچ Draftی ایجاد نشد.','stale_preview');
        $record=$this->integration->proposeApprovedMeetingMinute($minute,$actor);
        return $this->response("Draft صورتجلسه دبیرخانه ایجاد شد (Draft #{$record->id}). ثبت رسمی هنوز انجام نشده است.",'draft_saved',['record_id'=>(int)$record->id,'record_status'=>(string)$record->status]);
    }

    /** @param array<string,mixed> $pending */
    private function saveResolution(User $actor, SecretariatOffice $office, array $pending): array
    {
        if (! $this->validPending($actor,$office,$pending)) return $this->response('درخواست ذخیره معتبر نیست؛ دوباره پیش‌نمایش بگیرید.','blocked');
        $resolution=Resolution::query()->find((int)$pending['source_id']);
        if (! $resolution || (string)optional($resolution->updated_at)->toJSON() !== (string)($pending['source_updated_at'] ?? '') || ! $this->canCreate($actor,$office,'resolution'))
            return $this->response('مصوبه یا مجوز از زمان پیش‌نمایش تغییر کرده است؛ هیچ Draftی ایجاد نشد.','stale_preview');
        $minuteRecord=null;
        if ((int)($pending['minute_record_id'] ?? 0)>0) {
            $minuteRecord=SecretariatRecord::query()->find((int)$pending['minute_record_id']);
            if (! $minuteRecord || (int)$minuteRecord->office_id !== (int)$office->id || $minuteRecord->record_type !== 'meeting_minute' || (int)$minuteRecord->current_version_id !== (int)($pending['minute_record_version_id'] ?? 0) || ! Gate::forUser($actor)->allows('view',$minuteRecord))
                return $this->response('رکورد صورتجلسه از زمان پیش‌نمایش تغییر کرده یا دیگر مجاز نیست.','stale_preview');
        }
        $record=$this->integration->proposeAdoptedResolution($resolution,$actor,$minuteRecord);
        return $this->response("Draft مصوبه دبیرخانه ایجاد شد (Draft #{$record->id}). ثبت رسمی هنوز انجام نشده است.",'draft_saved',['record_id'=>(int)$record->id,'record_status'=>(string)$record->status]);
    }

    private function canCreate(User $actor, SecretariatOffice $office, string $recordType): bool
    {
        $probe=new SecretariatRecord(['office_id'=>$office->id,'record_type'=>$recordType,'direction'=>'internal','status'=>'draft','confidentiality'=>$office->default_confidentiality]);
        $probe->setRelation('office',$office);
        return Gate::forUser($actor)->allows('create',$probe);
    }
    /** @param array<string,mixed> $pending */
    private function validPending(User $actor, SecretariatOffice $office, array $pending): bool
    { return (int)($pending['actor_id']??0)===(int)$actor->id && (int)($pending['office_id']??0)===(int)$office->id && (int)($pending['source_id']??0)>0; }
    /** @param array<string,mixed> $pageContext */
    private function officeId(array $pageContext): int
    {
        if ((string)($pageContext['resource_type']??'')==='secretariat_office') return (int)($pageContext['resource_id']??0);
        $resource=is_array($pageContext['resource']??null)?$pageContext['resource']:[];
        return (int)($resource['office_id']??0);
    }
    private function looksLikeMinuteRequest(string $message): bool
    {
        $plain=mb_strtolower($message);
        if (mb_stripos($plain,'مصوبه')!==false || mb_stripos($plain,'resolution')!==false) return false;
        return (mb_stripos($plain,'صورتجلسه')!==false || mb_stripos($plain,'minute')!==false)
            && (mb_stripos($plain,'آماده')!==false || mb_stripos($plain,'ثبت')!==false || mb_stripos($plain,'پیش‌نویس')!==false);
    }
    private function looksLikeResolutionRequest(string $message): bool
    {
        $plain=mb_strtolower($message);
        return (mb_stripos($plain,'مصوبه')!==false || mb_stripos($plain,'resolution')!==false)
            && mb_stripos($plain,'گزارش اجرا')===false
            && (mb_stripos($plain,'آماده')!==false || mb_stripos($plain,'ثبت')!==false || mb_stripos($plain,'پیش‌نویس')!==false);
    }
    /** @param array<int,string> $labels */
    private function extractId(string $message,array $labels): int
    { foreach($labels as $label) if(preg_match('/'.preg_quote($label,'/').'\s*[:：]\s*(\d+)/iu',$message,$match)) return (int)$match[1]; return 0; }
    private function isMinuteConfirmation(string $message): bool
    { return in_array(trim(mb_strtolower($message)),['ذخیره صورتجلسه','صورتجلسه را ذخیره کن','save minute'],true); }
    private function isResolutionConfirmation(string $message): bool
    { return in_array(trim(mb_strtolower($message)),['ذخیره مصوبه','مصوبه را ذخیره کن','save resolution'],true); }
    private function isCancellation(string $message): bool
    { return in_array(trim(mb_strtolower($message)),['لغو','انصراف','cancel'],true); }
    private function pendingKey(string $type,int $actorId,?int $conversationId,int $officeId): string
    { return 'najm_hoda:secretariat_governance:'.$type.':'.$actorId.':'.($conversationId?:0).':'.$officeId; }
    /** @param array<string,mixed> $extra */
    private function response(string $message,string $status,array $extra=[]): array
    { return array_merge(['success'=>true,'message'=>$message,'agent'=>'secretariat_governance_draft','agent_name'=>'نجم‌هدا','agent_icon'=>'✦','suggestions'=>[],'grounded'=>true,'status'=>$status],$extra); }
}
