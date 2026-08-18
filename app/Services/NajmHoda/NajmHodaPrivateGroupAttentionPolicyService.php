<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NajmHodaPrivateGroupAttentionPolicyService
{
    public function intercept(User $user, array $ctx, string $message, ?int $conversationId = null): ?array
    {
        if (($ctx['page_kind'] ?? null) !== 'group_chat') return null;
        $groupId = (int) ($ctx['resource_id'] ?? data_get($ctx, 'resource.id', 0));
        $group = $groupId > 0 ? Group::find($groupId) : null;
        if (! $group) return null;
        $key = "najm_hoda:attention_policy:{$groupId}:{$user->id}:" . ($conversationId ?: 0);
        $pending = Cache::get($key);
        if (is_array($pending) && $this->isCancel($message)) { Cache::forget($key); return $this->reply('تغییر تنظیمات پیگیری لغو شد و چیزی تغییر نکرد.', 'cancelled'); }
        if (is_array($pending) && $this->isConfirm($message)) {
            if (! $this->leader($groupId, $user->id)) { Cache::forget($key); return $this->reply('مجوز تغییر تنظیمات پیگیری این گروه را ندارید.', 'blocked'); }
            Cache::forget($key); $setting = $this->setting($groupId); $setting->fill((array) ($pending['changes'] ?? []))->save();
            return $this->reply("تنظیمات پیگیری نجم هدا برای «{$group->name}» بروزرسانی شد.\n\n" . $this->status($setting), 'executed');
        }
        if (! $this->looksRelevant($message)) return null;
        if (! $this->leader($groupId, $user->id)) return $this->reply('تنظیمات پیگیری فعال فقط برای مدیران و بازرسان فعال گروه قابل تغییر است.', 'blocked');
        $setting = $this->setting($groupId); $changes = $this->changes($message);
        if ($changes === []) return $this->reply("تنظیمات فعلی پیگیری نجم هدا برای «{$group->name}»:\n\n" . $this->status($setting), 'policy_status');
        Cache::put($key, ['changes' => $changes], now()->addMinutes(20));
        $lines = ["تغییر پیشنهادی تنظیمات پیگیری نجم هدا برای «{$group->name}»:", '']; foreach ($changes as $field => $value) $lines[] = '• ' . $this->label($field, $value);
        $lines[] = ''; $lines[] = 'تا قبل از تأیید چیزی تغییر نمی‌کند. برای اعمال «تأیید» و برای انصراف «لغو» بفرستید.';
        return $this->reply(implode("\n", $lines), 'awaiting_confirmation');
    }

    protected function setting(int $groupId): NajmHodaGroupAttentionSetting
    {
        return NajmHodaGroupAttentionSetting::firstOrCreate(['group_id' => $groupId], ['enabled'=>false,'due_soon_hours'=>48,'suppress_minutes'=>720,'digest_mode'=>'daily','timezone'=>(string)config('app.timezone','UTC'),'preferred_time'=>'08:00']);
    }

    protected function changes(string $message): array
    {
        $m = $this->norm($message); $c = [];
        if ($this->any($m, ['پیگیری فعال را روشن','پیگیری نجم هدا را روشن','هشدارها را روشن','هشدارهای نجم هدا را روشن'])) $c['enabled'] = true;
        if ($this->any($m, ['پیگیری فعال را خاموش','پیگیری نجم هدا را خاموش','هشدارها را خاموش','هشدارهای نجم هدا را خاموش'])) $c['enabled'] = false;
        if ($this->any($m, ['هشدارها را فوری','هشدارهای نجم هدا را فوری','هشدار فوری','اعلان فوری','فوری خبر بده'])) { $c['enabled'] = true; $c['digest_mode'] = 'immediate'; }
        if ($this->any($m, ['خلاصه روزانه','گزارش روزانه','هشدار روزانه'])) { $c['enabled'] = true; $c['digest_mode'] = 'daily'; }
        if ($this->any($m, ['فقط ثبت کن','اعلان نده','هشدار نده','بدون اعلان'])) { $c['enabled'] = true; $c['digest_mode'] = 'off'; }
        if (preg_match('/(?:ساعت|راس|رأس)\s*(\d{1,2})(?:[:٫\.]([0-5]?\d))?/u', $m, $x)) $c['preferred_time'] = sprintf('%02d:%02d', min(23,(int)$x[1]), isset($x[2]) ? min(59,(int)$x[2]) : 0);
        if (preg_match('/(?:موعد نزدیک|نزدیک موعد)[^\d]{0,20}(\d{1,3})\s*ساعت/u', $m, $x)) $c['due_soon_hours'] = min(720,max(1,(int)$x[1]));
        if (preg_match('/(?:تکرار هشدار|فاصله هشدار)[^\d]{0,20}(\d{1,3})\s*ساعت/u', $m, $x)) $c['suppress_minutes'] = min(10080,max(60,(int)$x[1]*60));
        return $c;
    }

    protected function looksRelevant(string $message): bool { return $this->any($this->norm($message), ['تنظیمات پیگیری','پیگیری فعال','هشدار نجم هدا','هشدارهای نجم هدا','خلاصه روزانه','گزارش روزانه','هشدار فوری','اعلان فوری','موعد نزدیک','نزدیک موعد','تکرار هشدار','فاصله هشدار']); }
    protected function status(NajmHodaGroupAttentionSetting $s): string { $mode = match((string)$s->digest_mode){'immediate'=>'فوری','daily'=>'خلاصه روزانه','off'=>'فقط ثبت داخلی؛ بدون اعلان',default=>(string)$s->digest_mode}; return '• پیگیری فعال: '.($s->enabled?'روشن':'خاموش')."\n• حالت اعلان: {$mode}\n• ساعت خلاصه: {$s->preferred_time}\n• نزدیک موعد: {$s->due_soon_hours} ساعت\n• فاصله تکرار: {$s->suppress_minutes} دقیقه"; }
    protected function label(string $f,mixed $v): string { return match($f){'enabled'=>'پیگیری فعال → '.($v?'روشن':'خاموش'),'digest_mode'=>'حالت اعلان → '.match($v){'immediate'=>'فوری','daily'=>'خلاصه روزانه','off'=>'فقط ثبت داخلی؛ بدون اعلان',default=>(string)$v},'preferred_time'=>'ساعت خلاصه → '.$v,'due_soon_hours'=>'بازه نزدیک موعد → '.$v.' ساعت','suppress_minutes'=>'فاصله تکرار → '.$v.' دقیقه',default=>$f.' → '.(string)$v}; }
    protected function leader(int $groupId,int $userId): bool { $role=GroupUser::where('group_id',$groupId)->where('user_id',$userId)->where('status',1)->value('role'); return in_array((int)$role,[2,3],true); }
    protected function norm(string $v): string { $v=mb_strtolower(trim(strip_tags($v))); $v=str_replace(['ي','ك','ۀ'],['ی','ک','ه'],$v); $v=strtr($v,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']); return preg_replace('/\s+/u',' ',$v) ?: $v; }
    protected function any(string $h,array $needles): bool { foreach($needles as $n) if(mb_stripos($h,$n)!==false) return true; return false; }
    protected function isConfirm(string $m): bool { return in_array($this->norm($m),['تایید','تأیید','تایید کن','تأیید کن','confirm','yes'],true); }
    protected function isCancel(string $m): bool { return in_array($this->norm($m),['لغو','لغو کن','cancel','نه','خیر'],true); }
    protected function reply(string $message,string $status): array { return ['success'=>true,'message'=>$message,'agent'=>'runtime','agent_name'=>'نجم‌هدا','agent_icon'=>'⚙️','suggestions'=>[],'private_group_attention_policy'=>true,'status'=>$status]; }
}
