<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatEvidenceDraftChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_uses_only_authorized_formal_evidence_and_confirmation_creates_draft_only(): void
    {
        config(['najm-hoda.enabled'=>true]);
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);

        $visible = $this->formal($records, $office, $actor, 'گزارش رسمی برنامه آب', 'اطلاعات رسمی درباره برنامه آب', 'office_members');
        $hidden = $this->formal($records, $office, $actor, 'گزارش محرمانه برنامه آب', 'SECRET-EVIDENCE-HIDDEN', 'confidential');

        $this->actingAs($actor);
        $page = [
            'route_name'=>'secretariat.index','module'=>'secretariat','resource_type'=>'secretariat_office','resource_id'=>$office->id,
            'title'=>'BROWSER FORGED','body'=>'BROWSER FORGED BODY',
        ];
        $before = SecretariatRecord::query()->count();
        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'پیش‌نویس مستند از شواهد بساز | موضوع: برنامه آب | نوع: official_report',
            'context'=>['page'=>$page],
        ]);

        $preview->assertOk()->assertJsonPath('success', true)->assertJsonPath('agent','secretariat_evidence_draft');
        $message = (string)$preview->json('message');
        $this->assertStringContainsString((string)$visible->registry_number, $message);
        $this->assertStringNotContainsString((string)$hidden->registry_number, $message);
        $this->assertStringNotContainsString('SECRET-EVIDENCE-HIDDEN', $message);
        $this->assertStringNotContainsString('BROWSER FORGED', $message);
        $this->assertSame($before, SecretariatRecord::query()->count());

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'ذخیره پیش‌نویس مستند',
            'conversation_id'=>(int)$preview->json('conversation_id'),
            'context'=>['page'=>$page],
        ]);
        $save->assertOk()->assertJsonPath('agent','secretariat_evidence_draft');

        $draft = SecretariatRecord::query()->where('status','draft')->where('record_type','official_report')->latest('id')->firstOrFail();
        $this->assertNull($draft->registry_number);
        $this->assertSame('najm_hoda_s7', data_get($draft->metadata,'prepared_by'));
        $this->assertSame('deterministic_authorized_registry', data_get($draft->metadata,'grounding_mode'));
        $ids = collect(data_get($draft->metadata,'grounding_evidence',[]))->pluck('record_id')->map(fn($id)=>(int)$id)->all();
        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_confirmation_rejects_evidence_changed_after_preview(): void
    {
        config(['najm-hoda.enabled'=>true]);
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $evidence = $this->formal($records, $office, $actor, 'گزارش رسمی آب', 'نسخه اول evidence آب', 'office_members');

        $this->actingAs($actor);
        $page = ['route_name'=>'secretariat.index','module'=>'secretariat','resource_type'=>'secretariat_office','resource_id'=>$office->id];
        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'پیش‌نویس مستند از شواهد بساز | موضوع: آب',
            'context'=>['page'=>$page],
        ]);
        $preview->assertOk()->assertJsonPath('agent','secretariat_evidence_draft');
        $countBefore = SecretariatRecord::query()->count();

        $amendment = $records->createAmendment($evidence->fresh(), $actor, [
            'title'=>$evidence->title,'subject'=>$evidence->subject,'summary'=>$evidence->summary,'body'=>'نسخه دوم evidence آب',
        ], 'Evidence changed after preview');
        $records->approveAmendment($amendment, $actor);

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'ذخیره پیش‌نویس مستند',
            'conversation_id'=>(int)$preview->json('conversation_id'),
            'context'=>['page'=>$page],
        ]);
        $save->assertOk()->assertJsonPath('agent','secretariat_evidence_draft');
        $this->assertStringContainsString('تغییر', (string)$save->json('message'));
        $this->assertSame($countBefore, SecretariatRecord::query()->count());
    }

    private function formal(SecretariatRecordService $records, $office, User $actor, string $title, string $body, string $confidentiality): SecretariatRecord
    {
        $record = $records->createDraft($office, $actor, [
            'record_type'=>'official_report','direction'=>'none','title'=>$title,'subject'=>'آب','body'=>$body,
            'confidentiality'=>$confidentiality,'source_type'=>'manual',
        ]);
        return $records->register($records->submitForApproval($record, $actor), $actor);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name'=>'Evidence Draft Office','group_type'=>'0']);
        GroupUser::query()->create(['group_id'=>$group->id,'user_id'=>$actor->id,'role'=>3,'status'=>1,'expired'=>null]);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'S7-EVD-'.$group->id,'name'=>'Evidence Draft Office','office_type'=>'group','scope_type'=>'group','scope_id'=>$group->id,
        ]);
        return [$actor,$office];
    }
}
