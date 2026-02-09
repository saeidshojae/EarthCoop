<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use App\Models\Group;
use Carbon\Carbon;
use App\Models\AgeGroup;
use Morilog\Jalali\Jalalian;

class GroupService
{
    public function getAgeGroup(User $user): ?AgeGroup
    {
        if (!$user->birth_date) return null;
        
$formatted = Carbon::parse($user->birth_date)->format('Y-m-d');
$age = Carbon::parse($formatted)->age;

        return AgeGroup::where('min_age', '<=', $age)
                    ->where('max_age', '>=', $age)
                    ->first();
    }
    public function getLocationLevelsFromAddress(Address $address): array
    {
        $levels = [];
    
        $fields = [
            'continent_id', 'country_id', 'province_id', 'county_id', 'section_id',
            'city_id', 'rural_id', 'region_id', 'village_id', 'neighborhood_id', 'street_id', 'alley_id'
        ];
    
        foreach ($fields as $field) {
            if ($address->$field) {
                $base = str_replace('_id', '', $field);
                $levels[] = [
                    'level' => $base,
                    'id' => $address->$field,
                    'name' => optional($address->$base)->name,
                ];
            }
        }
    
        return $levels;
    }
    
    public function getGroupsForUser(User $user): array
{
    $groups = [];

    // آدرس‌ها به صورت سلسله‌مراتبی
    $locationLevels = $this->getLocationLevels($user);

    // گروه‌های عمومی
    foreach ($locationLevels as $location) {
        $groups[] = $this->findOrCreateGroup('0', $location);
    }

    // گروه‌های صنفی
    foreach ($user->specialties as $specialty) {
        foreach ($locationLevels as $location) {
            $groups[] = $this->findOrCreateGroup('1', $location, $specialty->id);
            if ($specialty->parent) {
                $groups[] = $this->findOrCreateGroup('1', $location, $specialty->parent->id);
            }
            if ($specialty->parent && $specialty->parent->parent) {
                $groups[] = $this->findOrCreateGroup('1', $location, $specialty->parent->parent->id);
            }
        }
    }

    // گروه‌های تجربی
    foreach ($user->experiences as $experience) {
        foreach ($locationLevels as $location) {
            $groups[] = $this->findOrCreateGroup('2', $location, null, $experience->id);
            if ($experience->parent) {
                $groups[] = $this->findOrCreateGroup('2', $location, null, $experience->parent->id);
            }
            if ($experience->parent && $experience->parent->parent) {
                $groups[] = $this->findOrCreateGroup('2', $location, null, $experience->parent->parent->id);
            }
        }
    }

    // گروه سنی
    $ageGroup = $this->getAgeGroup($user);
    if ($ageGroup) {
        foreach ($locationLevels as $location) {
            $groups[] = $this->findOrCreateGroup('3', $location, null, null, $ageGroup->id);
        }
    }

    // گروه جنسیتی
    if ($user->gender) {
        foreach ($locationLevels as $location) {
            $groups[] = $this->findOrCreateGroup('4', $location, null, null, null, $user->gender);
        }
    }

    // حذف تکراری‌ها (بر اساس ID)
    $uniqueGroups = collect($groups)->unique('id')->values()->all();

    return $uniqueGroups;
}


