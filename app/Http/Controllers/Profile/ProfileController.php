<?php

namespace App\Http\Controllers\Profile;
use Illuminate\Support\Facades\Log;

use App\Models\Continent;
use App\Services\GroupService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\OccupationalField;
use App\Models\ExperienceField;
use App\Models\Location;
use App\Models\InvitationCode;
use App\Mail\InvitationMail;
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

class ProfileController 
{
    // نمایش صفحه پروفایل (نمایش اطلاعات غیر قابل تغییر از قبیل هویتی)
    public function showProfile()
    {
               
        $checkUserHave = UserExperience::where('user_id', auth()->user()->id)->first();
        if(auth()->user()->national_id == null){
            return redirect('profile/edit')->with('success', 'شما هنوز اطلاعات هویتی خود را تکمیل نکرده اید، ابتدا با وارد کردن اطلاعات هویتی حساب کاربری خود را فعال و سپس وارد پروفایل خود شوید');
        }
        
                if($checkUserHave == null){
            return redirect('register/step2')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }
        
                if(Address::where('user_id', auth()->user()->id)->first() == null){
            return redirect('register/step3')->with('success', 'شما نمیتوانید وارد برنامه شوید، لطفا مراحل ثبت نام را کامل کنید و اگر نیاز به ویرایش دارید پس از ثبت نام از درون برنامه اقدام کنید');
        }
        
        
        $user = auth()->user();
        $candidates = Candidate::where('user_id', $user->id)->where('accept_status', 1)->get();
        $generalGroups = $user->groups()->where('group_type', 0)->get();
        $specialityGroups = $user->groups()->whereNotNull('specialty_id')->whereNull('experience_id')->get();
        $experienceGroups = $user->groups()->whereNull('specialty_id')->whereNotNull('experience_id')->get();
        $ageGroups = $user->groups()->where('group_type', 3)->get();
        $genderGroups = $user->groups()->where('group_type', 4)->get();

        $expiredGroups = GroupUser::where('status', 1)->where('expired', '<', now())->get();
        $expiredGroups->each(function($groupUser){
            $groupUser->delete();
        });
        
        $joinGroupRequests = GroupUser::where('user_id', $user->id)->where('status', 0)->where('role', 4)->get();

        // Get pending chat requests
        $chatRequests = ChatRequest::where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->with('sender')
            ->latest()
            ->get();
        
        return view('profile.profile', compact(
            'user', 
            'candidates', 
            'generalGroups', 
            'specialityGroups',
            'experienceGroups',
            'ageGroups',
            'genderGroups',
            'chatRequests',
            'joinGroupRequests'
        ));
    }

    public function generateInvationCode(){
        $setting = Setting::find(1);
        $codes = InvitationCode::where('user_id', auth()->user()->id)->orderBy('created_at', 'desc')->get();
        if($codes->count() >= intval($setting->count_invation)){
            return back()->with('error', 'شما اجازه ساخت کد دعوت جدید را ندارید');
        }
        
        $inputs['code'] = Str::random(6);
        $inputs['expire_at'] = Carbon::now()->addHours(intval($setting->expire_invation_time));
        $inputs['user_id'] = auth()->user()->id;
            
        InvitationCode::create($inputs);
        return back()->with('success', 'کد دعوت جدید با موفقیت ایجاد شد');
    }

