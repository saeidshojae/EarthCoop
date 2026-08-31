<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Services\MembershipEligibilityService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_active_terms_accepted_completed_member_is_eligible(): void
    {
        $this->mock(ProfileCompletionService::class, function ($mock) {
            $mock->shouldReceive('isComplete')->once()->andReturnTrue();
        });

        $user = User::factory()->create([
            'terms_accepted_at' => now(),
            'status' => 'active',
            'is_system' => false,
            'email_verified_at' => now(),
        ]);

        $this->assertTrue(app(MembershipEligibilityService::class)->isEligibleForInitialMembershipCredit($user));
    }

    public function test_unverified_member_is_not_eligible_even_with_completed_profile(): void
    {
        $this->mock(ProfileCompletionService::class, function ($mock) {
            $mock->shouldReceive('isComplete')->never();
        });

        $user = User::factory()->unverified()->create([
            'terms_accepted_at' => now(),
            'status' => 'active',
            'is_system' => false,
        ]);

        $this->assertFalse(app(MembershipEligibilityService::class)->isEligibleForInitialMembershipCredit($user));
    }

    public function test_member_without_terms_acceptance_is_not_eligible(): void
    {
        $this->mock(ProfileCompletionService::class, function ($mock) {
            $mock->shouldReceive('isComplete')->never();
        });

        $user = User::factory()->create([
            'terms_accepted_at' => null,
            'status' => 'active',
            'is_system' => false,
            'email_verified_at' => now(),
        ]);

        $this->assertFalse(app(MembershipEligibilityService::class)->isEligibleForInitialMembershipCredit($user));
    }

    public function test_inactive_or_system_identity_is_not_eligible(): void
    {
        $this->mock(ProfileCompletionService::class, function ($mock) {
            $mock->shouldReceive('isComplete')->never();
        });

        $inactive = User::factory()->create([
            'terms_accepted_at' => now(),
            'status' => 'inactive',
            'is_system' => false,
            'email_verified_at' => now(),
        ]);

        $system = User::factory()->create([
            'terms_accepted_at' => now(),
            'status' => 'active',
            'is_system' => true,
            'email_verified_at' => now(),
        ]);

        $service = app(MembershipEligibilityService::class);

        $this->assertFalse($service->isEligibleForInitialMembershipCredit($inactive));
        $this->assertFalse($service->isEligibleForInitialMembershipCredit($system));
    }
}
