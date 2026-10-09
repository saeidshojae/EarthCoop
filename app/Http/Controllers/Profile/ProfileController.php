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
        $chatRequests = ChatRequest::where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->with('sender')
            ->latest()
            ->get();
        return view('profile.profile', compact(
            'user', 'candidates', 'generalGroups', 'specialityGroups', 'experienceGroups',
            'ageGroups', 'genderGroups', 'chatRequests', 'joinGroupRequests'
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
            $currentGroup = Group::find($candidate->election->group_id);
            $groupUser = GroupUser::where('user_id', $user->id)
                ->where('group_id', $currentGroup->id)
                ->first();
            $groupUser->update(['role' => $role == 0 ? 2 : 3]);

            $levels = ['alley','street','neighborhood','region','city','section','county','province','countery','continent'];
            $currentIndex = array_search($currentGroup->location_level, $levels);
            $newLocationLevel = $levels[$currentIndex + 1] ?? null;

            if ($newLocationLevel) {
                $newGroup = Group::where('specialty_id', $currentGroup->specialty_id)
                    ->where('experience_id', $currentGroup->experience_id)
                    ->where('age_group_id', $currentGroup->age_group_id)
                    ->where('gender', $currentGroup->gender)
                    ->where('location_level', $newLocationLevel)
                    ->first();
                if ($newGroup) {
                    $newGroupUser = GroupUser::firstOrCreate(
                        ['user_id' => $user->id, 'group_id' => $newGroup->id],
                        ['role' => 1]
                    );
                    $newGroupUser->update(['role' => 1]);
                }
            }

            if ($currentIndex !== false) {
                $previousLevels = array_slice($levels, 0, $currentIndex);
                $previousGroups = Group::where('specialty_id', $currentGroup->specialty_id)
                    ->where('experience_id', $currentGroup->experience_id)
                    ->where('age_group_id', $currentGroup->age_group_id)
                    ->where('gender', $currentGroup->gender)
                    ->whereIn('location_level', $previousLevels)
                    ->pluck('id');
                GroupUser::where('user_id', $user->id)->whereIn('group_id', $previousGroups)->update(['role' => 1]);
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
            $candidate->refresh();
            $election = $candidate->election;
            $group = $election->group;
            $user = $candidate->user;
            event(new \App\Events\CandidateAccepted($candidate, $election, $group, $user));
            return redirect()->back()->with('success', 'شما با موفقیت پذیرفته شدید');
        } elseif($type == 'reject') {
            $candidate = Candidate::find($_GET['id']);
            $role = Vote::where('candidate_id', $candidate->user_id)->where('election_id', $candidate->election_id)->first()->position;
            $nextCandidate = $this->nextForReject($candidate->election, $role, $candidate->user_id);
            $candidate->accept_status = 0;
            $candidate->save();
            if ($nextCandidate) {
                $newCandidate = Candidate::where('user_id', $nextCandidate)->where('election_id', $candidate->election_id)->first();
                if ($newCandidate) {
                    $newCandidate->accept_status = 1;
                    $newCandidate->save();
                } else {
                    Log::warning("Next candidate ($nextCandidate) not found in Candidate table.");
                }
            } else {
                Log::warning("No next candidate available for rejection.");
            }
            return redirect()->back()->with('success', 'شما با موفقیت رد شدید');
        }
        return back();
    }

    protected function nextForReject($election, $position, $rejectedId) {
        $candidates = Vote::select('candidate_id', DB::raw('COUNT(*) as total_votes'))
            ->where('election_id', $election->id)
            ->where('position', $position)
            ->groupBy('candidate_id')
            ->orderBy('total_votes', 'desc')
            ->get();
        $candidates = $candidates->filter(fn($c) => $c->candidate_id != $rejectedId)->values();
        if ($candidates->isEmpty()) return null;
        $index = $candidates->search(fn($c) => $c->candidate_id == $rejectedId);
        if ($index !== false && isset($candidates[$index + 1])) return $candidates[$index + 1]->candidate_id;
        $maxVotes = $candidates->first()->total_votes;
        $topCandidates = $candidates->filter(fn($c) => $c->total_votes == $maxVotes);
        return $topCandidates->random()->candidate_id;
    }

    public function editModifiable()
    {
        $user = auth()->user();
        $occupationalFields = OccupationalField::whereNull(columns: 'parent_id')->get();
        $experienceFields   = ExperienceField::whereNull('parent_id')->get();
        $allOccupationalFields = OccupationalField::with('parent')->get();
        $allExperienceFields = ExperienceField::with('parent')->get();
        $continents = Continent::where('status', 1)->get();
        $countries = Country::where('continent_id', $user->address->continent_id)->get();
        $provinces = Province::where('country_id', $user->address->country_id)->get();
        $counties = County::where('province_id', $user->address->province_id)->get();
        $sections = District::where('county_id', $user->address->county_id)->get();
        if($user->address->city_id == null){
            $cities = Village::where('district_id', $user->address->section_id)->get();
        }else{
            $cities = City::where('district_id', $user->address->section_id)->get();
        }
        if($user->address->region_id == null){
            $regions = Rural::where('district_id', $user->address->village_id)->get();
            $parentNeighborhoods = $user->address->rural_id;
        }else{
            $regions = Region::where('parent_id', $user->address->city_id)->get();
            $parentNeighborhoods = $user->address->region_id;
        }
        $neighborhoods = Neighborhood::where('parent_id', $parentNeighborhoods)->where('status', 1)->get();
        $streets = Street::where('parent_id', $user->address->neighborhood_id)->where('status', 1)->get();
        $alleys = Alley::where('parent_id', $user->address->street_id)->where('status', 1)->get();
        $level1Fields = OccupationalField::whereNull('parent_id')->get();
        $level1ExperienceFields = ExperienceField::whereNull('parent_id')->get();

        $countryCodes = config('phone-countries', []);


        return view('profile.edit', compact('user', 'occupationalFields', 'level1ExperienceFields', 'level1Fields', 'counties', 'sections', 'cities', 'regions', 'neighborhoods', 'streets', 'alleys', 'experienceFields', 'continents', 'countries', 'provinces', 'allOccupationalFields', 'allExperienceFields', 'countryCodes'));
    }

    protected function isValidIranianNationalCode(string $code): bool
    {
        if (!preg_match('/^[0-9]{10}$/', $code)) return false;
        for ($i = 0; $i < 10; $i++) {
            if (preg_match("/^{$i}{10}$/", $code)) return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) $sum += ((10 - $i) * (int)$code[$i]);
        $remainder = $sum % 11;
        $checkDigit = (int)$code[9];
        return ($remainder < 2 && $checkDigit === $remainder) || ($remainder >= 2 && $checkDigit === (11 - $remainder));
    }

    public function updateGeneral(Request $request)
    {
        /** @var User $user */
        $user = User::findOrFail(auth()->id());

        if ($request->exists('phone') && $request->input('phone') !== null) {
            $normalizedPhone = preg_replace('/\s+/', '', (string) $request->input('phone')) ?? '';
            if (str_starts_with($normalizedPhone, '0')) {
                $normalizedPhone = substr($normalizedPhone, 1);
            }
            $request->merge(['phone' => $normalizedPhone]);
        }

        // Phone number + calling code are one identity value. If a crafted
        // request submits only one half, validate it against the persisted other half.
        if ($request->exists('phone') && ! $request->exists('country_code')) {
            $request->merge(['country_code' => $user->phone_country_code ?: '+98']);
        } elseif ($request->exists('country_code') && ! $request->exists('phone')) {
            $request->merge(['phone' => $user->phone]);
        }

        $immutableErrors = [];
        if ($request->has('email') && (string) $request->input('email') !== (string) $user->email) {
            $immutableErrors['email'] = 'ایمیل حساب قابل تغییر نیست.';
        }
        if ($request->has('national_id') && (string) $request->input('national_id') !== (string) $user->national_id) {
            $immutableErrors['national_id'] = 'کد ملی پس از ثبت اولیه قابل تغییر نیست.';
        }
        if ($immutableErrors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($immutableErrors);
        }

        $identityFields = ['first_name', 'last_name', 'birth_date', 'gender', 'phone', 'country_code'];
        if ($user->hasUsedIdentityEdit()) {
            $lockedErrors = [];
            foreach ($identityFields as $field) {
                if (! $request->exists($field)) {
                    continue;
                }

                $incoming = $request->input($field);
                if ($field === 'country_code') {
                    $current = $user->phone_country_code ?: '+98';
                } elseif ($field === 'birth_date') {
                    $current = $user->getRawOriginal('birth_date');
                } else {
                    $current = $user->{$field};
                }

                if (is_array($incoming) || (string) $incoming !== (string) $current) {
                    $lockedErrors[$field] = 'این بخش از اطلاعات هویتی قبلاً یک‌بار ویرایش شده و دیگر قابل تغییر نیست.';
                }
            }

            if ($lockedErrors !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages($lockedErrors);
            }
        }

        if ($request->exists('phone') || $request->exists('country_code')) {
            $candidateCode = (string) $request->input('country_code', $user->phone_country_code ?: '+98');
            $candidatePhone = (string) $request->input('phone', $user->phone);

            $phoneCollision = User::query()
                ->where('phone_country_code', $candidateCode)
                ->where('phone', $candidatePhone)
                ->whereKeyNot($user->id)
                ->exists();

            if ($phoneCollision) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'phone' => 'این شماره تلفن با همین کد کشور قبلاً ثبت شده است.',
                ]);
            }
        }

        $inputs = $request->validate([
            'first_name'   => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\s]+$/u',
            'last_name'    => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\s]+$/u',
            'birth_date'   => 'nullable|array|min:3',
            'gender'       => 'nullable|in:male,female',
            'nickname' => 'nullable|string|max:80',
            'country_code' => ['nullable', Rule::in(array_column(config('phone-countries', []), 'code'))],
            'phone' => [
                'nullable',
                'regex:/^\d{6,15}$/',
                Rule::unique('users', 'phone')
                    ->where(fn ($query) => $query->where(
                        'phone_country_code',
                        $request->input('country_code', $user->phone_country_code ?: '+98')
                    ))
                    ->ignore($user->id),
            ],
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4084',
            'document_names.*' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:4084',
            'biografie' => 'nullable|string|max:1000',
        ]);

        if ($request->has('remove_avatar') && $request->remove_avatar == '1') {
            $oldAvatar = auth()->user()->avatar;
            if ($oldAvatar) {
                $oldAvatarPath = public_path('images/users/avatars/' . $oldAvatar);
                if (file_exists($oldAvatarPath)) unlink($oldAvatarPath);
            }
            $inputs['avatar'] = null;
        } elseif ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $file = $request->file('avatar');
            $name = time() . '.' . $file->getClientOriginalExtension();
            $oldAvatar = auth()->user()->avatar;
            if ($oldAvatar) {
                $oldAvatarPath = public_path('images/users/avatars/' . $oldAvatar);
                if (file_exists($oldAvatarPath)) unlink($oldAvatarPath);
            }
            $file->move(public_path('images/users/avatars/'), $name);
            $inputs['avatar'] = $name;
        }

        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            $documentNames = $request->input('document_names', []);
            $existingDocumentsRaw = auth()->user()->documents;
            $existingDocuments = [];
            if ($existingDocumentsRaw) {
                $decoded = json_decode($existingDocumentsRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $existingDocuments = $decoded;
                } else {
                    $filesArray = explode(',', $existingDocumentsRaw);
                    foreach ($filesArray as $file) {
                        $file = trim($file);
                        if (!empty($file)) {
                            $extension = pathinfo($file, PATHINFO_EXTENSION);
                            $existingDocuments[] = ['filename' => $file, 'name' => 'مدرک', 'type' => strtolower($extension)];
                        }
                    }
                }
            }
            $totalDocuments = count($existingDocuments) + count($files);
            if ($totalDocuments > 5) return back()->with('error', 'شما می‌توانید حداکثر ۵ فایل داشته باشید. لطفاً ابتدا برخی از فایل‌های موجود را حذف کنید.')->withInput();
            $allDocuments = $existingDocuments;
            foreach($files as $index => $file){
                $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/users/documents'), $name);
                $extension = strtolower($file->getClientOriginalExtension());
                $docName = !empty($documentNames[$index]) ? trim($documentNames[$index]) : 'مدرک';
                $allDocuments[] = ['filename' => $name, 'name' => $docName, 'type' => $extension];
            }
            $inputs['documents'] = json_encode($allDocuments, JSON_UNESCAPED_UNICODE);
        }

        $identityBefore = [
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'birth_date' => (string) $user->getRawOriginal('birth_date'),
            'gender' => (string) $user->gender,
            'phone' => (string) $user->phone,
            'country_code' => (string) ($user->phone_country_code ?: '+98'),
        ];

        if (array_key_exists('phone', $inputs) && $inputs['phone'] !== null) {
            $phone = preg_replace('/\s+/', '', (string) $inputs['phone']) ?? '';
            if (str_starts_with($phone, '0')) {
                $phone = substr($phone, 1);
            }
            $inputs['phone'] = $phone;
        }
        if (array_key_exists('country_code', $inputs)) {
            $inputs['phone_country_code'] = $inputs['country_code'];
            unset($inputs['country_code']);
        }

        $oldBirthDate = $user->birth_date;
        $newBirthDate = $inputs['birth_date'] ?? null;
        if ($newBirthDate && $oldBirthDate !== $newBirthDate) {
            $groupService = new \App\Services\GroupService();
            $oldAgeGroup = $groupService->getAgeGroup($user);
            $inputs['birth_date'] = app(TemporalService::class)
                ->parseDateParts(
                    (int) $inputs['birth_date'][0],
                    (int) $inputs['birth_date'][1],
                    (int) $inputs['birth_date'][2],
                )
                ->toCanonical();
            $user->update($inputs);
            $newAgeGroup = $groupService->getAgeGroup($user);
            if (!$oldAgeGroup || !$newAgeGroup || $oldAgeGroup->id !== $newAgeGroup->id) {
                $oldGroups = $user->groups()->where('group_type', 3)->get();
                foreach ($oldGroups as $group) $user->groups()->detach($group->id);
                foreach ($groupService->getLocationLevels($user) as $location) {
                    $group = $groupService->findOrCreateGroup('3', $location, null, null, $newAgeGroup->id);
                    $user->groups()->syncWithoutDetaching([$group->id]);
                    if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                }
                $globalGroup = \App\Models\Group::firstOrCreate([
                    'group_type' => 3, 'location_level' => 'global', 'address_id' => null, 'age_group_id' => $newAgeGroup->id,
                ], ['name' => "گروه سنی {$newAgeGroup->title} جهانی"]);
                $user->groups()->syncWithoutDetaching([$globalGroup->id]);
            }
        } else {
            $user->update($inputs);
        }
        $user->refresh();
        $identityAfter = [
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'birth_date' => (string) $user->getRawOriginal('birth_date'),
            'gender' => (string) $user->gender,
            'phone' => (string) $user->phone,
            'country_code' => (string) ($user->phone_country_code ?: '+98'),
        ];
        $identityChanged = $identityBefore !== $identityAfter;

        if ($user->first_name != null AND $user->last_name != null AND $user->gender != null AND $user->national_id != null AND $user->phone != null){
            $user->status = 1;
            if ($identityChanged && ! $user->hasUsedIdentityEdit()) {
                $user->identity_edit_used_at = now();
                $user->edited = 1;
            }
            $user->save();
        }
        app(ProfileCompletionService::class)->maybeAward($user);
        return back()->with('success', 'پروفایل با موفقیت ویرایش شد');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'لطفاً رمز فعلی را وارد کنید.',
            'new_password.required' => 'لطفاً رمز جدید را وارد کنید.',
            'new_password.min' => 'رمز جدید باید حداقل ۸ کاراکتر باشد.',
            'new_password.confirmed' => 'تکرار رمز عبور با رمز جدید مطابقت ندارد.',
        ]);
        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) return back()->withErrors(['current_password' => 'رمز فعلی اشتباه است.']);
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
        $currentOccupational = $user->specialties->pluck('id')->toArray();
        $newOccupational = $validated['occupational_fields'];
        $addedOccupational = array_diff($newOccupational, $currentOccupational);
        $removedOccupational = array_diff($currentOccupational, $newOccupational);
        $user->specialties()->sync($newOccupational);
        $groupService = new \App\Services\GroupService();
        foreach ($addedOccupational as $id) {
            $specialty = \App\Models\OccupationalField::find($id);
            $globalGroup = \App\Models\Group::firstOrCreate([
                'group_type' => '1','location_level' => 'global','address_id' => null,'specialty_id' => $specialty->id,
            ], ['name' => "اصناف {$specialty->name} جهانی"]);
            $groupService->addUserToGroup($user, $globalGroup);
            $locations = $groupService->getLocationLevels($user);
            foreach ($locations as $index => $location) {
                $group = $groupService->findOrCreateGroup('1', $location, $specialty->id);
                $groupService->addUserToGroup($user, $group);
                if ($index === array_key_last($locations)) $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
            }
        }
        foreach ($removedOccupational as $id) {
            $current = $id;
            while ($current !== null) {
                $groupIds = \App\Models\Group::where('group_type', 1)->where('specialty_id', $current)->pluck('id')->toArray();
                if (!empty($groupIds)) $user->groups()->detach($groupIds);
                $current = \App\Models\OccupationalField::find($current)?->parent_id;
            }
        }
        $currentExperience = $user->experiences->pluck('id')->toArray();
        $newExperience = $validated['experience_fields'];
        $addedExperience = array_diff($newExperience, $currentExperience);
        $removedExperience = array_diff($currentExperience, $newExperience);
        $user->experiences()->sync($newExperience);
        foreach ($addedExperience as $id) {
            $experience = \App\Models\ExperienceField::find($id);
            $globalGroup = \App\Models\Group::firstOrCreate([
                'group_type' => '2','location_level' => 'global','address_id' => null,'experience_id' => $experience->id,
            ], ['name' => "متخصصان {$experience->name} جهانی"]);
            $groupService->addUserToGroup($user, $globalGroup);
            $locations = $groupService->getLocationLevels($user);
            foreach ($locations as $index => $location) {
                $group = $groupService->findOrCreateGroup('2', $location, null, $experience->id);
                $groupService->addUserToGroup($user, $group);
                if ($index === array_key_last($locations)) $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
            }
        }
        foreach ($removedExperience as $id) {
            $current = $id;
            while ($current !== null) {
                $groupIds = \App\Models\Group::where('group_type', 2)->where('experience_id', $current)->pluck('id')->toArray();
                if (!empty($groupIds)) $user->groups()->detach($groupIds);
                $current = \App\Models\ExperienceField::find($current)?->parent_id;
            }
        }
        app(ProfileCompletionService::class)->maybeAward($user);
        return redirect()->route('profile.edit')->with('success', 'زمینه‌های صنفی و تجربی با موفقیت به‌روزرسانی شدند.');
    }

    public function updateSocialNetworks(Request $request)
    {
        $request->validate(['options' => 'nullable|array','options.*' => 'nullable|url']);
        $user = Auth::user();
        $cleanedLinks = array_filter($request->input('options', []));
        $user->update(['social_networks' => $cleanedLinks]);
        return back()->with('success', 'لینک‌های شبکه اجتماعی ذخیره شدند.');
    }

    public function updateAddress(Request $request)
    {
        $inputs = $request->validate([
            'continent_id' => 'required|exists:continents,id','country_id' => 'required|exists:countries,id',
            'province_id' => 'required|exists:provinces,id','county_id' => 'required|exists:counties,id',
            'section_id' => 'required|exists:districts,id','city_id' => 'required','region_id' => 'required',
            'neighborhood_id' => 'required|exists:neighborhoods,id','street_id' => 'nullable|exists:streets,id',
            'alley_id' => 'nullable|exists:alleies,id',
        ]);
        $user = Auth::user();
        $previousAddress = $user->address->replicate();
        if (str_starts_with($inputs['city_id'], 'rural_rural_')) {
            $inputs['rural_id'] = str_replace('rural_rural_', '', $inputs['city_id']);
            $inputs['city_id'] = null;
            $inputs['village_id'] = $inputs['region_id'];
        } elseif (str_starts_with($inputs['city_id'], 'city_city_')) {
            $inputs['city_id'] = str_replace('city_city_', '', $inputs['city_id']);
            $inputs['rural_id'] = null;
        }
        if(!isset($inputs['street_id'])) $inputs['street_id'] = null;
        if(!isset($inputs['alley_id'])) $inputs['alley_id'] = null;
        $user->address->update($inputs);
        $groupService = new GroupService();
        $oldLevels = $groupService->getLocationLevelsFromAddress($previousAddress);
        $oldGroupIds = Group::whereIn('location_level', collect($oldLevels)->pluck('level'))
            ->whereIn('address_id', collect($oldLevels)->pluck('id'))->pluck('id')->toArray();
        $allVoters = Vote::where('voter_id', $user->id)->get();
        foreach($allVoters as $vote) $vote->delete();
        $user->groups()->detach($oldGroupIds);
        $user->refresh();
        $user->load(['address', 'specialties', 'experiences']);
        $groupService->generateGroupsForUser($user);
        app(ProfileCompletionService::class)->maybeAward($user);
        return back()->with('success', 'مکان شما با موفقیت به‌روزرسانی شد.');
    }

    public function sendInvitation(Request $request)
    {
        $request->validate(['invite_email' => 'required|email']);
        $setting = Setting::find(1);
        $code = InvitationCode::create([
            'code' => Str::random(10),
            'user_id' => auth()->id(),
            'expire_at' => Carbon::now()->addHours(intval($setting->expire_invation_time ?? 72))
        ]);

        app(CommunicationDispatcher::class)->dispatchExternal(
            'auth.invitation_issued',
            ['type' => 'profile_invitation', 'id' => (string) $code->id],
            [['email' => (string) $request->invite_email, 'locale' => 'fa']],
            [
                'code' => (string) $code->code,
                'expire_at' => app(TemporalService::class)->dateTime(
                    $code->expire_at,
                    app(TemporalContextResolver::class)->forLocale('fa', (string) config('app.timezone', 'Asia/Tehran')),
                    'short',
                ),
            ],
            [
                'locale' => 'fa',
                'deduplication_key' => 'auth.profile_invitation:'.$code->id,
            ],
        );

        return back()->with('success', 'ایمیل دعوت برای ارسال در صف قرار گرفت.');
    }

    public function showNajmHodaProfile()
    {
        $email = (string) config('najm-hoda.group_assistant.bot_email', 'najm-hoda-bot@local.invalid');
        $najmHoda = User::where('email', $email)->first();
        if (! $najmHoda) $najmHoda = app(\App\Services\NajmHoda\NajmHodaGroupAssistantService::class)->ensureBotUser();
        return view('profile.najm-hoda', compact('najmHoda'));
    }

    public function showProfileMember(User $user)
    {
        $najmHodaEmail = (string) config('najm-hoda.group_assistant.bot_email', 'najm-hoda-bot@local.invalid');
        if ($user->isSystemIdentity() && $user->email === $najmHodaEmail) return redirect()->route('najm-hoda.profile');
        $chatRequests = ChatRequest::where('receiver_id', auth()->id())->where('status', 'pending')->with('sender')->latest()->get();
        $access = app(\App\Services\Groups\CurrentGroupAccessService::class);
        $currentGroups = $access->groupsFor($user);
        $viewerGroupIds = auth()->check() ? $access->idsFor(auth()->user()) : [];

        // Show pending location groups as non-navigable presentation shells.
        // Read-only: never synchronize another member's residence while viewing.
        if ((bool) config('location-governance.groups_enabled', false)) {
            $pendingService = app(\App\Services\Groups\PendingLocationGroupRequestService::class);
            $pendingRequests = $pendingService->readOpenForUser($user);
            $currentGroups = $pendingService->presentableCanonicalGroups($currentGroups, $pendingRequests)
                ->concat($pendingService->presentationGroups($pendingRequests))->values();
        }
        // Canonical dimensions are identified by dimension_key; legacy presentation
        // fields (specialty_id / experience_id) are not authoritative in Stage C.
        $generalGroups = $currentGroups->filter(fn ($group) => $group->dimension_key === 'public' || ($group->dimension_key === null && (int) $group->group_type === 0))->values();
        $specialityGroups = $currentGroups->filter(fn ($group) => $group->dimension_key === 'profession' || ($group->dimension_key === null && $group->specialty_id !== null && $group->experience_id === null))->values();
        $experienceGroups = $currentGroups->filter(fn ($group) => $group->dimension_key === 'specialty' || ($group->dimension_key === null && $group->specialty_id === null && $group->experience_id !== null))->values();
        $ageGroups = $currentGroups->filter(fn ($group) => $group->dimension_key === 'age' || ($group->dimension_key === null && (int) $group->group_type === 3))->values();
        $genderGroups = $currentGroups->filter(fn ($group) => $group->dimension_key === 'gender' || ($group->dimension_key === null && (int) $group->group_type === 4))->values();
        return view('profile.profile-member', compact('user','chatRequests','generalGroups','specialityGroups','experienceGroups','ageGroups','genderGroups','viewerGroupIds'));
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
