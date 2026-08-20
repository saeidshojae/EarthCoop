<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\StockPayeeAccount;
use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderStockPayeeDecisionService;
use Illuminate\Http\Request;

class FounderStockPayeeController extends Controller
{
    public function index(FounderApprovalInboxService $approvals)
    {
        $stocks=Stock::query()->where('issuer_type','project')->orderBy('id')->get();
        $mappings=StockPayeeAccount::query()->with('account')->whereIn('stock_id',$stocks->pluck('id'))->get()->keyBy('stock_id');
        $accounts=Account::query()->where('status',1)->whereIn('type',['legal_entity','central'])->orderBy('id')->get(['id','account_number','name','type','status']);
        $approvalItems=collect(data_get($approvals->snapshot(),'items',[]))
            ->filter(fn($item)=>data_get($item,'domain')==='stock' && data_get($item,'domain_action')==='configure_payee_account')
            ->values();

        return view('admin.najm-hoda.founder-ops.stock-payees',compact('stocks','mappings','accounts','approvalItems'));
    }

    public function requestConfigure(Request $request,Stock $stock,FounderStockPayeeDecisionService $service)
    {
        $data=$request->validate(['account_id'=>'required|integer|exists:najm_accounts,id']);
        $result=$service->requestConfigure((int)$stock->id,(int)$data['account_id'],(int)$request->user()->id);
        $ok=($result['status']??'')==='awaiting_approval';
        return back()->with($ok?'success':'error',$ok?'درخواست تغییر حساب مقصد در صف تأیید Founder قرار گرفت.':'امکان ایجاد درخواست mapping وجود ندارد.');
    }

    public function decide(Request $request,string $requestId,FounderStockPayeeDecisionService $service)
    {
        $data=$request->validate(['decision'=>'required|in:approve,reject','reason'=>'nullable|string|max:500']);
        $result=$service->decideAndExecute($requestId,$data['decision'],(int)$request->user()->id,$data['reason']??null);
        return back()->with(($result['success']??false)?'success':'error',($result['success']??false)?'تصمیم ثبت و مطابق policy اجرا شد.':'تصمیم یا اجرای mapping مجاز نبود.');
    }
}
