<?php

namespace App\Modules\Secretariat\Services;

use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Models\SecretariatFollowUpProposal;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class SecretariatFollowUpProposalService
{
    public function __construct(protected RuntimeEventBus $events) {}

    /** @return array<string,mixed> */
    public function prepare(SecretariatDispatch $dispatch, ?string $reasonCode = null): array
    {
        if (in_array((string)$dispatch->status,['completed','failed','cancelled'],true)) {
            return ['success'=>false,'status'=>'skipped','reason'=>'dispatch_terminal'];
        }

        $existing=SecretariatFollowUpProposal::query()->where('dispatch_id',$dispatch->id)->where('status','draft')->latest('id')->first();
        if ($existing) return $this->summary($existing,'existing');

        $dispatch->loadMissing('record:id,registry_number,status');
        $deadline=$dispatch->due_at ?? $dispatch->follow_up_at;
        $overdue=$deadline ? $deadline->isPast() : false;
        $urgency=$overdue?'high':'normal';

        $proposal='پیگیری مکاتبه دبیرخانه'
            . ($dispatch->record?->registry_number ? ' با شماره ثبت '.$dispatch->record->registry_number : '')
            . '. وضعیت فعلی: '.(string)$dispatch->status.'. کانال: '.(string)$dispatch->channel.'.';
        if ($dispatch->expects_response) $proposal.=' این ارجاع منتظر پاسخ است.';
        if ($deadline) $proposal.=' موعد پیگیری: '.$deadline->toIso8601String().($overdue?' (عقب‌افتاده).':'.');
        $proposal.=' این فقط پیشنهاد پیگیری است و هیچ ارسال یا تغییر وضعیت رسمی انجام نشده است.';

        $draft=SecretariatFollowUpProposal::query()->create([
            'dispatch_id'=>$dispatch->id,'status'=>'draft','urgency'=>$urgency,
            'proposal'=>$proposal,'reason_code'=>$reasonCode,
        ]);

        $this->events->emit('najm_hoda.input.secretariat.follow_up_proposal.created',[
            'dispatch_id'=>(int)$dispatch->id,'proposal_id'=>(int)$draft->id,'urgency'=>$urgency,
            'reason_code'=>$reasonCode,
        ]);
        return $this->summary($draft,'created');
    }

    /** @return array<string,mixed> */
    protected function summary(SecretariatFollowUpProposal $proposal,string $mode): array
    {
        return ['success'=>true,'status'=>'follow_up_ready','mode'=>$mode,'proposal_id'=>(int)$proposal->id,
            'dispatch_id'=>(int)$proposal->dispatch_id,'urgency'=>(string)$proposal->urgency];
    }
}
