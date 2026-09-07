<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatDraftAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatDraftAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_has_no_side_effect_and_explicit_save_creates_only_a_draft(): void
    {
        [$actor, $office] = $this->officeWithMemberRole(3);
        $page = app(NajmHodaPageContextResolver::class)->resolve($actor, ['page' => [
            'route_name' => 'secretariat.records.create',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
            'title' => 'FORGED TITLE',
            'body' => 'FORGED BODY',
        ]]);

        $assistant = app(NajmHodaSecretariatDraftAssistant::class);
        $preview = $assistant->intercept(
            $actor,
            $page,
            'پیش‌نویس سند بساز | عنوان: گزارش جلسه آب | متن: تصمیم‌های جلسه درباره شبکه آب | نوع: official_report | جهت: internal | محرمانگی: office_members',
            7001
        );

        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());
        $this->assertStringNotContainsString('FORGED', json_encode($page, JSON_UNESCAPED_UNICODE));

        $saved = $assistant->intercept($actor, $page, 'ذخیره پیش‌نویس', 7001);
        $this->assertSame('draft_saved', $saved['status']);
        $this->assertSame(1, SecretariatRecord::query()->count());
        $record = SecretariatRecord::query()->firstOrFail();
        $this->assertSame('draft', $record->status);
        $this->assertNull($record->registry_number);
        $this->assertNull($record->registered_at);
        $this->assertSame('گزارش جلسه آب', $record->title);
    }

    public function test_ordinary_member_cannot_prepare_or_save_a_secretariat_draft(): void
    {
        [$actor, $office] = $this->officeWithMemberRole(1);
        $page = app(NajmHodaPageContextResolver::class)->resolve($actor, ['page' => [
            'route_name' => 'secretariat.records.create',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
        ]]);

        $result = app(NajmHodaSecretariatDraftAssistant::class)->intercept(
            $actor,
            $page,
            'پیش‌نویس سند بساز | عنوان: سند غیرمجاز | متن: نباید ذخیره شود',
            7002
        );

        $this->assertSame('blocked', $result['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());
    }

    public function test_cancel_discards_pending_preview_without_persistence(): void
    {
        [$actor, $office] = $this->officeWithMemberRole(2);
        $page = app(NajmHodaPageContextResolver::class)->resolve($actor, ['page' => [
            'route_name' => 'secretariat.index',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
        ]]);
        $assistant = app(NajmHodaSecretariatDraftAssistant::class);
        $assistant->intercept($actor, $page, 'پیش‌نویس سند بساز | عنوان: تست لغو | متن: متن تست', 7003);
        $cancelled = $assistant->intercept($actor, $page, 'لغو', 7003);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, SecretariatRecord::query()->count());
    }

    private function officeWithMemberRole(int $role): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => 'Draft Office Group '.$role, 'group_type' => '0']);
        GroupUser::query()->create(['group_id'=>$group->id,'user_id'=>$actor->id,'role'=>$role,'status'=>1,'expired'=>null]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'DRAFT-'.$role.'-'.uniqid(),
            'name' => 'Draft Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        return [$actor, $office];
    }
}
