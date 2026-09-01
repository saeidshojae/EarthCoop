<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatDispatchService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/** Guided internal referral; preview-only until explicit human confirmation. */
class NajmHodaSecretariatReferralAssistant
{
    public function __construct(private readonly SecretariatDispatchService $dispatches) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if ((string) ($pageContext['resource_type'] ?? '') !== 'secretariat_record') return null;
        $recordId = (int) ($pageContext['resource_id'] ?? 0);
        if ($recordId <= 0) return null;

        $key = $this->pendingKey($actor->id, $conversationId, $recordId);
        if ($this->isCancellation($message) && Cache::has($key)) {
            Cache::forget($key);
            return $this->response('پیشنهاد ارجاع لغو شد و هیچ گردش جدیدی ایجاد نشد.', 'cancelled');
        }
        if ($this->isConfirmation($message) && is_array($pending = Cache::get($key))) {
            Cache::forget($key);
            return $this->confirm($actor, $recordId, $pending);
        }
        if (! $this->looksLikeReferral($message)) return null;

        $targetUserId = $this->extractId($message, ['کاربر', 'گیرنده', 'user']);
        if ($targetUserId <= 0) return $this->response('برای ارجاع داخلی، شناسه کاربر گیرنده را مشخص کنید؛ مثال: «ارجاع بده | کاربر: 42 | دستور: بررسی و اعلام نظر».', 'needs_input');

        $record = SecretariatRecord::query()->with('office')->find($recordId);
        if (! $this->isEligibleRecord($record) || ! Gate::forUser($actor)->allows('transition', $record))
            return $this->response('این سند برای ارجاع داخلی مجاز نیست یا شما اختیار گردش آن را ندارید.', 'blocked');
        if ($record->office?->scope_type !== 'group' || $record->office?->scope_id === null)
            return $this->response('در این مرحله Guided Referral فقط برای دفترهای گروهی فعال است.', 'blocked');

        $target = User::query()->find($targetUserId);
        if (! $target || ! $this->isGroupMember((int) $record->office->scope_id, $targetUserId))
            return $this->response('کاربر گیرنده عضو فعال گروه این دفتر نیست.', 'blocked');

        $instructions = $this->extractText($message, ['دستور', 'توضیح', 'instructions']);
        [$dueOk, $dueAt] = $this->parseOptionalTimestamp($this->extractText($message, ['موعد', 'deadline', 'due']));
        [$followOk, $followUpAt] = $this->parseOptionalTimestamp($this->extractText($message, ['پیگیری', 'follow up', 'follow_up']));
        if (! $dueOk || ! $followOk) {
            return $this->response('فرمت موعد یا زمان پیگیری معتبر نیست. نمونه: «موعد: 2026-08-25 17:00 | پیگیری: 2026-08-24 10:00».', 'needs_input');
        }
        $expectsResponse = $this->yes($this->extractText($message, ['پاسخ لازم', 'انتظار پاسخ', 'expects response']));

        $displayName = trim((string) ($target->first_name ?? '') . ' ' . (string) ($target->last_name ?? ''));
        if ($displayName === '') $displayName = (string) ($target->email ?? ('User #' . $target->id));

        Cache::put($key, [
            'actor_id' => (int) $actor->id,
            'record_id' => (int) $record->id,
            'record_version_id' => (int) ($record->current_version_id ?? 0),
            'registry_number' => (string) $record->registry_number,
            'target_user_id' => (int) $target->id,
            'instructions' => $instructions !== '' ? mb_substr($instructions, 0, 5000) : null,
            'expects_response' => $expectsResponse,
            'due_at' => $dueAt,
            'follow_up_at' => $followUpAt,
        ], now()->addMinutes(15));

