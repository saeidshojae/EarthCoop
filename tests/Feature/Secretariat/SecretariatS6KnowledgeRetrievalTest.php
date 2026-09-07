<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatKnowledgeRetrievalService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecretariatS6KnowledgeRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_packets_never_return_confidential_content_without_explicit_acl(): void
    {
        [$manager, $office] = $this->office('S6-KNOW-A', 3);
        $outsider = User::factory()->create();
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'Opaque confidential report',
            'body' => 'knowledge-canary-551902 confidential body',
            'confidentiality' => 'confidential',
        ]);

        $retrieval = app(SecretariatKnowledgeRetrievalService::class);
        $this->assertCount(0, $retrieval->retrieve($outsider, 'knowledge-canary-551902'));
        $this->assertDatabaseMissing('secretariat_audit_events', [
            'record_id' => $record->id,
            'event_type' => 'access_sensitive',
            'actor_id' => $outsider->id,
        ]);

        app(SecretariatAclService::class)->grant($record, 'user', $outsider->id, $manager);
        $packets = $retrieval->retrieve($outsider, 'knowledge-canary-551902');

        $this->assertCount(1, $packets);
        $this->assertSame($record->id, $packets->first()['record_id']);
        $this->assertStringContainsString('knowledge-canary-551902', $packets->first()['excerpt']);
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'event_type' => 'access_sensitive',
            'actor_id' => $outsider->id,
        ]);
    }

    public function test_sensitive_retrieval_audit_stores_fingerprint_not_raw_query(): void
    {
        [$manager, $office] = $this->office('S6-KNOW-B', 3);
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'Sensitive lookup',
            'body' => 'private-query-token-66181',
            'confidentiality' => 'confidential',
        ]);
        app(SecretariatAclService::class)->grant($record, 'user', $manager->id, $manager);

        app(SecretariatKnowledgeRetrievalService::class)->retrieve($manager, 'private-query-token-66181');

        $event = DB::table('secretariat_audit_events')
            ->where('record_id', $record->id)
            ->where('event_type', 'access_sensitive')
            ->latest('id')
            ->firstOrFail();
        $metadata = json_decode((string) $event->metadata, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('knowledge_retrieval', $metadata['channel']);
        $this->assertSame(hash('sha256', 'private-query-token-66181'), $metadata['query_fingerprint']);
        $this->assertStringNotContainsString('private-query-token-66181', (string) $event->metadata);
    }

    public function test_knowledge_packets_enforce_per_record_and_total_character_budgets(): void
    {
        [$manager, $office] = $this->office('S6-KNOW-C', 3);
        $records = app(SecretariatRecordService::class);

        foreach (range(1, 3) as $i) {
            $records->createDraft($office, $manager, [
                'record_type' => 'official_note',
                'direction' => 'internal',
                'title' => "Budget packet {$i}",
                'body' => 'budget-token ' . str_repeat("content-{$i}-", 200),
                'confidentiality' => 'office_members',
            ]);
        }

        $packets = app(SecretariatKnowledgeRetrievalService::class)->retrieve(
            $manager,
            'budget-token',
            [],
            3,
            256,
            512,
        );

        $this->assertGreaterThanOrEqual(1, $packets->count());
        $this->assertLessThanOrEqual(2, $packets->count());
        $this->assertLessThanOrEqual(512, $packets->sum(fn (array $packet) => mb_strlen($packet['excerpt'])));
        foreach ($packets as $packet) {
            $this->assertLessThanOrEqual(256, mb_strlen($packet['excerpt']));
            $this->assertTrue($packet['truncated']);
        }
    }

    public function test_natural_language_question_finds_document_by_meaningful_term_when_full_phrase_does_not_match(): void
    {
        [$manager, $office] = $this->office('S6-KNOW-NL', 3);
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'formal_decision',
            'direction' => 'internal',
            'title' => 'تصمیم درباره تخصیص آب کشاورزی',
            'body' => 'سهم آب باغ‌های پایین‌دست برای فصل آینده بازتنظیم می‌شود.',
            'confidentiality' => 'office_members',
        ]);

        $packets = app(SecretariatKnowledgeRetrievalService::class)->retrieve(
            $manager,
            'لطفاً در اسناد دبیرخانه بگو درباره آب چه تصمیمی گرفته شده است؟'
        );

        $this->assertContains($record->id, $packets->pluck('record_id')->all());
        $this->assertGreaterThan(0, $packets->firstWhere('record_id', $record->id)['retrieval_score']);
    }

    public function test_keyword_fanout_still_cannot_surface_foreign_office_record(): void
    {
        [$member, $office] = $this->office('S6-KNOW-SAFE', 1);
        [$foreignManager, $foreignOffice] = $this->office('S6-KNOW-FOREIGN', 3);

        app(SecretariatRecordService::class)->createDraft($office, $member, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'یادداشت آب محلی',
            'body' => 'آب محله در دستور بررسی است.',
            'confidentiality' => 'office_members',
        ]);
        $hidden = app(SecretariatRecordService::class)->createDraft($foreignOffice, $foreignManager, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'سند محرمانه دفتر دیگر درباره آب',
            'body' => 'آب و برنامه خصوصی دفتر دیگر',
            'confidentiality' => 'office_members',
        ]);

        $packets = app(SecretariatKnowledgeRetrievalService::class)->retrieve(
            $member,
            'در اسناد دبیرخانه درباره آب چه چیزی داریم؟'
        );

        $this->assertNotContains($hidden->id, $packets->pluck('record_id')->all());
    }

    public function test_retrieval_rejects_empty_or_oversized_query_before_search(): void
    {
        $actor = User::factory()->create();
        $retrieval = app(SecretariatKnowledgeRetrievalService::class);

        try {
            $retrieval->retrieve($actor, '   ');
            $this->fail('Empty retrieval query was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('query', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $retrieval->retrieve($actor, str_repeat('x', 2001));
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
