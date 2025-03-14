<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupUserSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        $groups = Group::all();

        foreach ($users as $user) {
            foreach ($groups as $group) {
                if ($group->type == 'محله' || $group->type == 'خیابان' || $group->type == 'کوچه') {
                    $user->groups()->attach($group->id, ['role' => 'active']);
                } else {
                    $user->groups()->attach($group->id, ['role' => 'observer']);
                }
            }
        }
    }
}