    public function acceptCandidate($type){  
        if ($type == 'accept') {
    $user = auth()->user();
    $candidate = Candidate::find($_GET['id']);
    $role = Vote::where('candidate_id', $candidate->user_id)
        ->where('election_id', $candidate->election_id)
        ->first()
        ->position;

    // گروه فعلی
    $currentGroup = Group::find($candidate->election->group_id);

    // ست کردن نقش بازرس یا مدیر در گروه فعلی
    $groupUser = GroupUser::where('user_id', $user->id)
        ->where('group_id', $currentGroup->id)
        ->first();
    $groupUser->update(['role' => $role == 0 ? 2 : 3]);

    // سطوح لوکیشن به ترتیب
    $levels = [
        'alley',
        'street',
        'neighborhood',
        'region',
        'city',
        'section',
        'county',
        'province',
        'countery',
        'continent'
    ];

    // سطح فعلی
    $currentIndex = array_search($currentGroup->location_level, $levels);

    // سطح بعدی
    $newLocationLevel = $levels[$currentIndex + 1] ?? null;

    if ($newLocationLevel) {
        $newGroup = Group::where('specialty_id', $currentGroup->specialty_id)
            ->where('experience_id', $currentGroup->experience_id)
            ->where('age_group_id', $currentGroup->age_group_id)
            ->where('gender', $currentGroup->gender)
            ->where('location_level', $newLocationLevel)
            ->first();

        if ($newGroup) {
            // کاربر در گروه بالاتر نقش عادی می‌گیره
            $newGroupUser = GroupUser::firstOrCreate(
                ['user_id' => $user->id, 'group_id' => $newGroup->id],
                ['role' => 1]
            );
            $newGroupUser->update(['role' => 1]);
            
        }
    }

    // همه گروه‌های پایین‌تر (بدون گروه فعلی) نقش = 1
    if ($currentIndex !== false) {
        $previousLevels = array_slice($levels, 0, $currentIndex);

        $previousGroups = Group::where('specialty_id', $currentGroup->specialty_id)
            ->where('experience_id', $currentGroup->experience_id)
            ->where('age_group_id', $currentGroup->age_group_id)
            ->where('gender', $currentGroup->gender)
            ->whereIn('location_level', $previousLevels)
            ->pluck('id');

        GroupUser::where('user_id', $user->id)
            ->whereIn('group_id', $previousGroups)
            ->update(['role' => 1]);
        
        $previousGroupList = Group::where('specialty_id', $currentGroup->specialty_id)
            ->where('experience_id', $currentGroup->experience_id)
            ->where('age_group_id', $currentGroup->age_group_id)
            ->where('gender', $currentGroup->gender)
            ->whereIn('location_level', $previousLevels)
            ->get();

        foreach($previousGroupList as $group){

                 $substitute = GroupUser::where('group_id', $group->id)->where('user_id', '!=', $user->id)->where('role', 1)->first();
            if($substitute){
                $substitute->role = $role == 0 ? 2 : 3;
                $substitute->save();
            }   
        }
    }

    $candidate->accept_status = 2;
    $candidate->save();

    // Dispatch event for accepted candidate
    $candidate->refresh();
    $election = $candidate->election;
    $group = $election->group;
    $user = $candidate->user;
    event(new \App\Events\CandidateAccepted($candidate, $election, $group, $user));

    return redirect()->back()->with('success', 'شما با موفقیت پذیرفته شدید');
}
elseif($type == 'reject'){
            $candidate = Candidate::find($_GET['id']);
            $role = Vote::where('candidate_id', $candidate->user_id)->where('election_id', $candidate->election_id)->first()->position;

            $nextCandidate = $this->nextForReject($candidate->election, $role, $candidate->user_id);
            $candidate->accept_status = 0;
            $candidate->save();
            
            if ($nextCandidate) {
        // گرفتن رکورد بعدی فقط اگر پیدا شد
        $newCandidate = Candidate::where('user_id', $nextCandidate)
                        ->where('election_id', $candidate->election_id)
                        ->first();

        if ($newCandidate) {
            $newCandidate->accept_status = 1;
            $newCandidate->save();
        } else {
            // اگر رکورد در جدول Candidate موجود نبود
            // می‌توانی لاگ بزنی یا عملیات دیگری انجام دهی
            Log::warning("Next candidate ($nextCandidate) not found in Candidate table.");
        }
    } else {
        // اگر علی‌البدل پیدا نشد
        Log::warning("No next candidate available for rejection.");
    }


            return redirect()->back()->with('success', 'شما با موفقیت رد شدید');
        }else{
            return back();
        }
    }
    protected function nextForReject($election, $position, $rejectedId) {
    $candidates = Vote::select('candidate_id', DB::raw('COUNT(*) as total_votes'))
        ->where('election_id', $election->id)
        ->where('position', $position)
        ->groupBy('candidate_id')
        ->orderBy('total_votes', 'desc')
        ->get();

    // حذف نفر رد شده
    $candidates = $candidates->filter(fn($c) => $c->candidate_id != $rejectedId)->values();
    if ($candidates->isEmpty()) {
        return null; // هیچ نفر جایگزینی وجود ندارد
    }

    // سعی می‌کنیم نفر بعدی در لیست را برگردانیم
    $index = $candidates->search(fn($c) => $c->candidate_id == $rejectedId);

    if ($index !== false && isset($candidates[$index + 1])) {
        return $candidates[$index + 1]->candidate_id;
    }

    // اگر نفر بعدی نبود یا $index پیدا نشد، نفر با بیشترین رأی (اول لیست) یا رندوم انتخاب می‌کنیم
    $maxVotes = $candidates->first()->total_votes;
    $topCandidates = $candidates->filter(fn($c) => $c->total_votes == $maxVotes);

    return $topCandidates->random()->candidate_id;
}


    

