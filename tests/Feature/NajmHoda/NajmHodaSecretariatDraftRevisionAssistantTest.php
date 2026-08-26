<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaSecretariatDraftRevisionAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatDraftRevisionAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_preview_has_zero_side_effect_and_confirmation_appends_exact_version(): void
    {
        [$actor, $record] = $this->draftWithManager('S7-REV-A');
        $assistant = app(NajmHodaSecretariatDraftRevisionAssistant::class);
        $page = $this->recordPage($record->id);
        $beforeVersions = $record->versions()->count();
        $beforeCurrent = (int) $record->current_version_id;

        $preview = $assistant->intercept(
            $actor,
            $page,
            'اصلاح پیش‌نویس | عنوان: گزارش اصلاح‌شده | متن: متن نسخه دوم و دقیق',
            701
        );

        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertSame($beforeVersions, $record->fresh()->versions()->count());
        $this->assertSame($beforeCurrent, (int) $record->fresh()->current_version_id);

        $saved = $assistant->intercept($actor, $page, 'ذخیره اصلاحات', 701);
        $fresh = $record->fresh()->load('currentVersion');

        $this->assertSame('revision_saved', $saved['status']);
        $this->assertSame('draft', $fresh->status);
        $this->assertSame($beforeVersions + 1, $fresh->versions()->count());
        $this->assertSame('گزارش اصلاح‌شده', $fresh->title);
        $this->assertSame('متن نسخه دوم و دقیق', $fresh->currentVersion->body);
        $this->assertNull($fresh->registry_number);
    }

    public function test_stale_preview_cannot_overwrite_a_newer_draft_version(): void
    {
        [$actor, $record] = $this->draftWithManager('S7-REV-B');
        $assistant = app(NajmHodaSecretariatDraftRevisionAssistant::class);
        $page = $this->recordPage($record->id);

        $assistant->intercept($actor, $page, 'اصلاح پیش‌نویس | متن: پیشنهاد نجم هدا', 702);

        app(SecretariatRecordService::class)->editDraft(
            $record->fresh(),
            $actor,
            ['body' => 'ویرایش انسانی جدیدتر'],
            'Concurrent human edit'
        );
        $versionsAfterHumanEdit = $record->fresh()->versions()->count();

        $result = $assistant->intercept($actor, $page, 'ذخیره اصلاحات', 702);
        $fresh = $record->fresh()->load('currentVersion');

        $this->assertSame('stale_preview', $result['status']);
        $this->assertSame($versionsAfterHumanEdit, $fresh->versions()->count());
        $this->assertSame('ویرایش انسانی جدیدتر', $fresh->currentVersion->body);
    }

    public function test_unauthorized_user_and_non_draft_record_are_not_revision_targets(): void
    {
        [$manager, $record] = $this->draftWithManager('S7-REV-C');
        $outsider = User::factory()->create();
        $assistant = app(NajmHodaSecretariatDraftRevisionAssistant::class);
        $page = $this->recordPage($record->id);

        $this->assertNull($assistant->intercept(
            $outsider,
            $page,
            'اصلاح پیش‌نویس | متن: نباید مجاز باشد',
            703
        ));

        app(SecretariatRecordService::class)->submitForApproval($record->fresh(), $manager);
        $this->assertNull($assistant->intercept(
            $manager,
            $page,
            'اصلاح پیش‌نویس | متن: بعد از submit نباید مستقیم ویرایش شود',
            704
        ));
    }

    public function test_cancel_discards_revision_preview_without_new_version(): void
    {
        [$actor, $record] = $this->draftWithManager('S7-REV-D');
        $assistant = app(NajmHodaSecretariatDraftRevisionAssistant::class);
        $page = $this->recordPage($record->id);
        $before = $record->versions()->count();

        $assistant->intercept($actor, $page, 'اصلاح پیش‌نویس | متن: متن موقت', 705);
        $result = $assistant->intercept($actor, $page, 'لغو', 705);

        $this->assertSame('cancelled', $result['status']);
        $this->assertSame($before, $record->fresh()->versions()->count());
    }

    private function draftWithManager(string $code): array
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
            'title' => 'گزارش اولیه',
            'body' => 'متن نسخه اول',
            'confidentiality' => 'office_members',
        ]);
        return [$actor, $record];
    }

    private function recordPage(int $recordId): array
    {
        return [
            'route_name' => 'secretariat.records.show',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_record',
            'resource_id' => $recordId,
            'title' => 'FORGED BROWSER TITLE',
            'body' => 'FORGED BROWSER BODY',
        ];
    }
}
