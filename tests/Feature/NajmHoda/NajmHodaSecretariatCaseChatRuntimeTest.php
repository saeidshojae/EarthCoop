<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatCaseChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_previews_then_creates_one_open_case_after_explicit_confirmation(): void
    {
        config(['najm-hoda.enabled' => true]);

        $actor = User::factory()->create();
        $this->actingAs($actor);
        $group = Group::query()->create(['name' => 'S7 Runtime Case', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S7-CASE-RUNTIME',
            'name' => 'S7 Case Runtime Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $page = [
            'route_name' => 'secretariat.cases.create',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
            'title' => 'BROWSER FORGED TITLE',
            'body' => 'BROWSER FORGED BODY',
        ];

        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'پرونده بساز | عنوان: پرونده پیگیری آب | خلاصه: اسناد و مکاتبات مسئله آب در این پرونده گردآوری شوند | محرمانگی: office_members',
            'context' => ['page' => $page],
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_case');
        $this->assertStringContainsString('هنوز هیچ پرونده‌ای ساخته نشده', (string) $preview->json('message'));
        $this->assertSame(0, SecretariatCase::query()->count());

        $create = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ایجاد پرونده',
            'conversation_id' => (int) $preview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);

        $create->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_case');
        $this->assertStringContainsString('وضعیت آن open است', (string) $create->json('message'));

        $case = SecretariatCase::query()->sole();
        $this->assertSame('open', $case->status);
        $this->assertSame('پرونده پیگیری آب', $case->title);
        $this->assertNotSame('BROWSER FORGED TITLE', $case->title);
        $this->assertNotSame('', (string) $case->case_number);
        $this->assertSame('najm_hoda_s7', data_get($case->metadata, 'prepared_by'));
        $this->assertSame(0, $case->records()->count());
        $this->assertSame(0, SecretariatRecord::query()->count());
    }
}
