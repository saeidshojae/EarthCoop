<?php

namespace App\Services;

use App\Models\Address;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\User;
use App\Services\Groups\GovernanceScopedGroupService;
use App\Services\Membership\MembershipEngine;
use Carbon\Carbon;

class GroupService
{
    public function __construct(
        private readonly ?MembershipEngine $membershipEngine = null,
        private readonly ?GovernanceScopedGroupService $governanceScopedGroupService = null,
    ) {
    }

    public function getAgeGroup(User $user): ?AgeGroup
    {
        if (!$user->birth_date) {
            return null;
        }

        $age = Carbon::parse($user->birth_date)->age;

        return AgeGroup::query()
            ->where('min_age', '<=', $age)
            ->where('max_age', '>=', $age)
            ->first();
    }

    public function getLocationLevelsFromAddress(Address $address): array
    {
        $levels = [];
        $fields = [
            'continent_id', 'country_id', 'province_id', 'county_id', 'section_id',
            'city_id', 'rural_id', 'region_id', 'village_id', 'neighborhood_id', 'street_id', 'alley_id',
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
        if (config('location-governance.groups_enabled', false)) {
            return $this->canonicalGroupsForUser($user);
        }

        $groups = [];
        $locationLevels = $this->getLocationLevels($user);

        foreach ($locationLevels as $location) {
            $groups[] = $this->findOrCreateGroup('0', $location);
        }

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

        $ageGroup = $this->getAgeGroup($user);
        if ($ageGroup) {
            foreach ($locationLevels as $location) {
                $groups[] = $this->findOrCreateGroup('3', $location, null, null, $ageGroup->id);
            }
        }

        if ($user->gender) {
            foreach ($locationLevels as $location) {
                $groups[] = $this->findOrCreateGroup('4', $location, null, null, null, $user->gender);
            }
        }

        return collect($groups)->unique('id')->values()->all();
    }

    public function generateGroupsForUser(User $user): void
    {
        if (config('location-governance.groups_enabled', false)) {
            $this->canonicalGroupsForUser($user);
            return;
        }

        $locationLevels = $this->getLocationLevels($user);

        $globalGeneralGroup = Group::firstOrCreate([
            'group_type' => '0',
            'location_level' => 'global',
            'address_id' => null,
        ], ['name' => 'مجمع عمومی جهانی']);
        $this->addUserToGroup($user, $globalGeneralGroup);

        foreach ($user->specialties as $specialty) {
            foreach (collect([$specialty, $specialty->parent, $specialty->parent?->parent])->filter()->unique('id') as $field) {
                $group = Group::firstOrCreate([
                    'group_type' => '1',
                    'location_level' => 'global',
                    'address_id' => null,
                    'specialty_id' => $field->id,
                ], ['name' => "مجمع صنفی فعالان {$field->name} جهانی"]);
                $this->addUserToGroup($user, $group);
            }
        }

        foreach ($user->experiences as $experience) {
            foreach (collect([$experience, $experience->parent, $experience->parent?->parent])->filter()->unique('id') as $field) {
                $group = Group::firstOrCreate([
                    'group_type' => '2',
                    'location_level' => 'global',
                    'address_id' => null,
                    'experience_id' => $field->id,
                ], ['name' => "مجمع متخصصان {$field->name} جهانی"]);
                $this->addUserToGroup($user, $group);
            }
        }

        $ageGroup = $this->getAgeGroup($user);
        if ($ageGroup) {
            $group = Group::firstOrCreate([
                'group_type' => '3',
                'location_level' => 'global',
                'address_id' => null,
                'age_group_id' => $ageGroup->id,
            ], ['name' => "مجمع {$ageGroup->title} جهانی"]);
            $this->addUserToGroup($user, $group);
        }

        if ($user->gender) {
            $genderLabel = $user->gender === 'male' ? 'آقایان' : ($user->gender === 'female' ? 'زنان' : 'دیگران');
            $group = Group::firstOrCreate([
                'group_type' => '4',
                'location_level' => 'global',
                'address_id' => null,
                'gender' => $user->gender,
            ], ['name' => "گروه {$genderLabel} جهانی"]);
            $this->addUserToGroup($user, $group);
        }

        foreach ($locationLevels as $location) {
            $public = $this->findOrCreateGroup('0', $location);
            $this->addUserToGroup($user, $public);
            $this->promoteLocalMember($user, $public, $location);

            foreach ($user->specialties as $specialty) {
                foreach (collect([$specialty, $specialty->parent, $specialty->parent?->parent])->filter()->unique('id') as $field) {
                    $group = $this->findOrCreateGroup('1', $location, $field->id);
                    $this->addUserToGroup($user, $group);
                    $this->promoteLocalMember($user, $group, $location);
                }
            }

            foreach ($user->experiences as $experience) {
                foreach (collect([$experience, $experience->parent, $experience->parent?->parent])->filter()->unique('id') as $field) {
                    $group = $this->findOrCreateGroup('2', $location, null, $field->id);
                    $this->addUserToGroup($user, $group);
                    $this->promoteLocalMember($user, $group, $location);
                }
            }

            if ($ageGroup) {
                $group = $this->findOrCreateGroup('3', $location, null, null, $ageGroup->id);
                $this->addUserToGroup($user, $group);
                $this->promoteLocalMember($user, $group, $location);
            }

            if ($user->gender) {
                $group = $this->findOrCreateGroup('4', $location, null, null, null, $user->gender);
                $this->addUserToGroup($user, $group);
                $this->promoteLocalMember($user, $group, $location);
            }
        }
    }

    public function getLocationLevels(User $user): array
    {
        $address = $user->address;
        if (!$address) {
            return [];
        }

        $levels = [];
        $fields = [
            'continent_id', 'country_id', 'province_id', 'county_id', 'section_id',
            'city_id', 'region_id', 'neighborhood_id', 'street_id', 'alley_id',
        ];

        foreach ($fields as $field) {
            if ($field === 'city_id' && $address->city_id === null) {
                $field = 'rural_id';
            }
            if ($field === 'region_id' && $address->region_id === null) {
                $field = 'village_id';
            }

            $base = str_replace('_id', '', $field);
            if ($address->$field) {
                $levels[] = [
                    'level' => $base,
                    'id' => $address->$field,
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
        $query = Group::query()
            ->where('group_type', $type)
            ->where('location_level', $location['level'])
            ->where('address_id', $location['id'])
            ->when($specialtyId, fn ($q) => $q->where('specialty_id', $specialtyId))
            ->when($experienceId, fn ($q) => $q->where('experience_id', $experienceId))
            ->when($ageGroupId, fn ($q) => $q->where('age_group_id', $ageGroupId))
            ->when($gender, fn ($q) => $q->where('gender', $gender));

        $group = $query->first();
        if ($group) {
            return $group;
        }

        $locationTitle = $this->getLevelTitle($location['level']);
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
            $ageGroup = AgeGroup::find($ageGroupId);
            $name = "مجمع {$ageGroup->title} {$locationTitle} {$location['name']}";
        }
        if ($gender) {
            $genderLabel = $gender === 'male' ? 'آقایان' : ($gender === 'female' ? 'بانوان' : 'دیگران');
            $name = "گروه {$genderLabel} {$locationTitle} {$location['name']}";
        }

        return Group::create([
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

    public function addUserToGroup(User $user, Group $group): void
    {
        $user->groups()->syncWithoutDetaching([$group->id]);
    }

    private function canonicalGroupsForUser(User $user): array
    {
        $engine = $this->membershipEngine ?? app(MembershipEngine::class);
        $materializer = $this->governanceScopedGroupService ?? app(GovernanceScopedGroupService::class);
        $resolution = $engine->resolve($user, true);
        $groups = [];

        foreach ($resolution->materializableIntents as $intent) {
            $group = $materializer->materialize($intent);
            if ($group !== null) {
                $this->addUserToGroup($user, $group);
                $groups[] = $group;
            }
        }

        return collect($groups)->unique('id')->values()->all();
    }

    private function promoteLocalMember(User $user, Group $group, array $location): void
    {
        if (in_array($location['level'], ['alley', 'street', 'neighborhood'], true)) {
            $user->groups()->updateExistingPivot($group->id, ['role' => 1], false);
        }
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
