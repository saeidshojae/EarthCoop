<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupSessionParticipationRequest;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\GroupChat\GroupSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionParticipationLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_temporary_participation_expires_when_meeting_ends_and_does_not_leak_to_next_meeting(): void
    {
        config(['broadcasting.default' => 'null']);

        $group = Group::create([
            'name' => 'Session participation lifecycle ' . uniqid('', true),
            'is_open' => false,
        ]);

        $manager = User::create([
            'email' => uniqid('session-manager-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Session',
            'last_name' => 'Manager',
            'is_system' => false,
        ]);

        $member = User::create([
            'email' => uniqid('session-member-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Session',
            'last_name' => 'Member',
            'is_system' => false,
        ]);

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
        ]);

        $membership = GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 1,
            'status' => 1,
            'session_write_allowed' => true,
        ]);

        $first = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست اول',
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'started_at' => now()->subHour(),
        ]);

        $request = GroupSessionParticipationRequest::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'status' => 'pending',
            'message' => 'درخواست مشارکت آزمایشی',
        ]);

        $service = app(GroupSessionService::class);
        $ended = $service->end($group, (int) $manager->id);

        $this->assertSame($first->id, $ended?->id);
        $this->assertFalse((bool) $membership->fresh()->session_write_allowed);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->resolved_at);
        $this->assertSame($manager->id, (int) $request->fresh()->resolved_by);
        $this->assertTrue((bool) $group->fresh()->is_open);

        $second = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست دوم',
            'status' => 'scheduled',
            'starts_at' => now(),
        ]);

        $started = $service->start($second, (int) $manager->id);

        $this->assertSame('active', $started->status);
        $this->assertFalse((bool) $group->fresh()->is_open);
        $this->assertFalse((bool) $membership->fresh()->session_write_allowed);
    }
}