    // نمایش فرم ویرایش اطلاعات تغییرپذیر (صنف، تخصص، مکان و عکس)
    public function editModifiable()
    {
        $user = auth()->user();

        // دریافت لیست‌های اولیه جهت انتخاب‌های چندگانه
        $occupationalFields = OccupationalField::whereNull(columns: 'parent_id')->get();
        $experienceFields   = ExperienceField::whereNull('parent_id')->get();
        $allOccupationalFields = OccupationalField::with('parent')->get();
        $allExperienceFields = ExperienceField::with('parent')->get();

        $continents = Continent::where('status', 1)->get();
        // ۱) کشورها بر اساس قاره کاربر
        $countries = Country::where('continent_id', $user->address->continent_id)->get();

        // ۲) استان‌ها بر اساس کشور
        $provinces = Province::where('country_id', $user->address->country_id)->get();

        // ۳) شهرستان‌ها بر اساس استان
        $counties = County::where('province_id', $user->address->province_id)->get();

        // ۴) بخش‌ها بر اساس شهرستان
        $sections = District::where('county_id', $user->address->county_id)->get();

        // ۵) شهرها / دهستان‌ها بر اساس بخش
        if($user->address->city_id == null){
            $cities = Village::where('district_id', $user->address->section_id)->get();
        }else{
            $cities = City::where('district_id', $user->address->section_id)->get();
        }
        // ۶) منطقه / روستا بر اساس شهر
        if($user->address->region_id == null){
            $regions = Rural::where('district_id', $user->address->village_id)->get();
            $parentNeighborhoods = $user->address->rural_id;
        }else{
            $regions = Region::where('parent_id', $user->address->city_id)->get();
            $parentNeighborhoods = $user->address->region_id;
        }
        

        // ۷) محله بر اساس منطقه
        $neighborhoods = Neighborhood::where('parent_id', $parentNeighborhoods)
            ->where('status', 1)
            ->get();

        // ۸) خیابان بر اساس محله
        $streets = Street::where('parent_id', $user->address->neighborhood_id)
            ->where('status', 1)
            ->get();

        // ۹) کوچه بر اساس خیابان
        $alleys = Alley::where('parent_id', $user->address->street_id)
            ->where('status', 1)
            ->get();
        $level1Fields = OccupationalField::whereNull('parent_id')->get();
        $level1ExperienceFields = ExperienceField::whereNull('parent_id')->get();

        // کدهای کشورها
        $countryCodes = [
            ['name' => 'ایران', 'code' => '+98', 'example' => '9123456789', 'flag' => '🇮🇷'],
            ['name' => 'آمریکا', 'code' => '+1', 'example' => '4151234567', 'flag' => '🇺🇸'],
            ['name' => 'انگلستان', 'code' => '+44', 'example' => '7123456789', 'flag' => '🇬🇧'],
            ['name' => 'آلمان', 'code' => '+49', 'example' => '1512345678', 'flag' => '🇩🇪'],
            ['name' => 'فرانسه', 'code' => '+33', 'example' => '612345678', 'flag' => '🇫🇷'],
            ['name' => 'ژاپن', 'code' => '+81', 'example' => '901234567', 'flag' => '🇯🇵'],
            ['name' => 'هند', 'code' => '+91', 'example' => '9123456789', 'flag' => '🇮🇳'],
            ['name' => 'ترکیه', 'code' => '+90', 'example' => '5012345678', 'flag' => '🇹🇷'],
            ['name' => 'مصر', 'code' => '+20', 'example' => '1012345678', 'flag' => '🇪🇬'],
            ['name' => 'عربستان', 'code' => '+966', 'example' => '501234567', 'flag' => '🇸🇦'],
            ['name' => 'امارات', 'code' => '+971', 'example' => '501234567', 'flag' => '🇦🇪'],
            ['name' => 'افغانستان', 'code' => '+93', 'example' => '701234567', 'flag' => '🇦🇫'],
            ['name' => 'آلبانی', 'code' => '+355', 'example' => '672345678', 'flag' => '🇦🇱'],
            ['name' => 'الجزایر', 'code' => '+213', 'example' => '551234567', 'flag' => '🇩🇿'],
            ['name' => 'آندورا', 'code' => '+376', 'example' => '312345', 'flag' => '🇦🇩'],
            ['name' => 'آنگولا', 'code' => '+244', 'example' => '923456789', 'flag' => '🇦🇴'],
            ['name' => 'آرژانتین', 'code' => '+54', 'example' => '91123456789', 'flag' => '🇦🇷'],
            ['name' => 'ارمنستان', 'code' => '+374', 'example' => '91234567', 'flag' => '🇦🇲'],
            ['name' => 'استرالیا', 'code' => '+61', 'example' => '412345678', 'flag' => '🇦🇺'],
            ['name' => 'اتریش', 'code' => '+43', 'example' => '6641234567', 'flag' => '🇦🇹'],
            ['name' => 'آذربایجان', 'code' => '+994', 'example' => '512345678', 'flag' => '🇦🇿'],
            ['name' => 'باهاما', 'code' => '+1-242', 'example' => '3591234', 'flag' => '🇧🇸'],
            ['name' => 'بحرین', 'code' => '+973', 'example' => '36001234', 'flag' => '🇧🇭'],
            ['name' => 'بنگلادش', 'code' => '+880', 'example' => '1712345678', 'flag' => '🇧🇩'],
            ['name' => 'باربادوس', 'code' => '+1-246', 'example' => '2501234', 'flag' => '🇧🇧'],
            ['name' => 'بلاروس', 'code' => '+375', 'example' => '291234567', 'flag' => '🇧🇾'],
            ['name' => 'بلژیک', 'code' => '+32', 'example' => '471234567', 'flag' => '🇧🇪'],
            ['name' => 'بلیز', 'code' => '+501', 'example' => '6221234', 'flag' => '🇧🇿'],
            ['name' => 'بنین', 'code' => '+229', 'example' => '90011234', 'flag' => '🇧🇯'],
            ['name' => 'بوتان', 'code' => '+975', 'example' => '17123456', 'flag' => '🇧🇹'],
            ['name' => 'بولیوی', 'code' => '+591', 'example' => '71234567', 'flag' => '🇧🇴'],
            ['name' => 'بوسنی و هرزگوین', 'code' => '+387', 'example' => '61123456', 'flag' => '🇧🇦'],
            ['name' => 'بوتسوانا', 'code' => '+267', 'example' => '71234567', 'flag' => '🇧🇼'],
            ['name' => 'برزیل', 'code' => '+55', 'example' => '11912345678', 'flag' => '🇧🇷'],
            ['name' => 'برونئی', 'code' => '+673', 'example' => '7123456', 'flag' => '🇧🇳'],
            ['name' => 'بلغارستان', 'code' => '+359', 'example' => '878123456', 'flag' => '🇧🇬'],
            ['name' => 'بورکینافاسو', 'code' => '+226', 'example' => '70123456', 'flag' => '🇧🇫'],
            ['name' => 'بوروندی', 'code' => '+257', 'example' => '79123456', 'flag' => '🇧🇮'],
            ['name' => 'کاپ‌ورد', 'code' => '+238', 'example' => '9911234', 'flag' => '🇨🇻'],
            ['name' => 'کامبوج', 'code' => '+855', 'example' => '91234567', 'flag' => '🇰🇭'],
            ['name' => 'کامرون', 'code' => '+237', 'example' => '671234567', 'flag' => '🇨🇲'],
            ['name' => 'کانادا', 'code' => '+1', 'example' => '4161234567', 'flag' => '🇨🇦'],
            ['name' => 'جمهوری آفریقای مرکزی', 'code' => '+236', 'example' => '70012345', 'flag' => '🇨🇫'],
            ['name' => 'چاد', 'code' => '+235', 'example' => '63012345', 'flag' => '🇹🇩'],
            ['name' => 'شیلی', 'code' => '+56', 'example' => '912345678', 'flag' => '🇨🇱'],
            ['name' => 'چین', 'code' => '+86', 'example' => '13123456789', 'flag' => '🇨🇳'],
            ['name' => 'کلمبیا', 'code' => '+57', 'example' => '3211234567', 'flag' => '🇨🇴'],
            ['name' => 'کومور', 'code' => '+269', 'example' => '3212345', 'flag' => '🇰🇲'],
            ['name' => 'کنگو (جمهوری دموکراتیک)', 'code' => '+243', 'example' => '991234567', 'flag' => '🇨🇩'],
            ['name' => 'کنگو (جمهوری)', 'code' => '+242', 'example' => '061234567', 'flag' => '🇨🇬'],
            ['name' => 'کاستاریکا', 'code' => '+506', 'example' => '83123456', 'flag' => '🇨🇷'],
            ['name' => 'کرواسی', 'code' => '+385', 'example' => '912345678', 'flag' => '🇭🇷'],
            ['name' => 'کوبا', 'code' => '+53', 'example' => '51234567', 'flag' => '🇨🇺'],
            ['name' => 'قبرس', 'code' => '+357', 'example' => '96123456', 'flag' => '🇨🇾'],
            ['name' => 'جمهوری چک', 'code' => '+420', 'example' => '601123456', 'flag' => '🇨🇿'],
            ['name' => 'دانمارک', 'code' => '+45', 'example' => '20123456', 'flag' => '🇩🇰'],
            ['name' => 'جیبوتی', 'code' => '+253', 'example' => '77831001', 'flag' => '🇩🇯'],
            ['name' => 'دومینیکا', 'code' => '+1-767', 'example' => '2251234', 'flag' => '🇩🇲'],
            ['name' => 'جمهوری دومینیکن', 'code' => '+1-809', 'example' => '2345678', 'flag' => '🇩🇴'],
            ['name' => 'اکوادور', 'code' => '+593', 'example' => '991234567', 'flag' => '🇪🇨'],
            ['name' => 'مصر', 'code' => '+20', 'example' => '1001234567', 'flag' => '🇪🇬'],
            ['name' => 'السالوادور', 'code' => '+503', 'example' => '70123456', 'flag' => '🇸🇻'],
            ['name' => 'گینه استوایی', 'code' => '+240', 'example' => '222123456', 'flag' => '🇬🇶'],
            ['name' => 'اریتره', 'code' => '+291', 'example' => '7123456', 'flag' => '🇪🇷'],
            ['name' => 'استونی', 'code' => '+372', 'example' => '51234567', 'flag' => '🇪🇪'],
            ['name' => 'اسواتینی', 'code' => '+268', 'example' => '76123456', 'flag' => '🇸🇿'],
        ];
    
        return view('profile.edit', compact('user', 'occupationalFields', 'level1ExperienceFields', 'level1Fields', 'counties', 'sections', 'cities', 'regions', 'neighborhoods', 'streets', 'alleys', 'experienceFields', 'continents', 'countries', 'provinces', 'allOccupationalFields', 'allExperienceFields', 'countryCodes'));
    }

protected function isValidIranianNationalCode(string $code): bool
{
    if (!preg_match('/^[0-9]{10}$/', $code)) return false;

    // رد کردن کدهای تکراری مانند 1111111111
    for ($i = 0; $i < 10; $i++) {
        if (preg_match("/^{$i}{10}$/", $code)) return false;
    }

    // الگوریتم بررسی صحت
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += ((10 - $i) * (int)$code[$i]);
    }