    public function generateGroupsForUser(User $user)
    {
        $locationLevels = $this->getLocationLevels($user);

        // 🔹 گروه مجمع عمومی جهانی
        $globalGeneralGroup = Group::firstOrCreate([
            'group_type' => '0',
            'location_level' => 'global',
            'address_id' => null,
        ], [
            'name' => 'مجمع عمومی جهانی',
        ]);
        $this->addUserToGroup($user, $globalGeneralGroup);
        
        // 🔹 گروه‌های تخصصی شغلی (جهانی)
        foreach ($user->specialties as $specialty) {
            $group = Group::firstOrCreate([
                'group_type' => '1',
                'location_level' => 'global',
                'address_id' => null,
                'specialty_id' => $specialty->id,
            ], [
                'name' => "مجمع صنفی فعالان {$specialty->name} جهانی",
            ]);
            $this->addUserToGroup($user, $group);

            $group = Group::firstOrCreate([
                'group_type' => '1',
                'location_level' => 'global',
                'address_id' => null,
                'specialty_id' => $specialty->parent_id == null ? $specialty->id : $specialty->parent_id,
            ], [
'name' => "مجمع صنفی فعالان " . ($specialty->parent_id === null ? $specialty->name : $specialty->parent->name) . " جهانی",
            ]);
            $this->addUserToGroup($user, $group);
            
$effectiveSpecialty = $specialty->parent->parent ?? $specialty->parent ?? $specialty;

$group = Group::firstOrCreate([
    'group_type' => '1',
    'location_level' => 'global',
    'address_id' => null,
    'specialty_id' => $effectiveSpecialty->id,
], [
    'name' => "مجمع صنفی فعالان {$effectiveSpecialty->name} جهانی",
]);

$this->addUserToGroup($user, $group);

        }
        
        // 🔹 گروه‌های تخصصی علمی/تجربی (جهانی)
        foreach ($user->experiences as $experience) {
            $group = Group::firstOrCreate([
                'group_type' => '2',
                'location_level' => 'global',
                'address_id' => null,
                'experience_id' => $experience->id,
            ], [
                'name' => "مجمع متخصصان {$experience->name} جهانی",
            ]);
            $this->addUserToGroup($user, $group);
            if($experience->parent){
                $group = Group::firstOrCreate([
                    'group_type' => '2',
                    'location_level' => 'global',
                    'address_id' => null,
                    'experience_id' => $experience->parent->id,
                ], [
                    'name' => "مجمع متخصصان {$experience->parent->name} جهانی",
                ]);
                $this->addUserToGroup($user, $group);
            }
            
            if($experience->parent AND $experience->parent->parent){
                 $group = Group::firstOrCreate([
                    'group_type' => '2',
                    'location_level' => 'global',
                    'address_id' => null,
                    'experience_id' => $experience->parent->parent->id,
                ], [
                    'name' => "مجمع متخصصان {$experience->parent->parent->name} جهانی",
                ]);
                $this->addUserToGroup($user, $group);   
            }
        }
        
        // 🔹 گروه سنی جهانی
        $ageGroup = $this->getAgeGroup($user);
        if ($ageGroup) {
            $group = Group::firstOrCreate([
                'group_type' => '3',
                'location_level' => 'global',
                'address_id' => null,
                'age_group_id' => $ageGroup->id,
            ], [
                'name' => "مجمع {$ageGroup->title} جهانی",
            ]);
            $this->addUserToGroup($user, $group);
        }
        
        // 🔹 گروه جنسیتی جهانی
        if ($user->gender) {
            // جهانی
            $genderLabel = $user->gender === 'male' ? 'آقایان' : ($user->gender === 'female' ? 'زنان' : 'دیگران');
                
            $group = Group::firstOrCreate([
                'group_type' => '4',
                'location_level' => 'global',
                'address_id' => null,
                'gender' => $user->gender,
            ], [
                'name' => "گروه {$genderLabel} جهانی",
            ]);
            $this->addUserToGroup($user, $group);

            // برای همه سطوح مکانی
            foreach ($locationLevels as $location) {
                $group = $this->findOrCreateGroup('4', $location, null, null, null, $user->gender);
                $this->addUserToGroup($user, $group);

if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) {
    $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
}
            }
        }
        
