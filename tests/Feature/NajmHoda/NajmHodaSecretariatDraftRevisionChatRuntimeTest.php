<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatDraftRevisionChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_revises_existing_draft_only_after_explicit_confirmation(): void
    {
        config(['najm-hoda.enabled' => true]);

        $actor = User::factory()->create();
        $this->actingAs($actor);
        $group = Group::query()->create(['name' => 'S7 Revision Runtime', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S7-REV-RUNTIME',
            'name' => 'S7 Revision Runtime Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'نسخه اولیه گزارش',
            'body' => 'متن نسخه اول',
            'confidentiality' => 'office_members',
        ]);
        $initialVersionCount = $record->versions()->count();
        $initialVersionId = (int) $record->current_version_id;

        $page = [
            'route_name' => 'secretariat.records.show',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_record',
            'resource_id' => $record->id,
            'title' => 'BROWSER FORGED RECORD TITLE',
            'body' => 'BROWSER FORGED RECORD BODY',
        ];

        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'اصلاح پیش‌نویس | عنوان: گزارش نهایی‌تر | متن: متن پیشنهادی نسخه دوم',
            'context' => ['page' => $page],
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_draft_revision');
        $this->assertStringContainsString('فقط پیش‌نمایش نسخه بعدی', (string) $preview->json('message'));
        $this->assertSame($initialVersionCount, $record->fresh()->versions()->count());
        $this->assertSame($initialVersionId, (int) $record->fresh()->current_version_id);

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره اصلاحات',
            'conversation_id' => (int) $preview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);

        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_draft_revision');

        $fresh = $record->fresh()->load('currentVersion');
        $this->assertSame('draft', $fresh->status);
        $this->assertSame($initialVersionCount + 1, $fresh->versions()->count());
        $this->assertSame('گزارش نهایی‌تر', $fresh->title);
        $this->assertSame('متن پیشنهادی نسخه دوم', $fresh->currentVersion->body);
        $this->assertNull($fresh->registry_number);
        $this->assertStringNotContainsString('BROWSER FORGED RECORD TITLE', $fresh->title);
        $this->assertStringNotContainsString('BROWSER FORGED RECORD BODY', (string) $fresh->currentVersion->body);
    }

    public function test_record_page_context_exposes_no_document_title_or_body(): void
    {
        [$actor, $record] = $this->draftFixture('S7-REV-CONTEXT');
        $this->actingAs($actor);

        $resolver = app(\App\Services\NajmHoda\Context\NajmHodaPageContextResolver::class);
        $context = $resolver->resolve($actor, ['page' => [
            'route_name' => 'secretariat.records.show',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_record',
            'resource_id' => $record->id,
            'title' => 'FORGED TITLE',
            'body' => 'FORGED BODY',
        ]]);

        $this->assertSame('secretariat_record', $context['resource_type']);
        $this->assertSame($record->id, $context['resource_id']);
        $this->assertSame($record->office_id, $context['resource']['office_id']);
        $this->assertArrayNotHasKey('title', $context['resource']);
        $this->assertArrayNotHasKey('body', $context['resource']);
        $this->assertStringNotContainsString('FORGED', json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    private function draftFixture(string $code): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => $code,
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'عنوان واقعی',
            'body' => 'بدنه واقعی',
            'confidentiality' => 'office_members',
        ]);
        return [$actor, $record];
    }
}
