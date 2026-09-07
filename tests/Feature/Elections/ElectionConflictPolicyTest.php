<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\OccupationalField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionConflictPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_e0_baseline_suspends_public_office_when_same_level_job_office_is_accepted_and_opens_vacancy(): void
    {
        $user = User::factory()->create();
        $public = Group::create(['name'=>'public n','group_type'=>'public','location_level'=>'neighborhood']);
        $field = OccupationalField::create(['name' => 'Test field', 'status' => 1]);
        $job = Group::create(['name'=>'job n','group_type'=>'public','location_level'=>'neighborhood','specialty_id'=>$field->id]);
        foreach ([$public,$job] as $group) GroupUser::create(['group_id'=>$group->id,'user_id'=>$user->id,'role'=>1,'status'=>1]);

        $first = $this->appointment($public, $user, 'manager', 1);
        $second = $this->appointment($job, $user, 'manager', 2);

        $this->assertSame('suspended', $first->refresh()->status);
        $this->assertSame('active', $second->refresh()->status);
        $this->assertDatabaseHas('election_vacancies', ['source_appointment_id'=>$first->id,'status'=>'open']);
        $this->assertFalse((bool) ($first->refresh()->metadata['automatic_return'] ?? true));
    }

    public function test_baseline_matrix_contains_exact_public_to_specialized_same_or_higher_rules(): void
    {
        $this->assertDatabaseHas('election_conflict_policy_rules', [
            'current_position'=>'manager','current_domain_type'=>'public','current_level'=>'neighborhood',
            'new_position'=>'inspector','new_domain_type'=>'experience','new_level'=>'global',
            'decision'=>'allowed_with_suspension',
        ]);
        $this->assertDatabaseMissing('election_conflict_policy_rules', [
            'current_position'=>'manager','current_domain_type'=>'public','current_level'=>'global',
            'new_position'=>'manager','new_domain_type'=>'job','new_level'=>'neighborhood',
        ]);
    }

    private function appointment(Group $group, User $user, string $position, int $version): ElectionAppointment
    {
        $election = Election::create(['group_id'=>$group->id,'starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth(),'is_closed'=>false,'lifecycle_status'=>'open']);
        $contract = ElectionResponsibilityContractVersion::create([
            'position'=>$position,'version'=>$version,'body'=>'conflict test','is_active'=>true,'published_at'=>now()->subDay(),
        ]);
        $offer = ElectionResponsibilityOffer::create([
            'election_id'=>$election->id,'candidate_user_id'=>$user->id,'position'=>$position,'ranking_position'=>1,
            'contract_version_id'=>$contract->id,'status'=>'accepted','offered_at'=>now()->subHour(),'expires_at'=>now()->addDays(7),'responded_at'=>now(),
        ]);
        return ElectionAppointment::create([
            'election_id'=>$election->id,'responsibility_offer_id'=>$offer->id,'user_id'=>$user->id,'group_id'=>$group->id,
            'position'=>$position,'group_role'=>$position==='manager'?2:3,'appointment_kind'=>'direct','status'=>'active','appointed_at'=>now(),
            'actor'=>'test','reason'=>'test',
        ]);
    }
}
