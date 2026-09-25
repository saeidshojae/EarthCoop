<?php

// HomeController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserExperience;
use App\Models\Setting;
use App\Models\Slider;
use App\Services\ProfileCompletionService;

class HomeController extends Controller
{
    public function index()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        
       
        $checkUserHave = UserExperience::where('user_id', auth()->user()->id)->first();

        
                if($checkUserHave == null){
            return redirect('register/step2')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }
        
                if(! app(ProfileCompletionService::class)->hasRequiredResidence(auth()->user())){
            return redirect('register/step3')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }
        
        
        
        // دریافت گروه‌ها از کاربر احراز هویت شده
        $user = auth()->user();
        if ((bool) config('location-governance.groups_enabled', false)) {
            $materializedIds = collect(app(\App\Services\Groups\CanonicalGroupMembershipReconciler::class)->reconcile($user))
                ->pluck('id')->filter()->values();
            $groups = $materializedIds->isEmpty()
                ? collect()
                : $user->groups()
                    ->whereIn('groups.id', $materializedIds->all())
                    ->wherePivot('status', 1)
                    ->get();
        } else {
            $groups = $user->groups()->wherePivot('status', 1)->get();
        }
        
        // دسته‌بندی گروه‌ها بر اساس نوع
        // '0' = عمومی (general)
        // '1' = صنفی (specialty_id دارد)
        // '2' = علمی/تجربی (experience_id دارد)
        // '3' = سنی (age_group_id دارد)
        // '4' = جنسیتی (gender دارد)
        
        if ((bool) config('location-governance.groups_enabled', false)) {
            // Canonical identity is dimension-based. Legacy group_type is only a
            // presentation/backward-compatibility field and must not drive counts.
            $pendingService = app(\App\Services\Groups\PendingLocationGroupRequestService::class);
            $pendingRequests = $pendingService->openForUser($user);
            $groups = $pendingService->presentableCanonicalGroups($groups, $pendingRequests);
            $pendingGroups = $pendingService->presentationGroups($pendingRequests);
            $allSystemGroups = $groups->concat($pendingGroups);
            $generalGroups = $allSystemGroups->where('dimension_key', 'public');
            $specializedGroups = $allSystemGroups->whereIn('dimension_key', ['profession', 'specialty']);
            $exclusiveGroups = $allSystemGroups->whereIn('dimension_key', ['age', 'gender']);
        } else {
            // Legacy fallback while canonical group cutover is disabled.
            $generalGroups = $groups->where('group_type', '0');
            $specializedGroups = $groups->filter(function($group) {
                return $group->group_type == '1' || $group->group_type == '2';
            });
            $exclusiveGroups = $groups->filter(function($group) {
                return $group->group_type == '3' || $group->group_type == '4';
            });
        }
        
        // دریافت حراج‌های فعال
        $activeAuctions = \App\Modules\Stock\Models\Auction::where('status', 'running')
            ->where('ends_at', '>', now())
            ->with('stock')
            ->orderBy('ends_at', 'asc')
            ->limit(3)
            ->get();

        $homeSetting = Setting::find(1);
        $homeSliders = Slider::query()
            ->where('position', 1)
            ->orderBy('created_at')
            ->get();
    
        // ارسال متغیرها به ویو
        return view('home', compact(
            'groups', 'generalGroups', 'specializedGroups', 'exclusiveGroups',
            'activeAuctions', 'homeSetting', 'homeSliders'
        ));
    }
}

