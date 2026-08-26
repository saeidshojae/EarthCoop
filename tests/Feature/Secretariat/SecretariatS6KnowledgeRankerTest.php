<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Contracts\SecretariatKnowledgeRanker;
use App\Modules\Secretariat\Services\DeterministicSecretariatKnowledgeRanker;
use App\Modules\Secretariat\Services\SecretariatKnowledgeRetrievalService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SecretariatS6KnowledgeRankerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranker_receives_only_packets_already_authorized_for_actor(): void
    {
        [$member, $memberOffice] = $this->office('S6-RANK-A', 1);
        [$foreignManager, $foreignOffice] = $this->office('S6-RANK-B', 3);

        $visible = $this->record($memberOffice, $member, 'Shared retrieval token', 'shared-rank-token visible');
        $hidden = $this->record($foreignOffice, $foreignManager, 'Hidden retrieval token', 'shared-rank-token hidden');

        $spy = new class implements SecretariatKnowledgeRanker {
            public array $seenIds = [];

            public function rank(string $query, Collection $packets): Collection
            {
                $this->seenIds = $packets->pluck('record_id')->map(fn ($id) => (int) $id)->all();
                return $packets;
            }
        };
        $this->app->instance(SecretariatKnowledgeRanker::class, $spy);

        $packets = app(SecretariatKnowledgeRetrievalService::class)->retrieve($member, 'shared-rank-token');

        $this->assertContains($visible->id, $spy->seenIds);
        $this->assertNotContains($hidden->id, $spy->seenIds);
        $this->assertSame([$visible->id], $packets->pluck('record_id')->all());
    }

    public function test_deterministic_ranker_prefers_title_and_subject_matches_over_body_only_matches(): void
    {
        $ranker = new DeterministicSecretariatKnowledgeRanker();
        $packets = collect([
            [
                'record_id' => 1,
                'title' => 'General report',
                'subject' => 'Background',
                'registry_number' => 'A-1',
                'excerpt' => 'water allocation appears only in a long body',
            ],
            [
                'record_id' => 2,
                'title' => 'Water allocation plan',
                'subject' => 'Agriculture',
                'registry_number' => 'A-2',
                'excerpt' => 'short body',
            ],
            [
                'record_id' => 3,
                'title' => 'General note',
                'subject' => 'Water allocation',
                'registry_number' => 'A-3',
                'excerpt' => 'short body',
            ],
        ]);

        $ranked = $ranker->rank('water allocation', $packets);

        $this->assertSame([2, 3, 1], $ranked->pluck('record_id')->all());
        $this->assertGreaterThan($ranked[1]['retrieval_score'], $ranked[0]['retrieval_score']);
        $this->assertGreaterThan($ranked[2]['retrieval_score'], $ranked[1]['retrieval_score']);
    }

    public function test_ranker_contract_binding_resolves_to_safe_deterministic_fallback(): void
    {
        $ranker = app(SecretariatKnowledgeRanker::class);

        $this->assertInstanceOf(DeterministicSecretariatKnowledgeRanker::class, $ranker);
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

    private function record($office, User $actor, string $title, string $body)
    {
        return app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => $title,
            'body' => $body,
            'confidentiality' => 'office_members',
        ]);
    }
}
