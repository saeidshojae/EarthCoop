<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderEmailDraft;
use Illuminate\Support\Facades\Mail;

class FounderEmailDecisionService
{
    public function __construct(protected FounderActionRequestService $requests, protected FounderApprovalVerifierService $approvals) {}

    public function requestSend(FounderEmailDraft $draft, int $actorId): array
    {
        if ($draft->status !== 'draft') return ['success'=>false,'status'=>'invalid_state'];
        return $this->requests->prepare('email','send_email',[
            'entity_type'=>'founder_email_draft','entity_id'=>$draft->id,'requested_by'=>$actorId,
            'reason_code'=>$draft->reason_code ?: substr(hash('sha256','email|'.$draft->id),0,20),
        ]);
    }

    public function decideAndExecute(string $requestId, string $decision, int $actorId, ?string $reason = null): array
    {
        $approval = $this->approvals->decide($requestId, $decision, $actorId, $reason);
        if (! ($approval['success'] ?? false) || $decision !== 'approve') return $approval;

        $draftId = (int) data_get($approval,'request.context.entity_id',0);
        $draft = FounderEmailDraft::query()->whereKey($draftId)->where('status','draft')->first();
        if (! $draft) return ['success'=>false,'status'=>'not_found'];

        foreach ((array) $draft->recipients as $recipient) {
            Mail::html($draft->body, function ($message) use ($recipient, $draft) {
                $message->to($recipient)->subject($draft->subject);
            });
        }
        $draft->forceFill(['status'=>'sent','approved_by'=>$actorId,'sent_at'=>now()])->save();
        return ['success'=>true,'status'=>'executed','draft_id'=>$draft->id];
    }
}
