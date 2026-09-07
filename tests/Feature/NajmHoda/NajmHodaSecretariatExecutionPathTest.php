<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaSecretariatGroundedResponder;
use App\Services\NajmHoda\NajmHodaInteractionBoundaryService;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Runtime\NajmHodaCrossModuleCapabilityOrchestratorService;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use App\Services\NajmHoda\Runtime\NajmHodaResourceAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NajmHodaSecretariatExecutionPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_secretariat_question_is_answered_before_legacy_llm_route(): void
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => 'S6 Runtime Knowledge', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 1,
            'status' => 1,
            'expired' => null,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S6-RUNTIME-KNOW',
            'name' => 'S6 Runtime Knowledge Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'گزارش رسمی آب محله',
            'body' => 'برنامه آب محله در سه مرحله اجرا خواهد شد.',
            'confidentiality' => 'office_members',
        ]);

        $boundary = Mockery::mock(NajmHodaInteractionBoundaryService::class);
        $boundary->shouldNotReceive('classify');

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldNotReceive('orchestrate');

        $resource = Mockery::mock(NajmHodaResourceAuthorizationService::class);
        $resource->shouldNotReceive('authorize');

        $legacy = Mockery::mock(NajmHodaOrchestrator::class);
        $legacy->shouldNotReceive('route');

        $service = new NajmHodaExecutionService(
            $boundary,
            $runtime,
            $resource,
            null,
            null,
            null,
            null,
            null,
            app(NajmHodaSecretariatGroundedResponder::class),
        );

        $result = $service->executeChat(
            $legacy,
            'در اسناد رسمی دبیرخانه درباره برنامه آب چه نوشته شده؟',
            ['user_id' => $actor->id]
        );

        $this->assertTrue($result['success']);
        $this->assertSame('secretariat_knowledge', $result['agent']);
        $this->assertTrue($result['grounded']);
        $this->assertSame('secretariat', $result['knowledge_source']);
        $this->assertStringContainsString('گزارش رسمی آب محله', $result['message']);
        $this->assertArrayHasKey('request_id', $result);
    }
}
