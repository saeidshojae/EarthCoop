<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatPageContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_record_route_is_described_without_exposing_browser_resource_payload(): void
    {
        $actor = User::factory()->create();

        $context = app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.records.show',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_record',
                'resource_id' => 999999,
                'title' => 'FORGED SECRET TITLE',
                'body' => 'FORGED SECRET BODY',
            ],
        ]);

        $this->assertSame('سند دبیرخانه', $context['page_label']);
        $this->assertSame('secretariat_record', $context['page_kind']);
        $this->assertContains('view_secretariat_record', $context['available_capabilities']);
        $this->assertNull($context['resource_id']);
        $this->assertNull($context['resource']);
        $this->assertStringNotContainsString('FORGED', json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    public function test_observer_can_ground_najm_hoda_on_leadership_record_but_not_restricted_record(): void
    {
        $manager = User::factory()->create();
        $observer = User::factory()->create();
        $group = Group::query()->create(['name' => 'Oversight group', 'group_type' => '0']);

        foreach ([[$manager, 3], [$observer, 0]] as [$user, $role]) {
            GroupUser::query()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 1,
                'expired' => null,
            ]);
        }

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'OVERSIGHT-HODA',
            'name' => 'Oversight Secretariat',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $records = app(SecretariatRecordService::class);
        $leadership = $records->createDraft($office, $manager, [
            'record_type' => 'financial_record',
            'title' => 'Management finance report',
            'confidentiality' => 'leadership',
        ]);
        $restricted = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Protected personal material',
            'confidentiality' => 'restricted',
        ]);

        $resolver = app(NajmHodaPageContextResolver::class);
        $visible = $resolver->resolve($observer, [
            'page' => [
                'route_name' => 'secretariat.records.show',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_record',
                'resource_id' => $leadership->id,
            ],
        ]);
        $hidden = $resolver->resolve($observer, [
            'page' => [
                'route_name' => 'secretariat.records.show',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_record',
                'resource_id' => $restricted->id,
            ],
        ]);

        $this->assertSame($leadership->id, $visible['resource_id']);
        $this->assertSame('leadership', $visible['resource']['confidentiality']);
        $this->assertNull($hidden['resource_id']);
        $this->assertNull($hidden['resource']);
    }

    public function test_secretariat_directory_and_case_routes_have_specific_page_identity(): void
    {
        $resolver = app(NajmHodaPageContextResolver::class);

        $directory = $resolver->resolve(null, [
            'page' => ['route_name' => 'secretariat.directory', 'module' => 'secretariat'],
        ]);
        $case = $resolver->resolve(null, [
            'page' => ['route_name' => 'secretariat.cases.show', 'module' => 'secretariat'],
        ]);

        $this->assertSame('secretariat_directory', $directory['page_kind']);
        $this->assertSame('فهرست دبیرخانه‌ها', $directory['page_label']);
        $this->assertSame('secretariat_case', $case['page_kind']);
        $this->assertSame('پرونده دبیرخانه', $case['page_label']);
    }
}