        // 🔹 گروه‌های عمومی
        foreach ($locationLevels as $location) {
            $group = $this->findOrCreateGroup('0', $location);
            $this->addUserToGroup($user, $group);

if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) {
    $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
}
        }

        // 🔹 گروه‌های تخصصی شغلی (specialties)
        foreach ($user->specialties as $specialty) {
            foreach ($locationLevels as $location) {
                $group = $this->findOrCreateGroup('1', $location, $specialty->id);
                $this->addUserToGroup($user, $group);

$level1Specialty = $specialty->parent ?? $specialty;
$group2 = $this->findOrCreateGroup('1', $location, $level1Specialty->id);
$this->addUserToGroup($user, $group2);

$level2Specialty = $specialty->parent->parent ?? $specialty->parent ?? $specialty;
$group3 = $this->findOrCreateGroup('1', $location, $level2Specialty->id);
$this->addUserToGroup($user, $group3);

                if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) {
                    $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                    $user->groups()->updateExistingPivot($group2->id, ['role' => 1], false);
                    $user->groups()->updateExistingPivot($group3->id, ['role' => 1], false);
                }   
            }

            
        }

        // 🔹 گروه‌های تخصصی علمی/تجربی (experiences)
        foreach ($user->experiences as $experience) {
            foreach ($locationLevels as $location) {
                $group = $this->findOrCreateGroup('2', $location, null, $experience->id);
                $this->addUserToGroup($user, $group);
                    
                if($experience->parent){
                    $group2 = $this->findOrCreateGroup('2', $location, null, $experience->parent->id);
                    $this->addUserToGroup($user, $group2);   
                }
                
                if($experience->parent AND $experience->parent->parent){
                    $group3 = $this->findOrCreateGroup('2', $location, null,  $experience->parent->parent->id);
                    $this->addUserToGroup($user, $group3);   
                }

                if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) {
                    $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                    $user->groups()->updateExistingPivot($group2->id, ['role' => 1], false);
                    $user->groups()->updateExistingPivot($group3->id, ['role' => 1], false);
                }
            }
        }

        // 🔹 گروه سنی و جنسیتی
        $ageGroup = $this->getAgeGroup($user);

        foreach ($locationLevels as $location) {
            if ($ageGroup) {
                foreach ($locationLevels as $location) {
                    $group = $this->findOrCreateGroup('3', $location, null, null, $ageGroup->id); // 3 = گروه سنی
                    $this->addUserToGroup($user, $group);

                    if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) {
                        $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                    }
                }
            }

            if ($user->gender) {
                $group = $this->findOrCreateGroup('4', $location, null, null, null, $user->gender);
                $this->addUserToGroup($user, $group);

                if (in_array($location['level'], ['alley', 'street', 'neighborhood'])) {
                    $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
                }
            }
        }
    }

    private function isLastLocationLevel(array $location, array $allLocations): bool
    {
        return $location === end($allLocations);
    }

    public function getLocationLevels(User $user): array
    {
        $levels = [];
        $address = $user->address;

        if (!$address) return $levels;

        $fields = [
            'continent_id' => 'قاره',
            'country_id' => 'کشور',
            'province_id' => 'استان',
            'county_id' => 'شهرستان',
            'section_id' => 'بخش',
            'city_id' => 'شهر',
            'region_id' => 'منطقه',
            'neighborhood_id' => 'محله',
            'street_id' => 'خیابان',
            'alley_id' => 'کوچه',
        ];

        foreach ($fields as $key => $label) {
            if($key == 'city_id' AND $address->city_id == null){
                $key = 'rural_id';
            }

            if($key == 'region_id' AND $address->region_id == null){
                $key = 'village_id';
            }

            $base = str_replace('_id', '', $key);


            if ($address->$key) {
                $levels[] = [
                    'level' => $base,
                    'id' => $address->$key,
                    'name' => optional($address->$base)->name,
                ];
            }
        }

        return $levels;
    }


    public function findOrCreateGroup(
        string $type,
        array $location,
        $specialtyId = null,
        $experienceId = null,
        $ageGroupId = null,
        $gender = null
    ): Group {
        $group = Group::where('group_type', $type)
            ->where('location_level', $location['level'])
            ->where('address_id', $location['id'])
            ->when($specialtyId, fn($q) => $q->where('specialty_id', $specialtyId))
            ->when($experienceId, fn($q) => $q->where('experience_id', $experienceId))
            ->when($ageGroupId, fn($q) => $q->where('age_group_id', $ageGroupId))
            ->when($gender, fn($q) => $q->where('gender', $gender))
            ->first();
    
        if (!$group) {
            $locationTitle = match ($location['level']) {
                'continent' => 'قاره',
                'country' => 'کشور',
                'province' => 'استان',
                'county' => 'شهرستان',
                'section' => 'بخش',
                'region' => 'منطقه',
                'city' => 'شهر',
                'rural' => 'دهستان',
                'village' => 'روستا',
                'neighborhood' => 'محله',
                'street' => 'خیابان',
                'alley' => 'کوچه',
                default => 'مکان'
            };
            
            // تولید نام گروه بر اساس نوع
            $name = match ($type) {
                '0' => "مجمع عمومی {$locationTitle} {$location['name']}",
                '1' => "مجمع صنفی فعالان {$locationTitle} {$location['name']}",
                '2' => "مجمع متخصصان {$locationTitle} {$location['name']}",
                '3' => "مجمع {$locationTitle} {$location['name']}",
                '4' => "گروه جنسیتی {$locationTitle} {$location['name']}",
                default => "گروه {$locationTitle} {$location['name']}",
            };
    
            if ($specialtyId) {
                $specialty = \App\Models\OccupationalField::find($specialtyId);
                $name = "مجمع صنفی فعالان {$specialty->name} در {$locationTitle} {$location['name']}";
            }
    
            if ($experienceId) {
                $experience = \App\Models\ExperienceField::find($experienceId);
                $name = "مجمع متخصصان {$experience->name} در {$locationTitle} {$location['name']}";
            }
    
            if ($ageGroupId) {
                $ageGroup = \App\Models\AgeGroup::find($ageGroupId);
                $name = "مجمع {$ageGroup->title} {$locationTitle} {$location['name']}";
            }
    
            if ($gender) {
                $genderLabel = $gender === 'male' ? 'آقایان' : ($gender === 'female' ? 'بانوان' : 'دیگران');
                $name = "گروه {$genderLabel} {$locationTitle} {$location['name']}";
            }
    
            $group = Group::create([
                'name' => $name,
                'group_type' => $type,
                'location_level' => $location['level'],
                'address_id' => $location['id'],
                'specialty_id' => $specialtyId,
                'experience_id' => $experienceId,
                'age_group_id' => $ageGroupId,
                'gender' => $gender,
            ]);
        }
    
        return $group;
    }
    

    public function addUserToGroup(User $user, Group $group): void
    {
        $user->groups()->syncWithoutDetaching([$group->id]);
    }
    

    private function getLevelTitle(string $level): string
    {
        return match ($level) {
            'continent' => 'قاره',
            'country' => 'کشور',
            'province' => 'استان',
            'county' => 'شهرستان',
            'section' => 'بخش',
            'region' => 'منطقه',
            'city' => 'شهر',
            'rural' => 'دهستان',
            'village' => 'روستا',
            'neighborhood' => 'محله',
            'street' => 'خیابان',
            'alley' => 'کوچه',
            default => 'منطقه',
        };
    }
}
