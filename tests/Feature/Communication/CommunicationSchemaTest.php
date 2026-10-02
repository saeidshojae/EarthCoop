<?php

namespace Tests\Feature\Communication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunicationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_communication_tables_and_columns_exist(): void
    {
        $tables = [
            'communication_templates',
            'communication_template_versions',
            'communication_sender_identities',
            'communication_rules',
            'communication_rule_schedules',
            'communication_campaigns',
            'communication_runs',
            'communications',
            'communication_recipients',
            'communication_delivery_attempts',
            'communication_preferences',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('communication_templates', [
            'key', 'name', 'category', 'classification', 'is_active',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_template_versions', [
            'communication_template_id', 'version', 'locale', 'subject', 'body',
            'variables_schema', 'communication_sender_identity_id', 'published_at',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_sender_identities', [
            'key', 'email', 'display_name', 'reply_to', 'purpose', 'system_identity_key',
            'is_active', 'is_default',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_rules', [
            'key', 'name', 'trigger_type', 'event_key', 'condition_definition',
            'audience_definition', 'communication_template_id', 'communication_sender_identity_id',
            'classification', 'priority', 'delay_seconds', 'is_active',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_rule_schedules', [
            'communication_rule_id', 'frequency', 'schedule_definition', 'timezone', 'timezone_mode',
            'next_run_at', 'last_run_at',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_campaigns', [
            'name', 'status', 'audience_definition', 'communication_template_id',
            'communication_sender_identity_id', 'classification', 'priority', 'scheduled_at',
            'confirmed_at', 'paused_at', 'cancelled_at',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_runs', [
            'communication_rule_id', 'communication_campaign_id', 'run_key', 'status',
            'matched_count', 'eligible_count', 'suppressed_count', 'invalid_count',
            'queued_count', 'sent_count', 'failed_count', 'started_at', 'finished_at',
        ]));

        $this->assertTrue(Schema::hasColumns('communications', [
            'source_type', 'source_id', 'communication_rule_id', 'communication_campaign_id',
            'communication_run_id', 'communication_template_version_id', 'classification',
            'priority', 'context_snapshot', 'status', 'deduplication_key', 'scheduled_at',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_recipients', [
            'communication_id', 'user_id', 'email', 'locale', 'communication_template_version_id',
            'preference_decision', 'status', 'queued_at', 'sent_at', 'failed_at',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_delivery_attempts', [
            'communication_recipient_id', 'attempt_number', 'provider', 'provider_message_id',
            'status', 'failure_class', 'failure_code', 'failure_message', 'started_at', 'finished_at',
        ]));

        $this->assertTrue(Schema::hasColumns('communication_preferences', [
            'user_id', 'topic_key', 'channel', 'preference', 'frequency',
        ]));

        $this->assertFalse(Schema::hasTable('communication_digest_items'));
    }

    public function test_deduplication_key_is_enforced_by_a_unique_database_index(): void
    {
        $indexes = collect(Schema::getIndexes('communications'));

        $dedupeUniqueIndex = $indexes->first(function (array $index): bool {
            $columns = $index['columns'] ?? [];
            $isUnique = (bool) ($index['unique'] ?? false);

            return $isUnique && $columns === ['deduplication_key'];
        });

        $this->assertNotNull($dedupeUniqueIndex, 'communications.deduplication_key must have a unique database index.');
    }

    public function test_delivery_attempts_and_recipient_template_versions_have_required_foreign_keys(): void
    {
        $recipientForeignKeys = collect(Schema::getForeignKeys('communication_recipients'));
        $attemptForeignKeys = collect(Schema::getForeignKeys('communication_delivery_attempts'));

        $this->assertTrue($recipientForeignKeys->contains(function (array $foreign): bool {
            return ($foreign['columns'] ?? []) === ['communication_template_version_id']
                && ($foreign['foreign_table'] ?? $foreign['foreignTable'] ?? null) === 'communication_template_versions';
        }), 'Recipients must reference the exact immutable template version used.');

        $this->assertTrue($attemptForeignKeys->contains(function (array $foreign): bool {
            return ($foreign['columns'] ?? []) === ['communication_recipient_id']
                && ($foreign['foreign_table'] ?? $foreign['foreignTable'] ?? null) === 'communication_recipients';
        }), 'Delivery attempts must belong to a recipient and remain append-only rows.');
    }
}
