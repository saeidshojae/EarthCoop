<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatReplyDraftChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_preview_uses_only_authorized_formal_evidence_and_confirmation_creates_draft_with_responds_to(): void
    {
        config(['najm-hoda.enabled' => true]);
        [$actor, $group, $office, $source] = $this->fixture();
        $records = app(SecretariatRecordService::class);

        $visible = $records->createDraft($office, $actor, [
            'record_type'=>'official_report','direction'=>'internal','title'=>'سابقه رسمی برنامه آب',
            'subject'=>'برنامه آب','body'=>'طبق گزارش رسمی، مرحله بررسی فنی برنامه آب تکمیل شده است.',
            'confidentiality'=>'office_members','source_type'=>'manual',
        ]);
        $visible = $records->register($records->submitForApproval($visible, $actor), $actor);

        $hidden = $records->createDraft($office, $actor, [
            'record_type'=>'official_report','direction'=>'internal','title'=>'سابقه محرمانه برنامه آب',
            'subject'=>'برنامه آب','body'=>'SECRET-HIDDEN-EVIDENCE',
            'confidentiality'=>'confidential','source_type'=>'manual',
        ]);
        $records->register($records->submitForApproval($hidden, $actor), $actor);

        $this->actingAs($actor);
        $page = [
            'route_name'=>'secretariat.records.show','module'=>'secretariat','resource_type'=>'secretariat_record','resource_id'=>$source->id,
            'title'=>'BROWSER FORGED SOURCE','recipient'=>'BROWSER FORGED RECIPIENT','body'=>'BROWSER FORGED BODY',
        ];
        $beforeCount = SecretariatRecord::query()->count();

        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'پیش‌نویس پاسخ مستند آماده کن | محور: پاسخ درباره وضعیت برنامه آب',
            'context'=>['page'=>$page],
        ]);

        $preview->assertOk()->assertJsonPath('success', true)->assertJsonPath('agent', 'secretariat_reply_draft');
        $message = (string) $preview->json('message');
        $this->assertStringContainsString('سازمان آب نمونه', $message);
        $this->assertStringContainsString((string) $visible->registry_number, $message);
        $this->assertStringNotContainsString('SECRET-HIDDEN-EVIDENCE', $message);
        $this->assertStringNotContainsString('سابقه محرمانه برنامه آب', $message);
        $this->assertStringNotContainsString('BROWSER FORGED', $message);
        $this->assertSame($beforeCount, SecretariatRecord::query()->count());
        $this->assertDatabaseCount('secretariat_relations', 0);

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'ذخیره پاسخ',
            'conversation_id'=>(int) $preview->json('conversation_id'),
            'context'=>['page'=>$page],
        ]);
        $save->assertOk()->assertJsonPath('success', true)->assertJsonPath('agent', 'secretariat_reply_draft');

        $reply = SecretariatRecord::query()
            ->where('record_type', 'outgoing_letter')
            ->where('status', 'draft')
            ->where('id', '<>', $source->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($reply->registry_number);
        $this->assertSame('outgoing', $reply->direction);
        $this->assertSame('najm_hoda_s7', data_get($reply->metadata, 'prepared_by'));
        $this->assertSame($source->id, (int) data_get($reply->metadata, 'reply_to_record_id'));
        $evidenceIds = collect(data_get($reply->metadata, 'grounding_evidence', []))->pluck('record_id')->map(fn ($id)=>(int)$id)->all();
        $this->assertContains($visible->id, $evidenceIds);
        $this->assertNotContains($hidden->id, $evidenceIds);

        $recipient = $reply->parties()->where('role','recipient')->sole();
        $this->assertSame('external', $recipient->party_type);
        $this->assertSame('سازمان آب نمونه', $recipient->display_name);
        $this->assertSame('office@example.org', $recipient->email);
        $this->assertDatabaseHas('secretariat_relations', [
            'source_record_id'=>$reply->id,'target_record_id'=>$source->id,'relation_type'=>'responds_to',
        ]);
        $this->assertSame(0, $reply->dispatches()->count());
    }

    public function test_confirmation_rejects_stale_source_version_after_formal_amendment(): void
    {
        config(['najm-hoda.enabled' => true]);
        [$actor, , , $source] = $this->fixture();
        $this->actingAs($actor);
        $page = ['route_name'=>'secretariat.records.show','module'=>'secretariat','resource_type'=>'secretariat_record','resource_id'=>$source->id];

        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'پیش‌نویس پاسخ آماده کن | محور: پاسخ رسمی',
            'context'=>['page'=>$page],
        ]);
        $preview->assertOk()->assertJsonPath('agent', 'secretariat_reply_draft');
        $countBefore = SecretariatRecord::query()->count();

        $records = app(SecretariatRecordService::class);
        $amendment = $records->createAmendment($source->fresh(), $actor, [
            'title'=>$source->title,
            'subject'=>$source->subject,
            'summary'=>$source->summary,
            'body'=>'نسخه اصلاح‌شده نامه مبدأ',
        ], 'Source changed after reply preview');
        $records->approveAmendment($amendment, $actor);

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message'=>'ذخیره پاسخ',
            'conversation_id'=>(int) $preview->json('conversation_id'),
            'context'=>['page'=>$page],
        ]);
        $save->assertOk()->assertJsonPath('agent', 'secretariat_reply_draft');
        $this->assertStringContainsString('تغییر', (string) $save->json('message'));
        $this->assertSame($countBefore, SecretariatRecord::query()->count());
        $this->assertDatabaseMissing('secretariat_relations', ['target_record_id'=>$source->id,'relation_type'=>'responds_to']);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name'=>'S7 Reply Evidence','group_type'=>'0']);
        GroupUser::query()->create(['group_id'=>$group->id,'user_id'=>$actor->id,'role'=>3,'status'=>1,'expired'=>null]);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'S7-REPLY-'.$group->id,'name'=>'Reply Office','office_type'=>'group','scope_type'=>'group','scope_id'=>$group->id,
        ]);

        $source = app(SecretariatCorrespondenceService::class)->createDraft(
            $office,
            $actor,
            'incoming',
            [
                'title'=>'درخواست درباره برنامه آب','subject'=>'برنامه آب','body'=>'خواهشمند است وضعیت برنامه آب اعلام شود.',
                'confidentiality'=>'office_members','channel'=>'email','received_at'=>now(),'source_type'=>'manual',
            ],
            [
                ['role'=>'sender','party_type'=>'external','display_name'=>'سازمان آب نمونه','organization_name'=>'سازمان آب','email'=>'office@example.org'],
                ['role'=>'recipient','party_type'=>'group','group_id'=>$group->id,'display_name'=>$group->name],
            ],
        );
        $records = app(SecretariatRecordService::class);
        $source = $records->register($records->submitForApproval($source, $actor), $actor);

        return [$actor, $group, $office, $source];
    }
}
