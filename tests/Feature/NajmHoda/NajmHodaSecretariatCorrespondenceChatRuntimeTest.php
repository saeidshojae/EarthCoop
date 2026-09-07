<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatCorrespondenceChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_previews_then_saves_outgoing_correspondence_as_draft_only(): void
    {
        config(['najm-hoda.enabled' => true]);

        $actor = User::factory()->create();
        $this->actingAs($actor);
        $group = Group::query()->create(['name' => 'S7 Runtime Correspondence', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S7-CORR-RUNTIME',
            'name' => 'S7 Correspondence Runtime Office',
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
            'message' => 'پیش‌نویس نامه صادره بساز | گیرنده: دانشگاه نمونه | سازمان: دانشگاه نمونه | ایمیل: info@example.org | عنوان: دعوت به همکاری | موضوع: همکاری علمی | متن: از دانشگاه برای همکاری در طرح دعوت می‌شود. | کانال: email | محرمانگی: office_members',
            'context' => ['page' => $page],
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_correspondence');
        $this->assertStringContainsString('این فقط پیش‌نمایش است', (string) $preview->json('message'));
        $this->assertSame(0, SecretariatRecord::query()->count());

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره نامه',
            'conversation_id' => (int) $preview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);

        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_correspondence');
        $this->assertStringContainsString('هنوز ثبت رسمی، ارسال، ابلاغ یا منتشر نشده', (string) $save->json('message'));

        $record = SecretariatRecord::query()->with(['currentVersion', 'parties', 'correspondenceDetail'])->sole();
        $this->assertSame('draft', $record->status);
        $this->assertSame('outgoing_letter', $record->record_type);
        $this->assertSame('outgoing', $record->direction);
        $this->assertNull($record->registry_number);
        $this->assertSame('دعوت به همکاری', $record->title);
        $this->assertStringNotContainsString('BROWSER FORGED BODY', (string) $record->currentVersion?->body);
        $this->assertNotSame('BROWSER FORGED TITLE', $record->title);
        $this->assertSame('email', $record->correspondenceDetail?->channel);
        $this->assertSame('دانشگاه نمونه', $record->parties()->where('role', 'recipient')->value('display_name'));
        $this->assertSame(0, $record->dispatches()->count());
    }
}