    $remainder = $sum % 11;
    $checkDigit = (int)$code[9];

    return ($remainder < 2 && $checkDigit === $remainder) ||
           ($remainder >= 2 && $checkDigit === (11 - $remainder));
}

    // پردازش به‌روز رسانی اطلاعات تغییرپذیر
    public function updateGeneral(Request $request)
    {
        // اعتبارسنجی اطلاعات جدید
        $inputs = $request->validate([
            'first_name'   => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\s]+$/u',
            'last_name'    => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\s]+$/u',
            'birth_date'   => 'nullable|array|min:3',
            'gender'       => 'nullable|in:male,female',
            'national_id'  => 'nullable|string|regex:/^\d{10}$/|unique:users,national_id,' . auth()->user()->id,
            'phone' => 'nullable|regex:/^(0)?9\d{9}$/|unique:users,phone,' . auth()->user()->id,
            // 'email' => 'required|email|unique:users,email,' . auth()->user()->id,
            'documents.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4084',
            'document_names.*'   => 'nullable|string|max:100',
            'avatar'           => 'nullable|image|mimes:jpg,jpeg,png|max:4084',
            'biografie'              => 'nullable|string|max:1000',
        ]);

        // Handle avatar removal
        if ($request->has('remove_avatar') && $request->remove_avatar == '1') {
            $oldAvatar = auth()->user()->avatar;
            if ($oldAvatar) {
                $oldAvatarPath = public_path('images/users/avatars/' . $oldAvatar);
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }
            $inputs['avatar'] = null;
        } elseif ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $file = $request->file('avatar');
            $name = time() . '.' . $file->getClientOriginalExtension();
            
            // Delete old avatar if exists
            $oldAvatar = auth()->user()->avatar;
            if ($oldAvatar) {
                $oldAvatarPath = public_path('images/users/avatars/' . $oldAvatar);
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }
            
            // Move the cropped image directly to the destination
            $file->move(public_path('images/users/avatars/'), $name);
            
            $inputs['avatar'] = $name;
        }
        
        // چک کردن تعداد فایل‌ها (با در نظر گرفتن فایل‌های موجود)
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            $documentNames = $request->input('document_names', []);
            
            // خواندن مدارک موجود (پشتیبانی از JSON و comma-separated)
            $existingDocumentsRaw = auth()->user()->documents;
            $existingDocuments = [];
            if ($existingDocumentsRaw) {
                $decoded = json_decode($existingDocumentsRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $existingDocuments = $decoded;
                } else {
                    // تبدیل comma-separated به فرمت JSON
                    $filesArray = explode(',', $existingDocumentsRaw);
                    foreach ($filesArray as $file) {
                        $file = trim($file);
                        if (!empty($file)) {
                            $extension = pathinfo($file, PATHINFO_EXTENSION);
                            $existingDocuments[] = [
                                'filename' => $file,
                                'name' => 'مدرک',
                                'type' => strtolower($extension)
                            ];
                        }
                    }
                }
            }
            
            $totalDocuments = count($existingDocuments) + count($files);
            
            if ($totalDocuments > 5) {
                return back()->with('error', 'شما می‌توانید حداکثر ۵ فایل داشته باشید. لطفاً ابتدا برخی از فایل‌های موجود را حذف کنید.')->withInput();
            }
            
            // پردازش فایل‌های جدید
            $allDocuments = $existingDocuments;
            foreach($files as $index => $file){
                $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/users/documents'), $name);
                
                $extension = strtolower($file->getClientOriginalExtension());
                $docName = !empty($documentNames[$index]) ? trim($documentNames[$index]) : 'مدرک';
                
                $allDocuments[] = [
                    'filename' => $name,
                    'name' => $docName,
                    'type' => $extension
                ];
            }
            
            $inputs['documents'] = json_encode($allDocuments, JSON_UNESCAPED_UNICODE);                
        }

        $user = User::find(auth()->user()->id);
        $oldBirthDate = $user->birth_date;
        $newBirthDate = $inputs['birth_date'] ?? null;
        
    if(isset($inputs['national_id']) AND $inputs['national_id'] != null){
        if (!$this->isValidIranianNationalCode($inputs['national_id'])) {
            return back()->with('error', 'کد ملی وارد شده معتبر نیست')->withInput();
        }   
    }
    
        if ($newBirthDate && $oldBirthDate !== $newBirthDate) {
            $groupService = new \App\Services\GroupService();

            $oldAgeGroup = $groupService->getAgeGroup($user);
            $inputs['birth_date'] = (new \Morilog\Jalali\Jalalian((int)$inputs['birth_date'][2], (int)$inputs['birth_date'][1], (int)$inputs['birth_date'][0]))->toCarbon();
            // آپدیت اطلاعات جدید (اول باید انجام بشه تا تاریخ جدید اعمال بشه)
            $user->update($inputs);

            $newAgeGroup = $groupService->getAgeGroup($user); // حالا که birth_date جدید اعمال شده، گروه جدید رو می‌گیریم

            // اگه گروه سنی تغییر کرده، گروه‌های قبلی رو حذف و جدید رو بساز
            if (!$oldAgeGroup || !$newAgeGroup || $oldAgeGroup->id !== $newAgeGroup->id) {
                // حذف گروه‌های سنی قبلی (نوع گروه 3)
                $oldGroups = $user->groups()->where('group_type', 3)->get();
                foreach ($oldGroups as $group) {
                    $user->groups()->detach($group->id);
                }

                // ساخت گروه‌های سنی جدید (گروه‌های سنی جهانی + مکانی)
                foreach ($groupService->getLocationLevels($user) as $location) {
                    $group = $groupService->findOrCreateGroup('3', $location, null, null, $newAgeGroup->id);
                    $user->groups()->syncWithoutDetaching([$group->id]);

                    if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) {
                        $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                    }
                }

                // همچنین گروه سنی جهانی
                $globalGroup = \App\Models\Group::firstOrCreate([
                    'group_type' => 3,
                    'location_level' => 'global',
                    'address_id' => null,
                    'age_group_id' => $newAgeGroup->id,
                ], [
                    'name' => "گروه سنی {$newAgeGroup->title} جهانی",
                ]);

                $user->groups()->syncWithoutDetaching([$globalGroup->id]);
            }
        } else {
            $user->update($inputs); // تاریخ تولد تغییر نکرد
        }
        
        if($user->first_name != null AND $user->last_name != null AND $user->gender != null AND $user->national_id != null AND $user->phone != null){
           $user->status = 1; 
           $user->edited = 1;
           $user->save();
        }

          app(ProfileCompletionService::class)->maybeAward($user);



        return back()->with('success', 'پروفایل با موفقیت ویرایش شد');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required',
            'password'          => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'لطفاً رمز فعلی را وارد کنید.',
            'new_password.required'     => 'لطفاً رمز جدید را وارد کنید.',
            'new_password.min'          => 'رمز جدید باید حداقل ۸ کاراکتر باشد.',
            'new_password.confirmed'    => 'تکرار رمز عبور با رمز جدید مطابقت ندارد.',
        ]);

        $user = Auth::user();

        // بررسی رمز فعلی
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'رمز فعلی اشتباه است.']);
        }

        // ذخیره رمز جدید
        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'رمز عبور با موفقیت تغییر یافت.');
    }

    public function updateExperience(Request $request)
    {
        $validated = $request->validate([
            'occupational_fields' => 'required|array',
            'occupational_fields.*' => 'exists:occupational_fields,id',
            'experience_fields' => 'required|array',
            'experience_fields.*' => 'exists:experience_fields,id',
        ]);

        $user = Auth::user();

        // 🔹 تخصص‌های صنفی (occupational)
        $currentOccupational = $user->specialties->pluck('id')->toArray();
        $newOccupational = $validated['occupational_fields'];
        $addedOccupational = array_diff($newOccupational, $currentOccupational);
        $removedOccupational = array_diff($currentOccupational, $newOccupational);

        $user->specialties()->sync($newOccupational);
        $groupService = new \App\Services\GroupService();

        foreach ($addedOccupational as $id) {
            $specialty = \App\Models\OccupationalField::find($id);
            $globalGroup = \App\Models\Group::firstOrCreate([
                'group_type' => '1',
                'location_level' => 'global',
                'address_id' => null,
                'specialty_id' => $specialty->id,
            ], [
                'name' => "اصناف {$specialty->name} جهانی",
            ]);
            $groupService->addUserToGroup($user, $globalGroup);
            $locations = $groupService->getLocationLevels($user);

            foreach ($locations as $index => $location) {
                $group = $groupService->findOrCreateGroup('1', $location, $specialty->id);
                $groupService->addUserToGroup($user, $group);
    
                // اگر آخرین لوکیشن بود، نقش role = 1 بده
                if ($index === array_key_last($locations)) {
                    $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                }
            }
            
        }   
        
        foreach ($removedOccupational as $id) {
            $current = $id;
        
            // تا وقتی parent_id موجود است (یعنی هنوز به ریشه نرسیده)
            while ($current !== null) {
                // پیدا کردن همهٔ گرو‌ه‌های متصل به این specialty_id
                $groupIds = \App\Models\Group::where('group_type', 1)
                    ->where('specialty_id', $current)
                    ->pluck('id')
                    ->toArray();
        
                // جدا کردن کاربر از این گروه‌ها
                if (!empty($groupIds)) {
                    $user->groups()->detach($groupIds);
                }
        
                // حرکت به سمت والد بعدی
                $current = \App\Models\OccupationalField::find($current)?->parent_id;
            }
        }

        // 🔹 تخصص‌های تجربی (experience)
        $currentExperience = $user->experiences->pluck('id')->toArray();
        $newExperience = $validated['experience_fields'];
        $addedExperience = array_diff($newExperience, $currentExperience);
        $removedExperience = array_diff($currentExperience, $newExperience);
        $user->experiences()->sync($newExperience);
    
        foreach ($addedExperience as $id) {
            $experience = \App\Models\ExperienceField::find($id);
            $globalGroup = \App\Models\Group::firstOrCreate([
                'group_type' => '2',
                'location_level' => 'global',
                'address_id' => null,
                'experience_id' => $experience->id,
            ], [
                'name' => "متخصصان {$experience->name} جهانی",
            ]);
            $groupService->addUserToGroup($user, $globalGroup);
            $locations = $groupService->getLocationLevels($user);
            foreach ($locations as $index => $location) {
                $group = $groupService->findOrCreateGroup('2', $location, null, $experience->id);
                $groupService->addUserToGroup($user, $group);
    
                // اگر آخرین لوکیشن بود، نقش role = 1 بده
                if ($index === array_key_last($locations)) {
                    $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                }
            }
            
        }
foreach ($removedExperience as $id) {
    $current = $id;

    // تا وقتی parent_id موجود است
    while ($current !== null) {
        // همهٔ گروه‌های مرتبط با این experience_id
        $groupIds = \App\Models\Group::where('group_type', 2)
            ->where('experience_id', $current)
            ->pluck('id')
            ->toArray();

        if (!empty($groupIds)) {
            $user->groups()->detach($groupIds);
        }

        // حرکت به سمت والد
        $current = \App\Models\ExperienceField::find($current)?->parent_id;
    }
}

        app(ProfileCompletionService::class)->maybeAward($user);

        return redirect()->route('profile.edit')->with('success', 'زمینه‌های صنفی و تجربی با موفقیت به‌روزرسانی شدند.');
    }


    public function updateSocialNetworks(Request $request)
    {
        $request->validate([
            'options'   => 'nullable|array',
            'options.*' => 'nullable|url',
        ]);
    
        $user = Auth::user();
    
        $cleanedLinks = array_filter($request->input('options', []));
    
        $user->update([
            'social_networks' => $cleanedLinks,
        ]);
    
        return back()->with('success', 'لینک‌های شبکه اجتماعی ذخیره شدند.');
    }
    
    public function updateAddress(Request $request)
    {

        $inputs = $request->validate([
            'continent_id'     => 'required|exists:continents,id',
            'country_id'       => 'required|exists:countries,id',
            'province_id'      => 'required|exists:provinces,id',
            'county_id'        => 'required|exists:counties,id',
            'section_id'       => 'required|exists:districts,id',
            'city_id'          => 'required',
            'region_id'        => 'required',
            'neighborhood_id'  => 'required|exists:neighborhoods,id',
            'street_id'        => 'nullable|exists:streets,id',
            'alley_id'         => 'nullable|exists:alleies,id',
        ]);

        $user = Auth::user();
        $previousAddress = $user->address->replicate(); // کپی آدرس قبلی

        // تشخیص city یا rural و region یا village
        if (str_starts_with($inputs['city_id'], 'rural_rural_')) {
            $inputs['rural_id'] = str_replace('rural_rural_', '', $inputs['city_id']);
            $inputs['city_id'] = null;

            $inputs['village_id'] = $inputs['region_id'];
            // $inputs['region_id'] = null;
        } elseif (str_starts_with($inputs['city_id'], 'city_city_')) {
            $inputs['city_id'] = str_replace('city_city_', '', $inputs['city_id']);
            // $inputs['village_id'] = null;
            $inputs['rural_id'] = null;
        }   

        if(!isset($inputs['street_id'])){
            $inputs['street_id'] = null;
        }
        if(!isset($inputs['alley_id'])){
            $inputs['alley_id'] = null;
        }

        // بروزرسانی آدرس کاربر
        $user->address->update($inputs);

        // دریافت گروه‌هایی که بر اساس آدرس قبلی ساخته شده بودند
        $groupService = new GroupService();
        $oldLevels = $groupService->getLocationLevelsFromAddress($previousAddress);
        $oldGroupIds = Group::whereIn('location_level', collect($oldLevels)->pluck('level'))
            ->whereIn('address_id', collect($oldLevels)->pluck('id'))
            ->pluck('id')
            ->toArray();
        
        $allVoters = Vote::where('voter_id', $user->id)->get();
        foreach($allVoters as $vote){
            $vote->delete();
        }
        
        // حذف عضویت قبلی در گروه‌های مرتبط با لوکیشن
        $user->groups()->detach($oldGroupIds);

        // رفرش اطلاعات جدید از دیتابیس و لود روابط مورد نیاز
        $user->refresh();
        $user->load(['address', 'specialties', 'experiences']);

        // ساخت گروه‌های جدید
        $groupService->generateGroupsForUser($user);

        app(ProfileCompletionService::class)->maybeAward($user);

        return back()->with('success', 'مکان شما با موفقیت به‌روزرسانی شد.');
    }

    // ارسال کد دعوت
    public function sendInvitation(Request $request)
    {
        $request->validate([
            'invite_email' => 'required|email'
        ]);

        $setting = Setting::find(1);
        $code = InvitationCode::create([
            'code' => Str::random(10),
            'user_id' => auth()->id(),
            'expire_at' => Carbon::now()->addHours(intval($setting->expire_invation_time ?? 72))
        ]);

        Mail::to($request->invite_email)->send(new InvitationMail($code->code, $code->expire_at));

        return back()->with('success', 'ایمیل دعوت با موفقیت ارسال شد.');
    }

    public function showProfileMember(User $user)
    {
        $chatRequests = ChatRequest::where('receiver_id', auth()->id())
            ->where('status', 'pending')
            ->with('sender')
            ->latest()
            ->get();

        // Get all group types for the user
        $generalGroups = $user->groups()->where('group_type', 0)->get();
        $specialityGroups = $user->groups()->whereNotNull('specialty_id')->whereNull('experience_id')->get();
        $experienceGroups = $user->groups()->whereNull('specialty_id')->whereNotNull('experience_id')->get();
        $ageGroups = $user->groups()->where('group_type', 3)->get();
        $genderGroups = $user->groups()->where('group_type', 4)->get();

        return view('profile.profile-member', compact(
            'user',
            'chatRequests',
            'generalGroups',
            'specialityGroups',
            'experienceGroups',
            'ageGroups',
            'genderGroups'
        ));
    } 

    public function showInfo(Request $request)
    {
        $field = $request->input('field', $request->query('field'));

        $map = [
            'name'        => 'show_name',
            'email'       => 'show_email',
            'phone'       => 'show_phone',
            'birthdate'   => 'show_birthdate',
            'gender'      => 'show_gender',
            'national_id' => 'show_national_id',
            'biografie'   => 'show_biografie',
            'documents'   => 'show_documents',
            'groups'      => 'show_groups',
            'created_at'  => 'show_created_at',
            'social'      => 'show_social_networks',
        ];

        if (!$field || !array_key_exists($field, $map)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid field',
                ], 422);
            }

            return back();
        }

        $user = $request->user();
        $column = $map[$field];

        // toggle (treat null as false)
        $user->{$column} = (int) !((bool) $user->{$column});
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'field' => $field,
                'column' => $column,
                'value' => (int) $user->{$column},
            ]);
        }

        return back()->with('success', 'پروفایل با موفقیت ویرایش شد');
    }

    public function profileJoinGroup($type){
        $groupUser = GroupUser::find($_GET['id']);
        if($type == 0){
            $groupUser->delete();
            return back()->with('success', 'درخواست شما با موفقیت ثبت شد');
        }else{
            $groupUser->status = $type;
            $groupUser->save();

            return redirect()->route('groups.chat', $groupUser->group_id)->with('success', 'شما با موفقیت به گروه اضافه شدید');
        }
    }
    
    public function deleteDocument(Request $request, $index)
    {
        $user = auth()->user();
        $documentsRaw = $user->documents;
        
        if (!$documentsRaw) {
            return back()->with('error', 'مدرکی یافت نشد.');
        }
        
        // خواندن مدارک (پشتیبانی از JSON و comma-separated)
        $decoded = json_decode($documentsRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $documents = $decoded;
        } else {
            // تبدیل comma-separated به فرمت JSON
            $filesArray = explode(',', $documentsRaw);
            $documents = [];
            foreach ($filesArray as $file) {
                $file = trim($file);
                if (!empty($file)) {
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    $documents[] = [
                        'filename' => $file,
                        'name' => 'مدرک',
                        'type' => strtolower($extension)
                    ];
                }
            }
        }

        if (isset($documents[$index])) {
            $documentToDelete = $documents[$index];
            $filename = is_array($documentToDelete) ? $documentToDelete['filename'] : $documentToDelete;
            
            // حذف فایل از public directory
            $filePath = public_path('images/users/documents/' . $filename);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // حذف از آرایه
            unset($documents[$index]);
            $documents = array_values($documents); // بازآرایی ایندکس‌ها
            
            // به‌روزرسانی documents در دیتابیس (به صورت JSON)
            $user->documents = !empty($documents) ? json_encode($documents, JSON_UNESCAPED_UNICODE) : null;
            $user->save();
        }

        return back()->with('success', 'مدرک با موفقیت حذف شد.');
    }

}