<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatCaseSummaryChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_case_summary_uses_server_resolved_case_and_never_leaks_hidden_record(): void
    {
        config(['najm-hoda.enabled' => true]);

        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $group = Group::query()->create(['name' => 'S7 Case Summary', 'group_type' => '0']);
        GroupUser::query()->create(['group_id'=>$group->id,'user_id'=>$manager->id,'role'=>3,'status'=>1,'expired'=>null]);
        GroupUser::query()->create(['group_id'=>$group->id,'user_id'=>$viewer->id,'role'=>0,'status'=>1,'expired'=>null]);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'S7-CASE-SUM-'.$group->id,'name'=>'Case Summary Office','office_type'=>'group','scope_type'=>'group','scope_id'=>$group->id,
        ]);

        $case = app(SecretariatCaseService::class)->create($office, $manager, [
            'title'=>'پرونده آب محله','summary'=>'پیگیری رسمی مسئله آب','confidentiality'=>'office_members',
        ]);
        $records = app(SecretariatRecordService::class);
        $visible = $records->createDraft($office, $manager, [
            'record_type'=>'official_report','direction'=>'internal','title'=>'گزارش عمومی آب','body'=>'اطلاعات مجاز','confidentiality'=>'office_members',
        ]);
        $visible = $records->register($records->submitForApproval($visible, $manager), $manager);
        $hidden = $records->createDraft($office, $manager, [
            'record_type'=>'official_report','direction'=>'internal','title'=>'عنوان بسیار محرمانه مخفی','body'=>'محتوای محرمانه','confidentiality'=>'confidential',
        ]);
        $hidden = $records->register($records->submitForApproval($hidden, $manager), $manager);
        app(SecretariatCaseService::class)->addRecord($case, $visible, $manager, 'evidence');
        app(SecretariatCaseService::class)->addRecord($case, $hidden, $manager, 'evidence');

        $caseBefore = $case->fresh()->getAttributes();
        $recordCountBefore = $case->records()->count();
        $this->actingAs($viewer);

        $response = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'خلاصه پرونده را بده',
            'context'=>['page'=>[
                'route_name'=>'secretariat.cases.show','module'=>'secretariat','resource_type'=>'secretariat_case','resource_id'=>$case->id,
                'title'=>'BROWSER FORGED CASE TITLE','body'=>'BROWSER FORGED SECRET',
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_case_summary');

        $message = (string) $response->json('message');
        $this->assertStringContainsString('گزارش عمومی آب', $message);
        $this->assertStringContainsString('تعداد اسناد قابل مشاهده برای شما: 1', $message);
        $this->assertStringNotContainsString('عنوان بسیار محرمانه مخفی', $message);
        $this->assertStringNotContainsString('BROWSER FORGED', $message);
        $this->assertSame($caseBefore, $case->fresh()->getAttributes());
        $this->assertSame($recordCountBefore, $case->records()->count());
    }
}
