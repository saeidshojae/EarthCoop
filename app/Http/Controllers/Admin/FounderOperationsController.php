<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Models\FounderFinancialRiskFinding;
use App\Models\ModerationCaseSummary;
use App\Models\SupportReplyDraft;
use App\Modules\Secretariat\Models\SecretariatFollowUpProposal;
use App\Services\NajmHoda\FounderOps\FounderAcceptanceStatusService;
use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService;
use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderAuthoritySnapshotService;
use App\Services\NajmHoda\FounderOps\FounderAutonomyBridgeService;
use App\Services\NajmHoda\FounderOps\FounderContentDecisionService;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\NajmHoda\FounderOps\FounderEmailDecisionService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use App\Services\NajmHoda\FounderOps\FounderModerationDecisionService;
use App\Services\NajmHoda\FounderOps\FounderOperationsSnapshotService;
use App\Services\NajmHoda\FounderOps\FounderReferenceApprovalCandidateService;
use App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
use Illuminate\Http\Request;

class FounderOperationsController extends Controller
{
    public function index(Request $request, FounderAttentionService $attention, FounderOperationsSnapshotService $snapshots, FounderApprovalInboxService $approvals, FounderReferenceApprovalCandidateService $referenceCandidates, FounderExecutiveConnectivityService $connectivity, FounderExecutiveWorkQueueService $workQueue, FounderAcceptanceStatusService $acceptanceStatus)
    {
        $hours=max(1,min((int)$request->integer('hours',24),168));
        return view('admin.najm-hoda.founder-ops.index',[
            'hours'=>$hours,'brief'=>$attention->brief($hours),'snapshot'=>$snapshots->snapshot($hours),
            'executiveConnectivity'=>$connectivity->report(),
            'executiveWorkQueue'=>$workQueue->snapshot($hours,30),
            'acceptanceStatus'=>$acceptanceStatus->snapshot($hours,50),
            'approvalInbox'=>$approvals->snapshot(),'referenceCandidates'=>$referenceCandidates->candidates(10),
            'supportDrafts'=>SupportReplyDraft::query()->with(['ticket:id,tracking_code,subject,status,priority,category'])->where('status','draft')->latest('id')->limit(20)->get(),
            'moderationCases'=>ModerationCaseSummary::query()->where('status','draft')->latest('id')->limit(20)->get(),
            'secretariatFollowUps'=>SecretariatFollowUpProposal::query()->with(['dispatch.record:id,registry_number,status'])->where('status','draft')->latest('id')->limit(20)->get(),
            'emailDrafts'=>FounderEmailDraft::query()->where('status','draft')->latest('id')->limit(20)->get(),
            'contentDrafts'=>FounderContentDraft::query()->where('status','draft')->latest('id')->limit(20)->get(),
            'announcementDrafts'=>FounderAnnouncementDraft::query()->where('status','draft')->latest('id')->limit(20)->get(),
            'financialRiskFindings'=>FounderFinancialRiskFinding::query()->where('status','open')
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->latest('id')->limit(30)->get(),
        ]);
    }

    public function brief(Request $request, FounderAttentionService $service){return response()->json(['success'=>true,'data'=>$service->brief((int)$request->integer('hours',24))]);}
    public function snapshot(Request $request, FounderOperationsSnapshotService $service){return response()->json(['success'=>true,'data'=>$service->snapshot((int)$request->integer('hours',24))]);}
    public function connectivity(FounderExecutiveConnectivityService $service){return response()->json(['success'=>true,'data'=>$service->report()]);}
    public function workQueue(Request $request, FounderExecutiveWorkQueueService $service){$hours=max(1,min((int)$request->integer('hours',24),168));$limit=max(1,min((int)$request->integer('limit',30),100));return response()->json(['success'=>true,'data'=>$service->snapshot($hours,$limit)]);}
    public function acceptanceStatus(Request $request, FounderAcceptanceStatusService $service){$hours=max(1,min((int)$request->integer('hours',24),168));$limit=max(1,min((int)$request->integer('limit',50),200));return response()->json(['success'=>true,'data'=>$service->snapshot($hours,$limit)]);}
    public function autonomyPlan(Request $request, FounderAutonomyBridgeService $service){$hours=max(1,min((int)$request->integer('hours',24),168));$limit=max(1,min((int)$request->integer('limit',12),50));return response()->json(['success'=>true,'data'=>$service->plan($hours,$limit)]);}
    public function approvals(FounderApprovalInboxService $service){return response()->json(['success'=>true,'data'=>$service->snapshot()]);}
    public function authority(FounderAuthoritySnapshotService $summary, FounderActionAuthorityService $authority){return response()->json(['success'=>true,'data'=>['summary'=>$summary->snapshot(),'matrix'=>$authority->matrix()]]);}

