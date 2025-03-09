<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use Carbon\Carbon;

class GroupService
{
    public static function assignGroups(User $user)
    {
        // پاکسازی گروه‌های قبلی (در صورت نیاز)
        $user->groups()->detach();
        
        self::assignGeneralGroups($user);
        self::assignSpecializedGroups($user);
        self::assignExclusiveGroups($user);
    }
    
    // اختصاص گروه‌های عمومی بر اساس هر سطح مکانی
    private static function assignGeneralGroups(User $user)
    {
        $locations = $user->locations;
        foreach($locations as $location) {
            $groupName = "گروه عمومی " . $location->name;
            $group = Group::firstOrCreate([
                'group_type'  => 'general',
                'name'        => $groupName,
                'location_id' => $location->id,
            ]);
            $user->groups()->attach($group->id);
        }
        // اضافه کردن گروه جهانی
        $globalGroup = Group::firstOrCreate([
            'group_type' => 'general',
            'name'       => "گروه عمومی جهانی",
        ]);
        $user->groups()->attach($globalGroup->id);
    }
    
    // اختصاص گروه‌های تخصصی بر اساس فعالیت صنفی و تجربی
    private static function assignSpecializedGroups(User $user)
    {
        // گروه‌های تخصصی بر اساس زمینه صنفی
        foreach($user->occupationalFields as $occupation) {
            foreach($user->locations as $location) {
                $groupName = "گروه تخصصی " . $occupation->name . " در " . $location->name;
                $group = Group::firstOrCreate([
                    'group_type'  => 'specialized',
                    'name'        => $groupName,
                    'category'    => $occupation->name,
                    'location_id' => $location->id,
                ]);
                $user->groups()->attach($group->id);
            }
            $globalOccupationGroup = Group::firstOrCreate([
                'group_type' => 'specialized',
                'name'       => "گروه تخصصی " . $occupation->name . " جهانی",
                'category'   => $occupation->name,
            ]);
            $user->groups()->attach($globalOccupationGroup->id);
        }
        
        // گروه‌های تخصصی بر اساس زمینه تجربی/تخصص
        foreach($user->experienceFields as $experience) {
            foreach($user->locations as $location) {
                $groupName = "گروه تخصصی " . $experience->name . " در " . $location->name;
                $group = Group::firstOrCreate([
                    'group_type'  => 'specialized',
                    'name'        => $groupName,
                    'category'    => $experience->name,
                    'location_id' => $location->id,
                ]);
                $user->groups()->attach($group->id);
            }
            $globalExperienceGroup = Group::firstOrCreate([
                'group_type' => 'specialized',
                'name'       => "گروه تخصصی " . $experience->name . " جهانی",
                'category'   => $experience->name,
            ]);
            $user->groups()->attach($globalExperienceGroup->id);
        }
    }
    
    // اختصاص گروه‌های اختصاصی بر اساس رده سنی و جنسیت
    private static function assignExclusiveGroups(User $user)
    {
        $age = Carbon::parse($user->birth_date)->age;
        $ageGroup = self::getAgeGroup($age);
        
        foreach($user->locations as $location) {
            $groupName = "گروه اختصاصی " . $ageGroup . " در " . $location->name;
            $group = Group::firstOrCreate([
                'group_type'  => 'exclusive',
                'name'        => $groupName,
                'category'    => $ageGroup,
                'location_id' => $location->id,
            ]);
            $user->groups()->attach($group->id);
        }
        $globalAgeGroup = Group::firstOrCreate([
            'group_type' => 'exclusive',
            'name'       => "گروه اختصاصی " . $ageGroup . " جهانی",
            'category'   => $ageGroup,
        ]);
        $user->groups()->attach($globalAgeGroup->id);
        
        $genderGroup = $user->gender == 'female' ? 'بانوان' : 'آقایان';
        foreach($user->locations as $location) {
            $groupName = "گروه اختصاصی " . $genderGroup . " در " . $location->name;
            $group = Group::firstOrCreate([
                'group_type'  => 'exclusive',
                'name'        => $groupName,
                'category'    => $genderGroup,
                'location_id' => $location->id,
            ]);
            $user->groups()->attach($group->id);
        }
        $globalGenderGroup = Group::firstOrCreate([
            'group_type' => 'exclusive',
            'name'       => "گروه اختصاصی " . $genderGroup . " جهانی",
            'category'   => $genderGroup,
        ]);
        $user->groups()->attach($globalGenderGroup->id);
    }
    
    // تابع نمونه برای تعیین گروه سنی از روی سن
    private static function getAgeGroup($age)
    {
        if ($age < 30) {
            return "جوانان";
        } elseif($age < 60) {
            return "میانسالان";
        } else {
            return "سنیین";
        }
    }
}
