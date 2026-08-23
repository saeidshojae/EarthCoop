<?php

namespace Tests\Feature\Elections;

use App\Events\Elections\ElectionAppointmentRevoked;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionRepresentationAssignment;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionAppointmentService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionAppointmentRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoking_direct_appointment_recursively_revokes_inherited_chain_and_ends_representation(): void
    {
        $user = User::factory()->create();
        $source = $this->group('street', 8801);
        $inheritedGroup = $this->group('neighborhood', 8802);
        $represented = $this->group('region', 8803);

        foreach ([$source, $inheritedGroup, $represented] as $group) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $group->id === $represented->id ? 1 : 2,
                'status' => 1,
            ]);
        }

        $election = Election::create([
            'group_id' => $source->id,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'is_closed' => true,
            'lifecycle_status' => 'filled',
        ]);
        $contract = ElectionResponsibilityContractVersion::create([
            'position' => 'manager', 'version' => 1, 'body' => 'manager contract',
            'is_active' => true, 'published_at' => now()->subDay(),
        ]);
        $offer = ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $user->id,
            'position' => 'manager', 'ranking_position' => 1,
            'contract_version_id' => $contract->id,
            'status' => 'accepted',
            'offered_at' => now()->subDays(2), 'expires_at' => now()->addDays(5),
            'responded_at' => now()->subDay(), 'eligibility_checked_at' => now()->subDay(),
        ]);

        $direct = ElectionAppointment::create([
            'election_id' => $election->id,
            'responsibility_offer_id' => $offer->id,
            'user_id' => $user->id,
            'group_id' => $source->id,
            'position' => 'manager', 'group_role' => 2,
            'appointment_kind' => 'direct', 'status' => 'active',
            'appointed_at' => now()->subDay(),
        ]);
        $inherited = ElectionAppointment::create([
            'election_id' => $election->id,
            'responsibility_offer_id' => $offer->id,
            'user_id' => $user->id,
            'group_id' => $inheritedGroup->id,
            'position' => 'manager', 'group_role' => 2,
            'appointment_kind' => 'inherited', 'source_appointment_id' => $direct->id,
            'status' => 'active', 'appointed_at' => now()->subDay(),
        ]);
        $representation = ElectionRepresentationAssignment::create([
            'appointment_id' => $inherited->id,
            'user_id' => $user->id,
            'source_group_id' => $inheritedGroup->id,
            'represented_group_id' => $represented->id,
            'status' => 'active', 'activated_at' => now()->subDay(),
        ]);

        $revoked = app(ElectionAppointmentService::class)->revoke($direct, 'membership_scope_changed', 'test_operator');

        $this->assertSame('revoked', $revoked->status);
        $this->assertSame('test_operator', $revoked->actor);
        $this->assertSame('membership_scope_changed', $revoked->reason);
        $this->assertSame('revoked', $inherited->refresh()->status);
        $this->assertStringContainsString('source_appointment_revoked', (string) $inherited->reason);
        $this->assertSame('ended', $representation->refresh()->status);
        $this->assertSame(1, (int) GroupUser::where('group_id', $source->id)->where('user_id', $user->id)->value('role'));
        $this->assertSame(1, (int) GroupUser::where('group_id', $inheritedGroup->id)->where('user_id', $user->id)->value('role'));

        // Retry is idempotent.
        app(ElectionAppointmentService::class)->revoke($direct, 'ignored_retry_reason', 'retry');
        $this->assertSame('membership_scope_changed', $direct->refresh()->reason);
    }

    public function test_revocation_event_is_dispatched_only_after_commit(): void
    {
        $this->assertTrue(is_subclass_of(ElectionAppointmentRevoked::class, ShouldDispatchAfterCommit::class));
    }

    private function group(string $level, int $addressId): Group
    {
        return Group::create([
            'group_type' => '0',
            'name' => 'Group '.$level,
            'location_level' => $level,
            'address_id' => $addressId,
        ]);
    }
}
