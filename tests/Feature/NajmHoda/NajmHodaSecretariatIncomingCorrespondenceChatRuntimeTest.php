<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatIncomingCorrespondenceChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_previews_then_saves_incoming_correspondence_as_draft_only(): void
    {
        config(['najm-hoda.enabled' => true]);

        $actor = User::factory()->create();
        $this->actingAs($actor);
        $group = Group::query()->create(['name' => 'S7 Runtime Incoming', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S7-IN-RUNTIME',
            'name' => 'S7 Incoming Runtime Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $page = [
            'route_name' => 'secretariat.correspondence.create',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
            'title' => 'BROWSER FORGED TITLE',
            'body' => 'BROWSER FORGED BODY',
        ];

        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'پیش‌نویس نامه وارده بساز | فرستنده: انجمن نمونه | سازمان: انجمن نمونه | ایمیل: incoming@example.org | عنوان: نامه همکاری | متن: این نامه برای بررسی همکاری دریافت شده است. | دریافت: 2026-08-19T14:15:00+04:00 | شماره خارجی: IN-77 | کانال: email | محرمانگی: office_members',
            'context' => ['page' => $page],
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_incoming_correspondence');
        $this->assertSame(0, SecretariatRecord::query()->count());

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره نامه',
            'conversation_id' => (int) $preview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);

        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_incoming_correspondence');
        $this->assertStringContainsString('هنوز ثبت رسمی، ارجاع، ابلاغ یا منتشر نشده', (string) $save->json('message'));

        $record = SecretariatRecord::query()->with(['currentVersion', 'parties', 'correspondenceDetail'])->sole();
        $this->assertSame('draft', $record->status);
        $this->assertSame('incoming_letter', $record->record_type);
        $this->assertSame('incoming', $record->direction);
        $this->assertNull($record->registry_number);
        $this->assertSame('external_document', $record->source_type);
        $this->assertSame('نامه همکاری', $record->title);
        $this->assertNotSame('BROWSER FORGED TITLE', $record->title);
        $this->assertStringNotContainsString('BROWSER FORGED BODY', (string) $record->currentVersion?->body);
        $this->assertSame('IN-77', $record->correspondenceDetail?->external_reference_number);
        $this->assertNotNull($record->correspondenceDetail?->received_at);
        $this->assertSame('انجمن نمونه', $record->parties()->where('role', 'sender')->value('display_name'));
        $this->assertSame(0, $record->dispatches()->count());
    }
}
