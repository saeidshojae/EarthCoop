<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionPolicyVersion;
use App\Services\Elections\ElectionActiveCyclePolicyOverrideService;
use Illuminate\Http\Request;

class ElectionPolicyOverrideController extends Controller
{
    public function edit(Election $election)
    {
        $election->load('policyVersion');
        abort_if($election->policyVersion === null,404);
        $policies=ElectionPolicyVersion::query()
            ->where('group_setting_id',$election->policyVersion->group_setting_id)
            ->where('effective_at','<=',now())->orderByDesc('version')->get();
        $overrides=\App\Models\ElectionPolicyOverride::query()->where('election_id',$election->id)->with(['fromPolicy','toPolicy','actor'])->orderByDesc('applied_at')->get();
        return view('admin.system-settings.elections.policy-override',compact('election','policies','overrides'));
    }

    public function update(Request $request, Election $election, ElectionActiveCyclePolicyOverrideService $service)
    {
        $validated=$request->validate(['policy_version_id'=>'required|integer|exists:election_policy_versions,id','reason'=>'required|string|max:1000']);
        $target=ElectionPolicyVersion::query()->findOrFail((int)$validated['policy_version_id']);
        $service->apply($election,$target,$request->user(),$validated['reason']);
        return back()->with('success','override صریح چرخه جاری با لاگ ممیزی ثبت شد.');
    }
}
