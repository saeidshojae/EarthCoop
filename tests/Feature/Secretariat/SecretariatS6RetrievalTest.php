<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordAccessQuery;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecretariatS6RetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_prefilter_excludes_ordinary_records_that_record_policy_would_reject(): void
    {
        [$actor, $group, $groupOffice] = $this->groupOffice('S6-PREFILTER', 1);
        $visible = $this->draft($groupOffice, $actor, 'Visible group record');

        $projectOwner = User::factory()->create();
        $project = Project::query()->create([
            'owner_type' => User::class,
            'owner_id' => $projectOwner->id,
            'title' => 'S6 private project',
            'summary' => 'S6 project',
            'project_type' => 'service',
            'project_visibility' => 'private',
            'project_stage' => 'idea',
            'status' => 'draft',
        ]);
        $projectOffice = app(SecretariatOfficeService::class)->create([
            'code' => 'S6-PROJECT',
            'name' => 'S6 Project Office',
            'office_type' => 'project',
            'scope_type' => 'najm_bahar_project',
            'scope_id' => $project->id,
        ]);
        $hidden = $this->draft($projectOffice, $projectOwner, 'Project-office ordinary record');

        $query = SecretariatRecord::query();
        app(SecretariatRecordAccessQuery::class)->apply($query, $actor);
        $candidateIds = $query->orderBy('id')->pluck('id')->all();

        $this->assertContains($visible->id, $candidateIds);
        $this->assertNotContains($hidden->id, $candidateIds);
        $this->assertFalse($actor->can('view', $hidden));
        $this->assertTrue($projectOwner->can('view', $hidden));
    }

    public function test_unauthorized_newer_records_cannot_starve_accessible_results_at_limit_boundary(): void
    {
        [$actor, , $groupOffice] = $this->groupOffice('S6-LIMIT', 1);
        $expected = [];
        foreach (range(1, 3) as $i) {
            $expected[] = $this->draft($groupOffice, $actor, "Accessible result {$i}")->id;
        }

        $owner = User::factory()->create();
        $project = Project::query()->create([
            'owner_type' => User::class,
            'owner_id' => $owner->id,
            'title' => 'S6 noisy project',
            'summary' => 'S6 noisy project',
            'project_type' => 'service',
            'project_visibility' => 'private',
            'project_stage' => 'idea',
            'status' => 'draft',
        ]);
        $projectOffice = app(SecretariatOfficeService::class)->create([
            'code' => 'S6-NOISE',
            'name' => 'S6 Noise Office',
            'office_type' => 'project',
            'scope_type' => 'najm_bahar_project',
            'scope_id' => $project->id,
        ]);
        foreach (range(1, 35) as $i) {
            $this->draft($projectOffice, $owner, "Inaccessible newer noise {$i}");
        }

        $results = app(SecretariatSearchService::class)->search($actor, [], 3);

        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expected, $results->pluck('id')->all());
    }

    public function test_leadership_and_sensitive_candidates_match_current_policy_contract(): void
    {
        [$member, $group, $office] = $this->groupOffice('S6-AUTH', 1);
        $manager = User::factory()->create();
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);

        $leadership = $this->draft($office, $manager, 'Leadership oversight record', 'leadership');
        $restricted = $this->draft($office, $manager, 'Restricted via group ACL', 'restricted');

        $memberCandidates = SecretariatRecord::query();
        app(SecretariatRecordAccessQuery::class)->apply($memberCandidates, $member);
        $this->assertContains($leadership->id, $memberCandidates->pluck('id')->all());
        $this->assertTrue($member->can('view', $leadership));

        $managerCandidates = SecretariatRecord::query();
        app(SecretariatRecordAccessQuery::class)->apply($managerCandidates, $manager);
        $this->assertContains($leadership->id, $managerCandidates->pluck('id')->all());

        app(SecretariatAclService::class)->grant($restricted, 'group', $group->id, $manager);
        $memberCandidatesAfterGrant = SecretariatRecord::query();
        app(SecretariatRecordAccessQuery::class)->apply($memberCandidatesAfterGrant, $member);
        $this->assertContains($restricted->id, $memberCandidatesAfterGrant->pluck('id')->all());
        $this->assertTrue($member->can('view', $restricted));
    }

    public function test_text_retrieval_never_exposes_sensitive_body_without_acl(): void
    {
        [$manager, $group, $office] = $this->groupOffice('S6-TEXT', 3);
        $outsider = User::factory()->create();
        $secret = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'Opaque title',
            'body' => 'semantic-canary-74291 must remain private',
            'confidentiality' => 'confidential',
        ]);

        $search = app(SecretariatSearchService::class);
        $this->assertCount(0, $search->search($outsider, ['text' => 'semantic-canary-74291']));

        app(SecretariatAclService::class)->grant($secret, 'user', $outsider->id, $manager);
        $afterGrant = $search->search($outsider, ['text' => 'semantic-canary-74291']);
        $this->assertSame([$secret->id], $afterGrant->pluck('id')->all());
    }

    public function test_extended_filters_cover_party_source_case_confidentiality_and_current_version_text(): void
    {
        [$manager, $group, $office] = $this->groupOffice('S6-FILTERS', 3);
        $records = app(SecretariatRecordService::class);

        $target = $records->createDraft($office, $manager, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'Agriculture assessment',
            'subject' => 'Water allocation',
            'summary' => 'Seasonal field review',
            'body' => 'deep-body-token-9137',
            'confidentiality' => 'leadership',
            'source_type' => 'group',
            'source_id' => $group->id,
        ]);
        $target = $records->register($records->submitForApproval($target, $manager), $manager);

        DB::table('secretariat_parties')->insert([
            'record_id' => $target->id,
            'role' => 'sender',
            'party_type' => 'external_organization',
            'display_name' => 'Mazandaran Water Cooperative',
            'organization_name' => 'Blue Orchard Network',
            'email' => 'water@example.test',
            'created_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $case = app(SecretariatCaseService::class)->create($office, $manager, [
            'title' => 'S6 Water Case',
            'confidentiality' => 'leadership',
        ]);
        app(SecretariatCaseService::class)->addRecord($case, $target, $manager, 'evidence');

        $noise = $this->draft($office, $manager, 'Unrelated ordinary note', 'office_members');

        $filters = [
            'office_id' => $office->id,
            'record_type' => 'official_report',
            'confidentiality' => 'leadership',
            'party' => 'Blue Orchard',
            'source_type' => 'group',
            'source_id' => $group->id,
            'case_id' => $case->id,
            'text' => 'deep-body-token-9137',
        ];
        $results = app(SecretariatSearchService::class)->search($manager, $filters);

        $this->assertSame([$target->id], $results->pluck('id')->all());
        $this->assertNotContains($noise->id, $results->pluck('id')->all());
    }

    public function test_source_filter_requires_known_morph_token_and_source_id_pair(): void
    {
        $actor = User::factory()->create();
        $search = app(SecretariatSearchService::class);

        try {
            $search->search($actor, ['source_type' => 'not-a-real-secretariat-source']);
            $this->fail('Unknown source morph token was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source_type', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $search->search($actor, ['source_id' => 42]);
    }

    private function groupOffice(string $code, int $role): array
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

        return [$actor, $group, $office];
    }

    private function draft($office, User $actor, string $title, string $confidentiality = 'office_members'): SecretariatRecord
    {
        return app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => $title,
            'confidentiality' => $confidentiality,
        ]);
    }
}
