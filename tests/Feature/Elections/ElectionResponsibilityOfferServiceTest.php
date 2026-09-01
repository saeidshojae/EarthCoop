<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionAcceptanceStatus;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionResponsibilityAcceptanceEvidenceService;
use App\Services\Elections\ElectionResponsibilityOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ElectionResponsibilityOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_decline_immediately_invites_next_rank_and_acceptance_does_not_install_role(): void
    {
        [$election, $managerA, $managerB, $inspectorA] = $this->fixture();
        $service = app(ElectionResponsibilityOfferService::class);
        $service->start($election);
        $this->assertSame('awaiting_acceptance', $election->refresh()->lifecycle_status->value);

        $managerOffer = ElectionResponsibilityOffer::where('election_id',$election->id)->where('position','manager')->where('status','pending')->firstOrFail();
        $this->assertSame($managerA->id, $managerOffer->candidate_user_id);
        $this->assertSame(1, $managerOffer->ranking_position);
        $this->assertEqualsWithDelta(ElectionResponsibilityOfferService::RESPONSE_WINDOW_DAYS,$managerOffer->offered_at->diffInDays($managerOffer->expires_at),0.000001);
        $this->assertSame(ElectionAcceptanceStatus::Pending, $this->candidateProjection($election,$managerA,'manager')->acceptance_status);

        $service->decline($managerOffer,$managerA->id);
        $this->assertSame(ElectionAcceptanceStatus::Declined,$this->candidateProjection($election,$managerA,'manager')->acceptance_status);
        $replacement = ElectionResponsibilityOffer::where('election_id',$election->id)->where('position','manager')->where('status','pending')->firstOrFail();
        $this->assertSame($managerB->id,$replacement->candidate_user_id);
        $this->assertSame(2,$replacement->ranking_position);

        $inspectorOffer = ElectionResponsibilityOffer::where('election_id',$election->id)->where('position','inspector')->where('status','pending')->firstOrFail();
        app(ElectionResponsibilityAcceptanceEvidenceService::class)->confirm($inspectorOffer,$inspectorA,(int)$inspectorOffer->contract_version_id);
        $service->accept($inspectorOffer->refresh(),$inspectorA->id);
        $this->assertSame(ElectionResponsibilityOfferStatus::Accepted,$inspectorOffer->refresh()->status);
        $this->assertSame(ElectionAcceptanceStatus::Accepted,$this->candidateProjection($election,$inspectorA,'inspector')->acceptance_status);
        $this->assertSame(1,(int)GroupUser::where('group_id',$election->group_id)->where('user_id',$inspectorA->id)->value('role'));
    }

    public function test_silence_expires_server_side_and_candidate_who_lost_live_eligibility_is_skipped(): void
    {
        [$election,$managerA,$managerB]=$this->fixture();
        $service=app(ElectionResponsibilityOfferService::class);$service->start($election);
        $first=ElectionResponsibilityOffer::where('election_id',$election->id)->where('position','manager')->where('status','pending')->firstOrFail();
        $first->forceFill(['expires_at'=>now()->subSecond()])->save();
        GroupUser::where('group_id',$election->group_id)->where('user_id',$managerB->id)->update(['status'=>0]);
        $this->assertSame(1,$service->expireDue());
        $this->assertSame(ElectionResponsibilityOfferStatus::Expired,$first->refresh()->status);
        $second=ElectionResponsibilityOffer::where('election_id',$election->id)->where('position','manager')->where('candidate_user_id',$managerB->id)->firstOrFail();
        $this->assertSame(ElectionResponsibilityOfferStatus::Ineligible,$second->status);
    }

    public function test_accepting_an_expired_offer_commits_expiry_before_validation_error(): void
    {
        [$election,$managerA,$managerB]=$this->fixture();
        $service=app(ElectionResponsibilityOfferService::class);
        $service->start($election);
        $first=ElectionResponsibilityOffer::where('election_id',$election->id)->where('position','manager')->where('status','pending')->firstOrFail();
        $first->forceFill(['expires_at'=>now()->subSecond()])->save();

        try {
            $service->accept($first->refresh(), $managerA->id);
            $this->fail('Expected expired offer acceptance to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('offer', $exception->errors());
        }

        $this->assertSame(ElectionResponsibilityOfferStatus::Expired, $first->refresh()->status);
        $replacement=ElectionResponsibilityOffer::where('election_id',$election->id)
            ->where('position','manager')->where('status','pending')->firstOrFail();
        $this->assertSame($managerB->id, (int) $replacement->candidate_user_id);
    }

    public function test_offer_chain_becomes_exhausted_after_all_ranked_candidates_expire_or_become_ineligible(): void
    {
        [$election,$managerA,$managerB,$inspectorA,$inspectorB]=$this->fixture();
        $service=app(ElectionResponsibilityOfferService::class);
        $service->start($election);

        GroupUser::where('group_id',$election->group_id)
            ->whereIn('user_id',[$managerB->id,$inspectorB->id])
            ->update(['status'=>0]);

        ElectionResponsibilityOffer::where('election_id',$election->id)
            ->whereIn('candidate_user_id',[$managerA->id,$inspectorA->id])
            ->where('status','pending')
            ->update(['expires_at'=>now()->subSecond()]);

        $this->assertSame(2,$service->expireDue(10));
        $this->assertSame(ElectionLifecycleStatus::Exhausted, $election->refresh()->lifecycle_status);
        $this->assertSame(0, ElectionResponsibilityOffer::where('election_id',$election->id)->where('status','pending')->count());
        $this->assertSame(2, ElectionResponsibilityOffer::where('election_id',$election->id)->where('status','ineligible')->count());
    }

    private function candidateProjection(Election $election,User $user,string $position): Candidate
    { return Candidate::where('election_id',$election->id)->where('user_id',$user->id)->where('position',$position)->firstOrFail(); }

    private function fixture(): array
    {
        $group=Group::create(['name'=>'Offers group','group_type'=>'public','location_level'=>'neighborhood']);
        GroupSetting::create(['level'=>'neighborhood','manager_count'=>1,'inspector_count'=>1,'election_time'=>10,'max_for_election'=>1,'election_status'=>1]);
        $election=Election::create(['group_id'=>$group->id,'starts_at'=>now()->subDays(30),'ends_at'=>now()->subMinute(),'is_closed'=>true,'lifecycle_status'=>ElectionLifecycleStatus::Tallying]);
        $managerA=User::factory()->create();$managerB=User::factory()->create();$inspectorA=User::factory()->create();$inspectorB=User::factory()->create();
        foreach([$managerA,$managerB,$inspectorA,$inspectorB] as $user) GroupUser::create(['group_id'=>$group->id,'user_id'=>$user->id,'role'=>1,'status'=>1]);
        $manifest=array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES,'متن کامل بند E0');
        foreach(['manager','inspector'] as $position) ElectionResponsibilityContractVersion::create([
            'position'=>$position,'version'=>1,'body'=>"contract {$position} v1",'clause_manifest'=>$manifest,'e0_compliant'=>true,'is_active'=>true,'published_at'=>now()->subDay(),
        ]);
        $this->tallyRow($election,$managerA,'manager',1,10);$this->tallyRow($election,$managerB,'manager',2,8);$this->tallyRow($election,$inspectorA,'inspector',1,9);$this->tallyRow($election,$inspectorB,'inspector',2,5);
        return[$election,$managerA,$managerB,$inspectorA,$inspectorB];
    }

    private function tallyRow(Election $election,User $candidate,string $position,int $rank,int $votes): void
    {
        ElectionTallyResult::create(['election_id'=>$election->id,'candidate_user_id'=>$candidate->id,'position'=>$position,'vote_count'=>$votes,'rank'=>$rank,'within_seat_cutoff'=>$rank===1,'cycle_identifier'=>'election:'.$election->id,'stopped_at'=>now()->subMinute(),'vote_snapshot_hash'=>str_repeat('a',64),'draw_seed_version'=>'stop-cycle-snapshot-sha256-v1','draw_seed'=>str_repeat('b',64),'tie_break_version'=>'verifiable-draw-sha256-v1','tie_break_key'=>hash('sha256',$position.'-'.$rank),'tallied_at'=>now()->subMinute()]);
    }
}
