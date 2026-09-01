<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Services\SecretariatGovernanceIntegrationService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\NajmHoda\Context\NajmHodaSecretariatRegistrationAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatRegistrationAdvisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_minute_and_adopted_resolution_are_detected_without_mutation_and_draft_is_not_mislabeled_unrecorded(): void
    {
        [$actor, $office, $minute, $resolution] = $this->fixture();
        $advisor = app(NajmHodaSecretariatRegistrationAdvisor::class);
        $context = ['page_kind'=>'secretariat_office','resource_type'=>'secretariat_office','resource_id'=>$office->id];

        $beforeRecords = \App\Modules\Secretariat\Models\SecretariatRecord::query()->count();
        $first = $advisor->intercept($actor, $context, 'چه چیزهایی لازم‌الثبت هستند؟');

        $this->assertSame('registration_review', $first['status']);
        $this->assertSame($beforeRecords, \App\Modules\Secretariat\Models\SecretariatRecord::query()->count());
        $this->assertContains($minute->id, collect($first['unrecorded'])->where('source_kind','approved_meeting_minute')->pluck('source_id')->all());
        $this->assertContains($resolution->id, collect($first['unrecorded'])->where('source_kind','adopted_governance_resolution')->pluck('source_id')->all());

        $minutePacket = collect($first['unrecorded'])->firstWhere('source_kind', 'approved_meeting_minute');
        $this->assertSame('meeting_minute', $minutePacket['suggested_record_type']);
        $this->assertSame('internal', $minutePacket['suggested_direction']);
        $this->assertSame($office->id, $minutePacket['suggested_office_id']);
        $this->assertSame($office->default_confidentiality, $minutePacket['suggested_confidentiality']);

        $draft = app(SecretariatGovernanceIntegrationService::class)->proposeApprovedMeetingMinute($minute, $actor);
        $second = $advisor->intercept($actor, $context, 'موارد ثبت‌نشده را بررسی کن');

        $this->assertNotContains($minute->id, collect($second['unrecorded'])->where('source_kind','approved_meeting_minute')->pluck('source_id')->all());
        $pending = collect($second['pending_registry'])->firstWhere('source_kind', 'approved_meeting_minute');
        $this->assertSame($draft->id, $pending['record_id']);
        $this->assertSame('draft', $pending['registry_status']);
    }

    public function test_ordinary_member_cannot_inspect_unregistered_governance_sources(): void
    {
        [$actor, $office] = $this->fixture();
        $member = User::factory()->create();
        GroupUser::query()->create(['group_id'=>$office->scope_id,'user_id'=>$member->id,'role'=>0,'status'=>1]);

        $result = app(NajmHodaSecretariatRegistrationAdvisor::class)->intercept(
            $member,
            ['page_kind'=>'secretariat_office','resource_type'=>'secretariat_office','resource_id'=>$office->id],
            'چه چیزهایی باید ثبت شوند؟'
        );

        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('دسترسی', $result['message']);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $group = Group::query()->create(['name'=>'Registration Intelligence','group_type'=>'0']);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'REGI-'.$group->id,'name'=>'Registration Office','office_type'=>'group','scope_type'=>'group','scope_id'=>$group->id,
        ]);
        $session = GroupSession::query()->create([
            'group_id'=>$group->id,'created_by'=>$actor->id,'title'=>'جلسه رسمی آب','subject'=>'آب',
            'status'=>'ended','starts_at'=>now()->subHours(2),'started_at'=>now()->subHours(2),'ended_at'=>now()->subHour(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id'=>$session->id,'group_id'=>$group->id,'status'=>'approved','summary'=>'خلاصه',
            'minutes'=>'متن مصوب','generated_by'=>$actor->id,'approved_by'=>$actor->id,
            'generated_at'=>now()->subMinutes(40),'approved_at'=>now()->subMinutes(30),
        ]);
        $proposal = Proposal::query()->create([
            'group_id'=>$group->id,'created_by'=>$actor->id,'type'=>'policy','title'=>'مصوبه آب',
            'summary'=>'خلاصه مصوبه','description'=>'شرح مصوبه','status'=>'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id'=>$proposal->id,'group_id'=>$group->id,'adopted_by'=>$actor->id,'type'=>'policy',
            'status'=>'adopted','effect_status'=>'none','adopted_at'=>now()->subMinutes(20),'effective_at'=>now(),
        ]);

        return [$actor, $office, $minute, $resolution];
    }
}
