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

class NajmHodaSecretariatDraftReadinessChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_returns_read_only_readiness_without_mutating_draft(): void
    {
        config(['najm-hoda.enabled' => true]);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $group = Group::query()->create(['name' => 'S7 Readiness Runtime', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S7-READY-RT',
            'name' => 'S7 Readiness Runtime Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $records = app(SecretariatRecordService::class);
        $draft = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'گزارش برنامه آب محله',
            'body' => null,
            'confidentiality' => 'office_members',
        ]);
        $formal = $records->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'سند رسمی برنامه آب محله',
            'body' => 'این سند رسمی اطلاعات مرتبط با برنامه آب را ثبت کرده است.',
            'confidentiality' => 'office_members',
            'source_type' => 'manual',
        ]);
        $formal = $records->register($records->submitForApproval($formal, $actor), $actor);

        $page = [
            'route_name' => 'secretariat.records.show',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_record',
            'resource_id' => $draft->id,
            'title' => 'BROWSER FORGED TITLE',
            'body' => 'BROWSER FORGED BODY',
        ];

        $beforeRecords = SecretariatRecord::query()->count();
        $beforeVersions = $draft->versions()->count();

        $response = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'این پیش‌نویس را بررسی کن؛ چه چیزهایی کم دارد و چه شواهدی مرتبط است؟',
            'context' => ['page' => $page],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_readiness');
        $this->assertStringContainsString('متن/بدنه نسخه جاری خالی است', (string) $response->json('message'));
        $this->assertStringContainsString((string) $formal->registry_number, (string) $response->json('message'));
        $this->assertStringContainsString('هیچ تغییری در سند نداد', (string) $response->json('message'));
        $this->assertStringNotContainsString('BROWSER FORGED BODY', (string) $response->json('message'));

        $this->assertSame($beforeRecords, SecretariatRecord::query()->count());
        $this->assertSame($beforeVersions, $draft->fresh()->versions()->count());
        $this->assertNull($draft->fresh()->registry_number);
    }
}
