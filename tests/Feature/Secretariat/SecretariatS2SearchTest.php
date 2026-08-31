<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatS2SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_only_records_actor_can_view(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::query()->create(['name' => 'S2 Search Group', 'group_type' => '0']);

        foreach ([[$manager, 3], [$member, 1]] as [$user, $role]) {
            GroupUser::query()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 1,
                'expired' => null,
            ]);
        }

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-SEARCH',
            'name' => 'S2 Search Group Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $records = app(SecretariatRecordService::class);
        $public = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Visible water plan',
            'confidentiality' => 'office_members',
        ]);
        $restricted = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Restricted water plan',
            'confidentiality' => 'restricted',
        ]);

        $search = app(SecretariatSearchService::class);
        $memberBeforeGrant = $search->search($member, ['title' => 'water plan']);
        $this->assertSame([$public->id], $memberBeforeGrant->pluck('id')->all());

        $outsiderResults = $search->search($outsider, ['title' => 'water plan']);
        $this->assertCount(0, $outsiderResults);

        app(SecretariatAclService::class)->grant($restricted, 'user', $outsider->id, $manager);
        $outsiderAfterGrant = $search->search($outsider, ['title' => 'water plan']);
        $this->assertSame([$restricted->id], $outsiderAfterGrant->pluck('id')->all());
    }

    public function test_search_filters_record_type_and_registry_number_deterministically(): void
    {
        $manager = User::factory()->create();
        $group = Group::query()->create(['name' => 'S2 Registry Search Group', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-REG-SEARCH',
            'name' => 'S2 Registry Search Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $records = app(SecretariatRecordService::class);

        $note = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Registered note',
        ]);
        $report = $records->createDraft($office, $manager, [
            'record_type' => 'official_report',
            'title' => 'Registered report',
        ]);
        $registeredNote = $records->register($records->submitForApproval($note, $manager), $manager);
        $records->register($records->submitForApproval($report, $manager), $manager);

        $results = app(SecretariatSearchService::class)->search($manager, [
            'record_type' => 'official_note',
            'registry_number' => (string) $registeredNote->registry_sequence,
        ]);

        $this->assertSame([$registeredNote->id], $results->pluck('id')->all());
    }
}
