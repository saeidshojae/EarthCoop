<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Models\FounderFinancialRiskFinding;
use App\Models\ModerationCaseSummary;
use App\Models\SupportReplyDraft;
use App\Modules\Secretariat\Models\SecretariatFollowUpProposal;
use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderAuthoritySnapshotService;
use App\Services\NajmHoda\FounderOps\FounderAutonomyBridgeService;
use App\Services\NajmHoda\FounderOps\FounderContentDecisionService;
use App\Services\NajmHoda\FounderOps\FounderEmailDecisionService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderModerationDecisionService;
use App\Services\NajmHoda\FounderOps\FounderOperationsSnapshotService;
use App\Services\NajmHoda\FounderOps\FounderReferenceApprovalCandidateService;
use App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
use Illuminate\Http\Request;

class FounderOperationsController extends Controller
{
    public function index(Request $request, FounderAttentionService $attention, FounderOperationsSnapshotService $snapshots, FounderApprovalInboxService $approvals, FounderReferenceApprovalCandidateService $referenceCandidates, FounderExecutiveConnectivityService $connectivity)
    {
        $hours=max(1,min((int)$request->integer('hours',24),168));
        return view('admin.najm-hoda.founder-ops.index',[
            'hours'=>$hours,'brief'=>$attention->brief($hours),'snapshot'=>$snapshots->snapshot($hours),
            'executiveConnectivity'=>$connectivity->report(),
            'approvalInbox'=>$approvals->snapshot(),'referenceCandidates'=>$referenceCandidates->candidates(10),
            'supportDrafts'=>SupportReplyDraft::query()->with(['ticket:id,tracking_code,subject,status,priority,category'])->where('status','draft')->latest('id')->limit(20)->get(),
            'moderationCases'=>ModerationCaseSummary::query()->where('status','draft')->latest('id')->limit(20)->get(),
            'secretariatFollowUps'=>SecretariatFollowUpProposal::query()->with(['dispatch.record:id,registry_number,status'])->where('status','draft')->latest('id')->limit(20)->get(),
            'emailDrafts'=>FounderEmailDraft::query()->where('status','draft')->latest('id')->limit(20)->get(),
            'contentDrafts'=>FounderContentDraft::query()->where('status','draft')->latest('id')->limit(20)->get(),
            'financialRiskFindings'=>FounderFinancialRiskFinding::query()->where('status','open')
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->latest('id')->limit(30)->get(),
        ]);
    }

    public function brief(Request $request, FounderAttentionService $service){return response()->json(['success'=>true,'data'=>$service->brief((int)$request->integer('hours',24))]);}
    public function snapshot(Request $request, FounderOperationsSnapshotService $service){return response()->json(['success'=>true,'data'=>$service->snapshot((int)$request->integer('hours',24))]);}
    public function connectivity(FounderExecutiveConnectivityService $service){return response()->json(['success'=>true,'data'=>$service->report()]);}
    public function autonomyPlan(Request $request, FounderAutonomyBridgeService $service){$hours=max(1,min((int)$request->integer('hours',24),168));$limit=max(1,min((int)$request->integer('limit',12),50));return response()->json(['success'=>true,'data'=>$service->plan($hours,$limit)]);}
    public function approvals(FounderApprovalInboxService $service){return response()->json(['success'=>true,'data'=>$service->snapshot()]);}
    public function authority(FounderAuthoritySnapshotService $summary, FounderActionAuthorityService $authority){return response()->json(['success'=>true,'data'=>['summary'=>$summary->snapshot(),'matrix'=>$authority->matrix()]]);}

    public function requestSupportDraftSend(Request $request, SupportReplyDraft $draft, FounderSupportDraftApprovalService $service){$result=$service->requestSend($draft,(int)$request->user()->id);return $this->approvalBack($result,'درخواست ارسال پاسخ در صف تأیید Founder قرار گرفت.');}
    public function decideSupportDraft(Request $request,string $requestId,FounderSupportDraftApprovalService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestReferenceApprove(Request $request,string $type,int $id,FounderReferenceApprovalDecisionService $service){try{$result=$service->requestApprove($type,$id,(int)$request->user()->id);}catch(\Throwable){return back()->with('error','مورد تأیید معتبر نیست.');}return $this->approvalBack($result,'درخواست تأیید در صف Founder قرار گرفت.');}
    public function decideReferenceApproval(Request $request,string $requestId,FounderReferenceApprovalDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestModerationResolve(Request $request,string $sourceType,int $sourceId,FounderModerationDecisionService $service){try{$result=$service->requestResolve($sourceType,$sourceId,(int)$request->user()->id);}catch(\Throwable){return back()->with('error','گزارش معتبر یا قابل بررسی نیست.');}return $this->approvalBack($result,'درخواست حل گزارش در صف تأیید Founder قرار گرفت.');}
    public function decideModerationResolve(Request $request,string $requestId,FounderModerationDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestEmailSend(Request $request,FounderEmailDraft $draft,FounderEmailDecisionService $service){return $this->approvalBack($service->requestSend($draft,(int)$request->user()->id),'درخواست ارسال ایمیل در صف تأیید Founder قرار گرفت.');}
    public function decideEmailSend(Request $request,string $requestId,FounderEmailDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestContentPublish(Request $request,FounderContentDraft $draft,FounderContentDecisionService $service){return $this->approvalBack($service->requestPublish($draft,(int)$request->user()->id),'درخواست انتشار محتوا در صف تأیید Founder قرار گرفت.');}
    public function decideContentPublish(Request $request,string $requestId,FounderContentDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}

    private function decisionArgs(Request $request): array{$v=$request->validate(['decision'=>'required|in:approve,reject','reason'=>'nullable|string|max:500']);return [$v['decision'],(int)$request->user()->id,$v['reason']??null];}
    private function approvalBack(array $result,string $message){return back()->with(($result['status']??'')==='awaiting_approval'?'success':'error',($result['status']??'')==='awaiting_approval'?$message:'امکان ایجاد درخواست وجود ندارد.');}
    private function decisionBack(Request $request,array $result){return back()->with((bool)($result['success']??false)?'success':'error',(bool)($result['success']??false)?'تصمیم ثبت و مطابق policy اجرا شد.':'تصمیم یا اجرای درخواست مجاز نبود.');}
}