        return $this->response(implode("\n", [
            'پیش‌نمایش ارجاع داخلی:',
            'سند رسمی: ' . $record->registry_number,
            'گیرنده: ' . $displayName . ' (#' . $target->id . ')',
            'دستور: ' . ($instructions !== '' ? mb_substr($instructions, 0, 1000) : '—'),
            'پاسخ مورد انتظار: ' . ($expectsResponse ? 'بله' : 'خیر'),
            'موعد: ' . ($dueAt ?? 'تعیین نشده'),
            'پیگیری: ' . ($followUpAt ?? 'تعیین نشده'),
            '',
            'هنوز هیچ گردش جدیدی ایجاد نشده است. برای ایجاد فقط یک Dispatch با وضعیت pending، «تأیید ارجاع» بفرستید.',
        ]), 'awaiting_confirmation', ['record_id'=>(int)$record->id,'target_user_id'=>(int)$target->id]);
    }

    /** @param array<string,mixed> $pending */
    private function confirm(User $actor, int $recordId, array $pending): array
    {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id || (int) ($pending['record_id'] ?? 0) !== $recordId)
            return $this->response('درخواست ارجاع معتبر نیست؛ دوباره پیش‌نمایش بگیرید.', 'blocked');

        $record = SecretariatRecord::query()->with('office')->find($recordId);
        if (! $this->isEligibleRecord($record)
            || (int) ($record->current_version_id ?? 0) !== (int) ($pending['record_version_id'] ?? -1)
            || (string) $record->registry_number !== (string) ($pending['registry_number'] ?? '')
            || ! Gate::forUser($actor)->allows('transition', $record))
            return $this->response('سند یا مجوز از زمان پیش‌نمایش تغییر کرده است؛ هیچ ارجاعی ایجاد نشد.', 'stale_preview');

        $targetUserId = (int) ($pending['target_user_id'] ?? 0);
        if ($record->office?->scope_type !== 'group' || $record->office?->scope_id === null
            || ! User::query()->whereKey($targetUserId)->exists()
            || ! $this->isGroupMember((int) $record->office->scope_id, $targetUserId))
            return $this->response('گیرنده دیگر عضو فعال این دفتر نیست؛ هیچ ارجاعی ایجاد نشد.', 'stale_preview');

        $dispatch = $this->dispatches->create($record, $actor, [
            'dispatch_type' => 'referral', 'channel' => 'internal', 'target_user_id' => $targetUserId,
            'instructions' => $pending['instructions'] ?? null,
            'expects_response' => (bool) ($pending['expects_response'] ?? false),
            'due_at' => $pending['due_at'] ?? null,
            'follow_up_at' => $pending['follow_up_at'] ?? null,
            'metadata' => ['prepared_by' => 'najm_hoda', 'guided_operation' => 'internal_referral'],
        ]);

        return $this->response(
            "ارجاع داخلی ثبت شد (Dispatch #{$dispatch->id}) و در وضعیت pending است. هنوز ارسال یا دریافت‌شده محسوب نمی‌شود.",
            'dispatch_pending',
            ['dispatch_id'=>(int)$dispatch->id,'dispatch_status'=>(string)$dispatch->status]
        );
    }

    private function isEligibleRecord(?SecretariatRecord $record): bool
    { return $record !== null && $record->registry_number !== null && in_array((string)$record->status, ['registered','active','closed'], true); }

    private function isGroupMember(int $groupId, int $userId): bool
    {
        return DB::table('group_user')->where('group_id',$groupId)->where('user_id',$userId)
            ->whereNull('deleted_at')->where('status',1)
            ->where(function($query){$query->whereNull('expired')->orWhere('expired',0)->orWhere('expired','>',now());})
            ->exists();
    }

    /** @return array{0:bool,1:?string} */
    private function parseOptionalTimestamp(string $value): array
    {
        if (trim($value) === '') return [true, null];
        try { return [true, CarbonImmutable::parse($value)->toIso8601String()]; }
        catch (Throwable) { return [false, null]; }
    }

    private function yes(string $value): bool
    { return in_array(trim(mb_strtolower($value)), ['بله','آری','yes','true','1'], true); }
    private function looksLikeReferral(string $message): bool
    { $p=mb_strtolower($message); return (mb_stripos($p,'ارجاع')!==false||mb_stripos($p,'referral')!==false)&&(mb_stripos($p,'بده')!==false||mb_stripos($p,'کن')!==false||mb_stripos($p,'آماده')!==false); }
    private function isConfirmation(string $message): bool
    { return in_array(trim(mb_strtolower($message)), ['تأیید ارجاع','تایید ارجاع','ارجاع را تأیید کن','confirm referral'], true); }
    private function isCancellation(string $message): bool
    { return in_array(trim(mb_strtolower($message)), ['لغو','انصراف','cancel'], true); }
    /** @param array<int,string> $labels */
    private function extractId(string $message,array $labels): int
    { foreach($labels as $label) if(preg_match('/'.preg_quote($label,'/').'\s*[:：]\s*(\d+)/iu',$message,$m)) return (int)$m[1]; return 0; }
    /** @param array<int,string> $labels */
    private function extractText(string $message,array $labels): string
    { foreach($labels as $label) if(preg_match('/(?:^|[|؛;])\s*'.preg_quote($label,'/').'\s*[:：]\s*(.*?)(?=\s*[|؛;]\s*[^|؛;]+\s*[:：]|$)/us',$message,$m)) return trim((string)$m[1]); return ''; }
    private function pendingKey(int $actorId,?int $conversationId,int $recordId): string
    { return 'najm_hoda:secretariat_referral:'.$actorId.':'.($conversationId?:0).':'.$recordId; }
    /** @param array<string,mixed> $extra */
    private function response(string $message,string $status,array $extra=[]): array
    { return array_merge(['success'=>true,'message'=>$message,'agent'=>'secretariat_referral','agent_name'=>'نجم‌هدا','agent_icon'=>'✦','suggestions'=>[],'grounded'=>true,'status'=>$status],$extra); }
}
