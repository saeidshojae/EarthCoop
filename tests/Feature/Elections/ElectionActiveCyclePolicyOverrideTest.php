<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionPolicyVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\User;
use App\Services\Elections\ElectionActiveCyclePolicyOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ElectionActiveCyclePolicyOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_cycle_changes_only_through_explicit_reasoned_override_and_audit_is_recorded(): void
    {
        $setting=GroupSetting::create(['level'=>'neighborhood','manager_count'=>7,'inspector_count'=>3,'election_time'=>30,'max_for_election'=>20,'election_status'=>1,'second_election_time'=>6]);
        $p1=$this->policy($setting,1,7,'baseline');$p2=$this->policy($setting,2,8,'new future decisions');
        $group=Group::create(['name'=>'override group','group_type'=>'public','location_level'=>'neighborhood']);
        $election=Election::create(['group_id'=>$group->id,'policy_version_id'=>$p1->id,'starts_at'=>now()->subDay(),'ends_at'=>now()->addDays(29),'is_closed'=>false,'lifecycle_status'=>'open']);
        $actor=User::factory()->create();

        $this->assertSame($p1->id,$election->refresh()->policy_version_id);
        $service=app(ElectionActiveCyclePolicyOverrideService::class);
        try { $service->apply($election,$p2,$actor,''); $this->fail('Missing reason must fail'); } catch(InvalidArgumentException $e) { $this->assertTrue(true); }
        $this->assertSame($p1->id,$election->refresh()->policy_version_id);

        $audit=$service->apply($election,$p2,$actor,'اصلاح فوری خطای ثبت‌شده در سیاست چرخه');
        $this->assertSame($p2->id,$election->refresh()->policy_version_id);
        $this->assertSame($p1->id,$audit->from_policy_version_id);
        $this->assertSame($p2->id,$audit->to_policy_version_id);
        $this->assertTrue((bool)$audit->metadata['explicit_override']);
        $this->assertDatabaseHas('election_policy_overrides',['election_id'=>$election->id,'actor_user_id'=>$actor->id]);
    }

    private function policy(GroupSetting $setting,int $version,int $managers,string $reason): ElectionPolicyVersion
    {
        return ElectionPolicyVersion::create(['group_setting_id'=>$setting->id,'level_key'=>'neighborhood','version'=>$version,'election_status'=>true,'manager_count'=>$managers,'inspector_count'=>3,'voting_duration_days'=>30,'start_threshold'=>20,'cycle_interval_months'=>6,'response_duration_days'=>7,'report_min_distinct_voters'=>10,'report_bucket_days'=>7,'meaningful_trend_min_net_change'=>3,'effective_at'=>now()->subMinutes(10-$version),'change_reason'=>$reason]);
    }
}
