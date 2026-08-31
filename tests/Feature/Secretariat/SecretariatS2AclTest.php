<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SecretariatS2AclTest extends TestCase
{
    use RefreshDatabase;

    public function test_restricted_record_uses_explicit_acl_and_regrant_preserves_history(): void
    {
        $manager = User::factory()->create(['is_admin' => 1]);
        $reader = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-ACL',
            'name' => 'S2 ACL Office',
            'office_type' => 'central',
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Restricted note',
            'confidentiality' => 'restricted',
        ]);

        $this->assertFalse($reader->can('view', $record));

        $acl = app(SecretariatAclService::class);
        $first = $acl->grant($record, 'user', $reader->id, $manager);
        $this->assertTrue($reader->can('view', $record));

        $acl->revoke($first, $manager);
        $this->assertFalse($reader->can('view', $record));

        $second = $acl->grant($record, 'user', $reader->id, $manager);
        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue($reader->can('view', $record));
        $this->assertSame(2, $record->aclEntries()->count());
        $this->assertNotNull($first->fresh()->revoked_at);
    }

    public function test_acl_grant_cannot_be_rewritten_outside_controlled_revocation(): void
    {
        $manager = User::factory()->create(['is_admin' => 1]);
        $reader = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-ACL-IMMUTABLE',
            'name' => 'S2 ACL Immutability Office',
            'office_type' => 'central',
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Restricted immutable grant',
            'confidentiality' => 'restricted',
        ]);
        $entry = app(SecretariatAclService::class)->grant($record, 'user', $reader->id, $manager);

        try {
            $entry->forceFill([
                'expires_at' => now()->subMinute(),
                'metadata' => ['rewritten' => true],
                'revoked_at' => now(),
            ])->save();
            $this->fail('ACL grant was rewritten outside the revocation service.');
        } catch (LogicException) {
            $fresh = $entry->fresh();
            $this->assertNull($fresh->expires_at);
            $this->assertNull($fresh->metadata);
            $this->assertNull($fresh->revoked_at);
            $this->assertTrue($reader->can('view', $record));
        }
    }

    public function test_acl_history_prevents_draft_hard_delete(): void
    {
        $manager = User::factory()->create(['is_admin' => 1]);
        $reader = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-ACL-DELETE',
            'name' => 'S2 ACL Delete Office',
            'office_type' => 'central',
        ]);
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Draft with access history',
            'confidentiality' => 'restricted',
        ]);
        $entry = app(SecretariatAclService::class)->grant($record, 'user', $reader->id, $manager);

        try {
            $records->deleteDraft($record);
            $this->fail('Draft with ACL history was hard-deleted.');
        } catch (LogicException) {
            $this->assertDatabaseHas('secretariat_records', ['id' => $record->id]);
            $this->assertDatabaseHas('secretariat_acl_entries', ['id' => $entry->id]);
        }
    }

    public function test_confidential_access_can_be_audited_without_exposing_record_to_ungranted_user(): void
    {
        $manager = User::factory()->create(['is_admin' => 1]);
        $reader = User::factory()->create();
        $other = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-CONF',
            'name' => 'S2 Confidential Office',
            'office_type' => 'central',
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_report',
            'title' => 'Confidential report',
            'confidentiality' => 'confidential',
        ]);

        $acl = app(SecretariatAclService::class);
        $acl->grant($record, 'user', $reader->id, $manager);

        $this->assertTrue($reader->can('view', $record));
        $this->assertFalse($other->can('view', $record));

        $acl->auditSensitiveAccess($record, $reader, ['surface' => 'feature_test']);

        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'actor_id' => $reader->id,
            'event_type' => 'access_sensitive',
        ]);
    }
}
