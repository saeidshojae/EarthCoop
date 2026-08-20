<?php

namespace App\Services\Invitation;

use App\Mail\InvitationMail;
use App\Mail\InvitationRejectedMail;
use App\Models\Invitation;
use App\Models\InvitationCode;
use App\Models\InvitationCodeLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class InvitationManagementService
{
    public function __construct(protected InvitationSystemIssuerResolver $systemIssuer) {}

    public function recommend(Invitation $invitation): array
    {
        if ((int)$invitation->status !== 0) {
            return ['success'=>true,'status'=>'already_reviewed','recommendation'=>'none','invitation_id'=>$invitation->id];
        }

        $email=trim((string)$invitation->email);
        $validEmail=filter_var($email,FILTER_VALIDATE_EMAIL)!==false;
        $duplicatePending=Invitation::query()->whereKeyNot($invitation->id)->where('status',0)->where('email',$email)->exists();
        $recommendation=!$validEmail?'review_invalid_email':($duplicatePending?'review_duplicate_request':'eligible_for_issue');

        return [
            'success'=>true,'status'=>'completed','invitation_id'=>$invitation->id,'recommendation'=>$recommendation,
            'signals'=>['valid_email'=>$validEmail,'duplicate_pending'=>$duplicatePending],
        ];
    }

    public function issue(Invitation $invitation,int $actorId): array
    {
        if ((int)$invitation->status !== 0) return ['success'=>true,'status'=>'already_reviewed','invitation_id'=>$invitation->id];

        // Resolve before opening the transaction so an invalid deployment config
        // fails without creating a code or changing the invitation state.
        $systemIssuerId=$this->systemIssuer->id();

        $result=DB::transaction(function() use($invitation,$actorId,$systemIssuerId){
            $locked=Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if((int)$locked->status!==0)return ['invitation'=>$locked,'code'=>null,'already'=>true];
            $codeStr=$this->uniqueCode();
            $hours=(int)(Setting::query()->find(1)?->expire_invation_time ?? 72);
            $code=InvitationCode::query()->create(['code'=>$codeStr,'user_id'=>$systemIssuerId,'expire_at'=>now()->addHours(max(1,$hours))]);
            $locked->forceFill(['status'=>1,'reviewed_by'=>$actorId,'reviewed_at'=>now()])->save();
            $this->log($code->id,'issue',$actorId,['invitation_id'=>$locked->id,'email'=>$locked->email,'issuer_user_id'=>$systemIssuerId]);
            return ['invitation'=>$locked,'code'=>$code,'already'=>false];
        });

        if($result['already'])return ['success'=>true,'status'=>'already_reviewed','invitation_id'=>$invitation->id];
        $mailSent=true; $mailError=null;
        try{Mail::to($result['invitation']->email)->send(new InvitationMail($result['code']->code,$result['code']->expire_at));}
        catch(\Throwable $e){$mailSent=false;$mailError=mb_substr($e->getMessage(),0,500);Log::error('Invitation email failed',['invitation_id'=>$invitation->id,'exception_class'=>$e::class]);}
        return ['success'=>true,'status'=>'issued','invitation_id'=>$invitation->id,'invitation_code_id'=>$result['code']->id,'mail_sent'=>$mailSent,'mail_error'=>$mailError];
    }

    public function reject(Invitation $invitation,int $actorId,?string $note=null): array
    {
        $locked=DB::transaction(function() use($invitation,$actorId,$note){
            $locked=Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if((int)$locked->status===2)return $locked;
            if((int)$locked->status!==0)throw new RuntimeException('Only pending invitation requests can be rejected.');
            $locked->forceFill(['status'=>2,'admin_note'=>$note,'reviewed_by'=>$actorId,'reviewed_at'=>now()])->save();
            $this->log(null,'reject',$actorId,['invitation_id'=>$locked->id,'email'=>$locked->email,'note'=>$note]);
            return $locked;
        });
        $mailSent=true; $mailError=null;
        try{Mail::to($locked->email)->send(new InvitationRejectedMail($locked->admin_note));}
        catch(\Throwable $e){$mailSent=false;$mailError=mb_substr($e->getMessage(),0,500);Log::error('Invitation rejection email failed',['invitation_id'=>$invitation->id,'exception_class'=>$e::class]);}
        return ['success'=>true,'status'=>'rejected','invitation_id'=>$invitation->id,'mail_sent'=>$mailSent,'mail_error'=>$mailError];
    }

    protected function uniqueCode(): string
    {
        do{$code=strtoupper(substr(bin2hex(random_bytes(8)),0,6));}while(InvitationCode::query()->where('code',$code)->exists());
        return $code;
    }

    protected function log(?int $codeId,string $action,int $actorId,array $meta=[]): void
    {
        if(!class_exists(InvitationCodeLog::class))return;
        InvitationCodeLog::query()->create(['invitation_code_id'=>$codeId,'action'=>$action,'actor_id'=>$actorId,'meta'=>$meta]);
    }
}
