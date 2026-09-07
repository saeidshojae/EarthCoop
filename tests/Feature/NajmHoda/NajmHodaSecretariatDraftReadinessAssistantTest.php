<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatDraftReadinessAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatDraftReadinessAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_is_read_only_and_only_surfaces_authorized_formal_evidence(): void
    {
        [$actor, $otherManager, $office] = $this->context('S7-READY');
        $records = app(SecretariatRecordService::class);

        $draft = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'برنامه مدیریت آب روستا',
            'body' => null,
            'confidentiality' => 'office_members',
        ])->load('currentVersion');

        $formal = $records->createDraft($office, $actor, [
            'record_type' => 'resolution',
            'direction' => 'internal',
            'title' => 'مصوبه رسمی برنامه مدیریت آب روستا',
            'subject' => 'برنامه آب',
            'summary' => 'چارچوب مصوب برای مدیریت آب روستا',
            'body' => 'این مصوبه چارچوب رسمی اقدامات مدیریت آب را ثبت می‌کند.',
            'confidentiality' => 'office_members',
            'source_type' => 'manual',
        ]);
        $formal = $records->register($records->submitForApproval($formal, $actor), $actor);

        $hidden = $records->createDraft($office, $otherManager, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'گزارش محرمانه برنامه مدیریت آب روستا',
            'body' => 'این محتوای محرمانه نباید در پیشنهاد Evidence ظاهر شود.',
            'confidentiality' => 'confidential',
            'source_type' => 'manual',
        ]);
        $hidden = $records->register($records->submitForApproval($hidden, $otherManager), $otherManager);

        $pageContext = app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.records.show',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_record',
                'resource_id' => $draft->id,
                'title' => 'FORGED TITLE',
                'body' => 'FORGED BODY',
            ],
        ]);

        $beforeRecordCount = SecretariatRecord::query()->count();
        $beforeVersionCount = $draft->versions()->count();

        $response = app(NajmHodaSecretariatDraftReadinessAssistant::class)->intercept(
            $actor,
            $pageContext,
            'این پیش‌نویس را بررسی کن؛ چه چیزهایی کم دارد و چه شواهدی مرتبط است؟'
        );

        $this->assertIsArray($response);
        $this->assertSame('secretariat_readiness', $response['agent']);
        $this->assertTrue($response['read_only']);
        $this->assertContains('متن/بدنه نسخه جاری خالی است.', $response['blockers']);
        $this->assertNotEmpty($response['quality_suggestions']);

        $evidenceIds = collect($response['evidence'])->pluck('record_id')->all();
        $this->assertContains($formal->id, $evidenceIds);
        $this->assertNotContains($hidden->id, $evidenceIds);
        $this->assertStringNotContainsString('این محتوای محرمانه', $response['message']);
        $this->assertStringNotContainsString('FORGED BODY', $response['message']);

        $this->assertSame($beforeRecordCount, SecretariatRecord::query()->count());
        $this->assertSame($beforeVersionCount, $draft->fresh()->versions()->count());
        $this->assertNull($draft->fresh()->registry_number);
    }

    private function context(string $code): array
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        foreach ([$actor, $other] as $user) {
            GroupUser::query()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 3,
                'status' => 1,
                'expired' => null,
            ]);
        }
        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => $code . ' Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$actor, $other, $office];
    }
}
