<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatDraftChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_previews_then_saves_exact_secretariat_draft_after_explicit_confirmation(): void
    {
        config(['najm-hoda.enabled' => true]);

        $actor = User::factory()->create();
        $this->actingAs($actor);

        $group = Group::query()->create([
            'name' => 'S6 Draft Runtime',
            'group_type' => '0',
        ]);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S6-DRAFT-RUNTIME',
            'name' => 'S6 Draft Runtime Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $page = [
            'route_name' => 'secretariat.index',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
            'title' => 'BROWSER FORGED TITLE',
            'body' => 'BROWSER FORGED BODY',
        ];

        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'پیش‌نویس سند بساز | عنوان: گزارش آب محله | متن: برنامه آب در سه مرحله اجرا شود. | نوع: official_report | جهت: internal | محرمانگی: office_members',
            'context' => ['page' => $page],
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_draft');
        $this->assertStringContainsString('این فقط پیش‌نمایش است', (string) $preview->json('message'));
        $this->assertSame(0, SecretariatRecord::query()->count());

        $conversationId = (int) $preview->json('conversation_id');
        $this->assertGreaterThan(0, $conversationId);

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره پیش‌نویس',
            'conversation_id' => $conversationId,
            'context' => ['page' => $page],
        ]);

        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_draft');
        $this->assertStringContainsString('هنوز ارسال، تأیید، ثبت رسمی یا منتشر نشده', (string) $save->json('message'));

        $record = SecretariatRecord::query()->sole();
        $this->assertSame('draft', $record->status);
        $this->assertSame('گزارش آب محله', $record->title);
        $this->assertSame('official_report', $record->record_type);
        $this->assertSame('internal', $record->direction);
        $this->assertSame('office_members', $record->confidentiality);
        $this->assertNull($record->registry_number);
        $this->assertNotSame('BROWSER FORGED TITLE', $record->title);
        $this->assertStringNotContainsString('BROWSER FORGED BODY', (string) $record->currentVersion?->body);
    }

    public function test_chat_api_does_not_save_on_cancel(): void
    {
        config(['najm-hoda.enabled' => true]);

        $actor = User::factory()->create();
        $this->actingAs($actor);
        $group = Group::query()->create(['name' => 'S6 Draft Cancel', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S6-DRAFT-CANCEL',
            'name' => 'S6 Draft Cancel Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $page = [
            'route_name' => 'secretariat.index',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
        ];

        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'پیش‌نویس سند بساز | عنوان: سند لغوشونده | متن: این متن نباید ذخیره شود.',
            'context' => ['page' => $page],
        ])->assertOk();

        $this->postJson('/api/najm-hoda/chat', [
            'message' => 'لغو',
            'conversation_id' => (int) $preview->json('conversation_id'),
            'context' => ['page' => $page],
        ])->assertOk()->assertJsonPath('agent', 'secretariat_draft');

        $this->assertSame(0, SecretariatRecord::query()->count());
    }
}
