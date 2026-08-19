<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaSecretariatGroundedResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatGroundedResponderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_records_visible_to_the_real_actor(): void
    {
        [$manager, $office] = $this->office('NH-S6-GROUNDED', 3);
        $outsider = User::factory()->create();
        $records = app(SecretariatRecordService::class);

        $visible = $records->createDraft($office, $manager, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'گزارش رسمی برنامه آب شهری',
            'body' => 'در این سند درباره برنامه تامین آب و زمان‌بندی اجرا توضیح داده شده است.',
            'confidentiality' => 'office_members',
        ]);

        $confidential = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'یادداشت محرمانه آب',
            'body' => 'canary-secret-water-8821',
            'confidentiality' => 'confidential',
        ]);

        app(SecretariatAclService::class)->grant($confidential, 'user', $manager->id, $manager);

        $responder = app(NajmHodaSecretariatGroundedResponder::class);
        $managerResponse = $responder->respond($manager, 'در اسناد رسمی دبیرخانه درباره آب چه داریم؟');

        $this->assertIsArray($managerResponse);
        $this->assertTrue($managerResponse['grounded']);
        $this->assertStringContainsString($visible->title, $managerResponse['message']);
        $this->assertStringContainsString($confidential->title, $managerResponse['message']);

        $outsiderResponse = $responder->respond($outsider, 'در اسناد رسمی دبیرخانه درباره آب چه داریم؟');
        $this->assertIsArray($outsiderResponse);
        $this->assertStringNotContainsString($visible->title, $outsiderResponse['message']);
        $this->assertStringNotContainsString($confidential->title, $outsiderResponse['message']);
        $this->assertStringNotContainsString('canary-secret-water-8821', $outsiderResponse['message']);
    }

    public function test_it_ignores_normal_chat_without_secretariat_intent(): void
    {
        $actor = User::factory()->create();
        $response = app(NajmHodaSecretariatGroundedResponder::class)->respond(
            $actor,
            'سلام، امروز چه کارهایی می‌توانم در این صفحه انجام بدهم؟'
        );

        $this->assertNull($response);
    }

    private function office(string $code, int $role): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => $role,
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

        return [$actor, $office];
    }
}