    public function updateSupportDraft(Request $request,SupportReplyDraft $draft,FounderDraftEditingService $service){$v=$request->validate(['body'=>'required|string|max:20000']);return $this->draftEditBack($service->updateSupport($draft,$v['body'],(int)$request->user()->id));}
    public function updateEmailDraft(Request $request,FounderEmailDraft $draft,FounderDraftEditingService $service){$v=$request->validate(['subject'=>'required|string|max:255','body'=>'required|string|max:100000']);return $this->draftEditBack($service->updateEmail($draft,$v['subject'],$v['body'],(int)$request->user()->id));}
    public function updateContentDraft(Request $request,FounderContentDraft $draft,FounderDraftEditingService $service){$v=$request->validate(['title'=>'required|string|max:255','body'=>'required|string|max:100000','category_id'=>'required|integer|exists:categories,id']);return $this->draftEditBack($service->updateContent($draft,$v['title'],$v['body'],(int)$request->user()->id,(int)$v['category_id']));}
    public function updateAnnouncementDraft(Request $request,FounderAnnouncementDraft $draft,FounderDraftEditingService $service){$v=$request->validate(['title'=>'required|string|max:255','content'=>'required|string|max:100000']);return $this->draftEditBack($service->updateAnnouncement($draft,$v['title'],$v['content'],(int)$request->user()->id));}

    public function requestSupportDraftSend(Request $request, SupportReplyDraft $draft, FounderSupportDraftApprovalService $service){$result=$service->requestSend($draft,(int)$request->user()->id);return $this->approvalBack($result,'درخواست ارسال پاسخ در صف تأیید مدیرکل قرار گرفت.');}
    public function decideSupportDraft(Request $request,string $requestId,FounderSupportDraftApprovalService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestReferenceApprove(Request $request,string $type,int $id,FounderReferenceApprovalDecisionService $service){try{$result=$service->requestApprove($type,$id,(int)$request->user()->id);}catch(\Throwable){return back()->with('error','مورد تأیید معتبر نیست.');}return $this->approvalBack($result,'درخواست تأیید در صف مدیرکل قرار گرفت.');}
    public function decideReferenceApproval(Request $request,string $requestId,FounderReferenceApprovalDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestModerationResolve(Request $request,string $sourceType,int $sourceId,FounderModerationDecisionService $service){try{$result=$service->requestResolve($sourceType,$sourceId,(int)$request->user()->id);}catch(\Throwable){return back()->with('error','گزارش معتبر یا قابل بررسی نیست.');}return $this->approvalBack($result,'درخواست حل گزارش در صف تأیید مدیرکل قرار گرفت.');}
    public function decideModerationResolve(Request $request,string $requestId,FounderModerationDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestEmailSend(Request $request,FounderEmailDraft $draft,FounderEmailDecisionService $service){return $this->approvalBack($service->requestSend($draft,(int)$request->user()->id),'درخواست ارسال ایمیل در صف تأیید مدیرکل قرار گرفت.');}
    public function decideEmailSend(Request $request,string $requestId,FounderEmailDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestContentPublish(Request $request,FounderContentDraft $draft,FounderContentDecisionService $service){return $this->approvalBack($service->requestPublish($draft,(int)$request->user()->id),'درخواست انتشار محتوا در صف تأیید مدیرکل قرار گرفت.');}
    public function decideContentPublish(Request $request,string $requestId,FounderContentDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}
    public function requestAnnouncementPublish(Request $request,FounderAnnouncementDraft $draft,FounderAnnouncementDecisionService $service){return $this->approvalBack($service->requestPublish($draft,(int)$request->user()->id),'درخواست انتشار اطلاعیه در صف تأیید مدیرکل قرار گرفت.');}
    public function decideAnnouncementPublish(Request $request,string $requestId,FounderAnnouncementDecisionService $service){return $this->decisionBack($request,$service->decideAndExecute($requestId,...$this->decisionArgs($request)));}

    private function decisionArgs(Request $request): array{$v=$request->validate(['decision'=>'required|in:approve,reject','reason'=>'nullable|string|max:500']);return [$v['decision'],(int)$request->user()->id,$v['reason']??null];}
    private function approvalBack(array $result,string $message){$awaiting=($result['status']??'')==='awaiting_approval';$reason=(string)($result['reason']??'');$error=$reason==='category_required'?'برای انتشار محتوا ابتدا دسته‌بندی معتبر را در پیش‌نویس انتخاب و ذخیره کنید.':'امکان ایجاد درخواست وجود ندارد.';return back()->with($awaiting?'success':'error',$awaiting?$message:$error);}
    private function draftEditBack(array $result){$ok=(bool)($result['success']??false);$blocked=($result['reason']??'')==='pending_approval_must_be_decided_first';return back()->with($ok?'success':'error',$ok?'متن پیشنهادی نجم هدا ویرایش و ذخیره شد.':($blocked?'برای حفظ یکپارچگی تأیید، ابتدا درخواست تأیید جاری را رد یا تعیین‌تکلیف کنید.':'این پیش‌نویس در وضعیت قابل ویرایش نیست.'));}
    private function decisionBack(Request $request,array $result){return back()->with((bool)($result['success']??false)?'success':'error',(bool)($result['success']??false)?'تصمیم ثبت و مطابق policy اجرا شد.':'تصمیم یا اجرای درخواست مجاز نبود.');}
}
