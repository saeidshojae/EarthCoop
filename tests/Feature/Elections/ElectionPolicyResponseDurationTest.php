<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionTallyResult;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionPolicyVersionService;
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use App\Services\Elections\ElectionResponsibilityOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionPolicyResponseDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_responsibility_offer_deadline_uses_frozen_cycle_policy(): void
    {
        $managerV1 = $this->publishManagerContract('manager contract v1');

        $setting = GroupSetting::create([
            'level' => 'global', 'manager_count' => 1, 'inspector_count' => 0,
            'election_time' => 10, 'max_for_election' => 1, 'election_status' => 1,
            'second_election_time' => 3,
        ]);
        $policy = app(ElectionPolicyVersionService::class)->publishFromSetting(
            $setting, null, 'response_window_test', now(), 11
        );

        $this->assertSame($managerV1->id, (int) $policy->manager_contract_version_id);

        $group = Group::create([
            'name' => 'Response policy group', 'group_type' => '0',
            'location_level' => 'global', 'address_id' => null,
        ]);
        $candidate = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id, 'user_id' => $candidate->id, 'role' => 1, 'status' => 1,
        ]);

        $election = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'policy_version_id' => $policy->id,
            'starts_at' => now()->subDays(12),
            'ends_at' => now()->subDays(2),
            'is_closed' => true,
            'lifecycle_status' => 'tallying',
        ]);

        ElectionTallyResult::create([
            'election_id' => $election->id,
            'candidate_user_id' => $candidate->id,
            'position' => 'manager',
            'vote_count' => 5,
            'rank' => 1,
            'within_seat_cutoff' => true,
            'cycle_identifier' => 'response-policy-cycle',
            'stopped_at' => now()->subDays(2),
            'vote_snapshot_hash' => str_repeat('a', 64),
            'draw_seed_version' => 'seed-v1',
            'draw_seed' => str_repeat('b', 64),
            'tie_break_version' => 'tie-v1',
            'tie_break_key' => str_repeat('c', 64),
            'tallied_at' => now()->subDay(),
        ]);

        app(ElectionResponsibilityOfferService::class)->start($election);
        $offer = $election->responsibilityOffers()->where('position', 'manager')->firstOrFail();

        $this->assertSame(11, (int) $offer->offered_at->startOfDay()->diffInDays($offer->expires_at->copy()->startOfDay()));
        $this->assertSame(11, (int) ($offer->response_metadata['response_duration_days'] ?? 0));
        $this->assertSame($policy->id, (int) ($offer->response_metadata['policy_version_id'] ?? 0));
        $this->assertSame($managerV1->id, (int) $offer->contract_version_id);
        $this->assertTrue((bool) ($offer->response_metadata['contract_frozen_by_policy'] ?? false));
    }

    public function test_later_contract_publication_does_not_change_contract_for_existing_cycle(): void
    {
        $managerV1 = $this->publishManagerContract('manager contract v1');

        $setting = GroupSetting::create([
            'level' => 'global', 'manager_count' => 1, 'inspector_count' => 0,
            'election_time' => 10, 'max_for_election' => 1, 'election_status' => 1,
            'second_election_time' => 3,
        ]);
        $policy = app(ElectionPolicyVersionService::class)->publishFromSetting(
            $setting, null, 'contract_freeze_test', now(), 7
        );

        $managerV2 = $this->publishManagerContract('manager contract v2');

        $group = Group::create([
            'name' => 'Contract freeze group', 'group_type' => '0',
            'location_level' => 'global', 'address_id' => null,
        ]);
        $candidate = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id, 'user_id' => $candidate->id, 'role' => 1, 'status' => 1,
        ]);

        $election = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'policy_version_id' => $policy->id,
            'starts_at' => now()->subDays(12),
            'ends_at' => now()->subDays(2),
            'is_closed' => true,
            'lifecycle_status' => 'tallying',
        ]);

        ElectionTallyResult::create([
            'election_id' => $election->id,
            'candidate_user_id' => $candidate->id,
            'position' => 'manager',
            'vote_count' => 5,
            'rank' => 1,
            'within_seat_cutoff' => true,
            'cycle_identifier' => 'contract-freeze-cycle',
            'stopped_at' => now()->subDays(2),
            'vote_snapshot_hash' => str_repeat('d', 64),
            'draw_seed_version' => 'seed-v1',
            'draw_seed' => str_repeat('e', 64),
            'tie_break_version' => 'tie-v1',
            'tie_break_key' => str_repeat('f', 64),
            'tallied_at' => now()->subDay(),
        ]);

        app(ElectionResponsibilityOfferService::class)->start($election);
        $offer = $election->responsibilityOffers()->where('position', 'manager')->firstOrFail();

        $this->assertSame($managerV1->id, (int) $policy->manager_contract_version_id);
        $this->assertSame($managerV1->id, (int) $offer->contract_version_id);
        $this->assertNotSame($managerV2->id, (int) $offer->contract_version_id);
    }

    private function publishManagerContract(string $text): ElectionResponsibilityContractVersion
    {
        $actor = User::factory()->create();
        $clauses = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, $text);

        return app(ElectionResponsibilityContractVersionService::class)
            ->publish('manager', $clauses, $actor, $text);
    }
}
