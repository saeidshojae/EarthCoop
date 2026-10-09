<?php

namespace App\Http\Controllers\Profile;
use Illuminate\Support\Facades\Log;

use App\Models\Continent;
use App\Services\GroupService;
use App\Services\ProfileCompletionService;
use App\Services\Communication\CommunicationDispatcher;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Context\TemporalContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\OccupationalField;
use App\Models\ExperienceField;
use App\Models\Location;
use App\Models\InvitationCode;
use App\Models\Alley;
use App\Models\Candidate;
use App\Models\City;
use App\Models\Country;
use App\Models\County;
use App\Models\District;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Neighborhood;
use App\Models\Province;
use App\Models\Region;
use App\Models\Rural;
use App\Models\Street;
use App\Models\User;
use App\Models\Village;
use App\Models\Vote;
use App\Models\Address;
use App\Models\UserExperience;
use Carbon\Carbon;
use App\Rules\JalaliMinimumAge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\ChatRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController 
{
    public function showProfile()
    {
        $checkUserHave = UserExperience::where('user_id', auth()->user()->id)->first();
        if(auth()->user()->national_id == null){
            return redirect('profile/edit')->with('success', 'شما هنوز اطلاعات هویتی خود را تکمیل نکرده اید، ابتدا با وارد کردن اطلاعات هویتی حساب کاربری خود را فعال و سپس وارد پروفایل خود شوید');
        }
        if($checkUserHave == null){
            return redirect('register/step2')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }
        if (! app(ProfileCompletionService::class)->hasRequiredResidence(auth()->user())) {
            return redirect('register/step3')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }
        $user = auth()->user();
        $candidates = Candidate::where('user_id', $user->id)->where('accept_status', 1)->get();
        // Stage C: exclude historical legacy spatial memberships and inactive pivots.
        // Preserve user-managed groups, whose identities are not governance-area based.
        $currentGroups = $user->groups()
            ->wherePivot('status', 1)
            ->where(function ($query) {
                $query->whereNull('group_user.expired')
                    ->orWhere('group_user.expired', '>', now());
            })
            ->when((bool) config('location-governance.groups_enabled', false), function ($query) {
                $query->where(function ($groups) {
                    $groups->whereNotNull('groups.governance_area_id')
                        ->orWhere('groups.location_level', 10);
                });
            })
            ->get();
        $generalGroups = $currentGroups->where('group_type', 0)->values();
        $specialityGroups = $currentGroups->filter(fn ($group) => $group->specialty_id !== null && $group->experience_id === null)->values();
        $experienceGroups = $currentGroups->filter(fn ($group) => $group->specialty_id === null && $group->experience_id !== null)->values();
        $ageGroups = $currentGroups->where('group_type', 3)->values();
        $genderGroups = $currentGroups->where('group_type', 4)->values();
        return view('profile.profile-member', compact('user','chatRequests','generalGroups','specialityGroups','experienceGroups','ageGroups','genderGroups'));
    }

    public function showInfo(Request $request)
    {
        $field = $request->input('field', $request->query('field'));
        $map = [
            'name'=>'show_name','email'=>'show_email','phone'=>'show_phone','birthdate'=>'show_birthdate','gender'=>'show_gender',
            'national_id'=>'show_national_id','biografie'=>'show_biografie','documents'=>'show_documents','groups'=>'show_groups',
            'created_at'=>'show_created_at','social'=>'show_social_networks',
        ];
        if (!$field || !array_key_exists($field, $map)) {
            if ($request->expectsJson()) return response()->json(['ok'=>false,'message'=>'Invalid field'], 422);
            return back();
        }
        $user = $request->user();
        $column = $map[$field];
        $user->{$column} = (int) !((bool) $user->{$column});
        $user->save();
        if ($request->expectsJson()) return response()->json(['ok'=>true,'field'=>$field,'column'=>$column,'value'=>(int)$user->{$column}]);
        return back()->with('success', 'پروفایل با موفقیت ویرایش شد');
    }

    public function profileJoinGroup($type){
        $groupUser = GroupUser::find($_GET['id']);
        if($type == 0){
            $groupUser->delete();
            return back()->with('success', 'درخواست شما با موفقیت ثبت شد');
        }
        $groupUser->status = $type;
        $groupUser->save();
        return redirect()->route('groups.chat', $groupUser->group_id)->with('success', 'شما با موفقیت به گروه اضافه شدید');
    }

    public function deleteDocument(Request $request, $index)
    {
        $user = auth()->user();
        $documentsRaw = $user->documents;
        if (!$documentsRaw) return back()->with('error', 'مدرکی یافت نشد.');
        $decoded = json_decode($documentsRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $documents = $decoded;
        } else {
            $filesArray = explode(',', $documentsRaw);
            $documents = [];
            foreach ($filesArray as $file) {
                $file = trim($file);
                if(!empty($file)) {
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    $documents[] = ['filename'=>$file,'name'=>'مدرک','type'=>strtolower($extension)];
                }
            }
        }
        if (isset($documents[$index])) {
            $documentToDelete = $documents[$index];
            $filename = is_array($documentToDelete) ? $documentToDelete['filename'] : $documentToDelete;
            $filePath = public_path('images/users/documents/' . $filename);
            if (file_exists($filePath)) unlink($filePath);
            unset($documents[$index]);
            $documents = array_values($documents);
            $user->documents = !empty($documents) ? json_encode($documents, JSON_UNESCAPED_UNICODE) : null;
            $user->save();
        }
        return back()->with('success', 'مدرک با موفقیت حذف شد.');
    }
}
