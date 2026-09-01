<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatInternalCorrespondenceChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_previews_then_saves_internal_correspondence_as_draft_only(): void
    {
        config(['najm-hoda.enabled' => true]);

        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $this->actingAs($actor);
        $group = Group::query()->create(['name' => 'S7 Runtime Internal', 'group_type' => '0']);
        foreach ([[$actor, 3], [$recipient, 1]] as [$user, $role]) {
            GroupUser::query()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 1,
                'expired' => null,
            ]);
        }
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S7-INT-RUNTIME',
            'name' => 'S7 Internal Runtime Office',
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
            'message' => 'پیش‌نویس مکاتبه داخلی بساز | گیرنده کاربر: ' . $recipient->id . ' | عنوان: درخواست گزارش | موضوع: گزارش اجرا | متن: لطفاً گزارش اجرای تصمیم را ارائه کنید. | کانال: internal | محرمانگی: office_members',
            'context' => ['page' => $page],
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_internal_correspondence');
        $this->assertSame(0, SecretariatRecord::query()->count());

        $save = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره مکاتبه',
            'conversation_id' => (int) $preview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);

        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_internal_correspondence');
        $this->assertStringContainsString('هنوز ثبت رسمی، ارسال، ارجاع یا ابلاغ نشده', (string) $save->json('message'));

        $record = SecretariatRecord::query()->with(['currentVersion', 'parties', 'correspondenceDetail'])->sole();
        $this->assertSame('draft', $record->status);
        $this->assertSame('internal_correspondence', $record->record_type);
        $this->assertSame('internal', $record->direction);
        $this->assertNull($record->registry_number);
        $this->assertSame('درخواست گزارش', $record->title);
        $this->assertNotSame('BROWSER FORGED TITLE', $record->title);
        $this->assertStringNotContainsString('BROWSER FORGED BODY', (string) $record->currentVersion?->body);
        $this->assertSame($recipient->id, $record->parties()->where('role', 'recipient')->value('user_id'));
        $this->assertSame('internal', $record->correspondenceDetail?->channel);
        $this->assertSame(0, $record->dispatches()->count());
    }
}
