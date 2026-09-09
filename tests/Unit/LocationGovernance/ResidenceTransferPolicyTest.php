<?php

namespace Tests\Unit\LocationGovernance;

use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\LocationGovernance\ResidenceTransferPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidenceTransferPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_policy_allows_two_explicit_transfers_in_rolling_twelve_months(): void
    {
        $user = User::factory()->create();
        $at = CarbonImmutable::parse('2026-09-10 12:00:00');

        UserLocationRelationship::factory()->primaryResidence()->ended()->create([
            'user_id' => $user->id,
            'explicit_transfer' => true,
            'started_at' => $at->subMonths(8),
            'ended_at' => $at->subMonths(7),
        ]);

        $policy = app(ResidenceTransferPolicy::class);
        $this->assertSame(1, $policy->remainingExplicitTransfers($user, $at));

        UserLocationRelationship::factory()->primaryResidence()->ended()->create([
            'user_id' => $user->id,
            'explicit_transfer' => true,
            'started_at' => $at->subMonths(5),
            'ended_at' => $at->subMonths(4),
        ]);

        $this->assertSame(0, $policy->remainingExplicitTransfers($user, $at));
        $this->assertFalse($policy->allowsExplicitTransfer($user, $at));
    }

    public function test_transfer_older_than_rolling_twelve_month_window_does_not_consume_quota(): void
    {
        $user = User::factory()->create();
        $at = CarbonImmutable::parse('2026-09-10 12:00:00');

        UserLocationRelationship::factory()->primaryResidence()->ended()->create([
            'user_id' => $user->id,
            'explicit_transfer' => true,
            'started_at' => $at->subMonths(14),
            'ended_at' => $at->subMonths(13),
        ]);

        $this->assertSame(2, app(ResidenceTransferPolicy::class)->remainingExplicitTransfers($user, $at));
    }
}
