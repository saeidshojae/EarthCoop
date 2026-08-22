<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionResponsibilityOfferHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_get_only_shows_confirmation_and_never_mutates_offer_or_role(): void
    {
        $this->withoutVite(); [$user,$group,$candidate,$offer]=$this->fixture();
        $response=$this->actingAs($user)->get(route('profile.accept.candidate',['type'=>'accept','id'=>$candidate->id]));
        $response->assertOk()->assertSee('دعوت به پذیرش مسئولیت')->assertSee('به‌طور کامل خواندم')->assertSee('دانلود همین نسخه قرارداد');
        $this->assertSame('pending',$offer->refresh()->status->value);
        $this->assertSame(1,(int)GroupUser::where('group_id',$group->id)->where('user_id',$user->id)->value('role'));
    }

    public function test_canonical_post_requires_explicit_version_bound_confirmation_and_leaves_appointment_for_e8(): void
    {
        [$user,$group,,$offer]=$this->fixture();
        $this->actingAs($user)->post(route('elections.responsibility-offers.respond',['offer'=>$offer->id,'decision'=>'accept']),['contract_version_id'=>$offer->contract_version_id])->assertSessionHasErrors('contract_confirmed');
        $this->assertSame('pending',$offer->refresh()->status->value);
        $response=$this->actingAs($user)->post(route('elections.responsibility-offers.respond',['offer'=>$offer->id,'decision'=>'accept']),['contract_confirmed'=>'1','contract_version_id'=>$offer->contract_version_id]);
        $response->assertRedirect(route('profile.show'));
        $offer->refresh();
        $this->assertSame('accepted',$offer->status->value);
        $this->assertSame($user->id,$offer->response_metadata['acceptance_evidence']['candidate_user_id']);
        $this->assertSame($offer->contract_version_id,$offer->response_metadata['acceptance_evidence']['contract_version_id']);
        $this->assertNotEmpty($offer->response_metadata['acceptance_evidence']['confirmed_at']);
        $this->assertSame(1,(int)GroupUser::where('group_id',$group->id)->where('user_id',$user->id)->value('role'));
    }

    public function test_another_user_cannot_answer_someone_elses_offer(): void
    {
        [$user,,,$offer]=$this->fixture();$other=User::factory()->create();
        $this->actingAs($other)->post(route('elections.responsibility-offers.respond',['offer'=>$offer->id,'decision'=>'accept']),['contract_confirmed'=>'1','contract_version_id'=>$offer->contract_version_id])->assertSessionHasErrors('offer');
        $this->assertSame('pending',$offer->refresh()->status->value);$this->assertNotSame($user->id,$other->id);
    }

    private function fixture(): array
    {
        $group=Group::create(['name'=>'HTTP E7 group','group_type'=>'public','location_level'=>'neighborhood']);
        $election=Election::create(['group_id'=>$group->id,'starts_at'=>now()->subDays(10),'ends_at'=>now()->subDay(),'is_closed'=>true,'lifecycle_status'=>ElectionLifecycleStatus::AwaitingAcceptance]);
        $user=User::factory()->create();GroupUser::create(['group_id'=>$group->id,'user_id'=>$user->id,'role'=>1,'status'=>1]);
        $manifest=array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES,'متن کامل بند E0');
        $contract=ElectionResponsibilityContractVersion::create(['position'=>'manager','version'=>1,'body'=>'HTTP contract body','clause_manifest'=>$manifest,'e0_compliant'=>true,'is_active'=>true,'published_at'=>now()->subDay()]);
        $offer=ElectionResponsibilityOffer::create(['election_id'=>$election->id,'candidate_user_id'=>$user->id,'position'=>'manager','ranking_position'=>1,'contract_version_id'=>$contract->id,'status'=>'pending','offered_at'=>now(),'expires_at'=>now()->addDays(7),'eligibility_checked_at'=>now()]);
        $candidate=Candidate::create(['election_id'=>$election->id,'user_id'=>$user->id,'position'=>'manager','accept_status'=>1]);
        return[$user,$group,$candidate,$offer];
    }
}
