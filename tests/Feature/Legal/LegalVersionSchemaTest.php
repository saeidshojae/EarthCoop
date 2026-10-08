<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegalVersionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_version_tables_are_additive_and_contain_snapshot_and_consent_keys(): void
    {
        foreach (['legal_documents', 'legal_document_versions', 'legal_document_acceptances'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertTrue(Schema::hasColumns('legal_document_versions', [
            'legal_document_id', 'version_label', 'language',
            'content_snapshot', 'content_sha256', 'published_at', 'status',
        ]));

        $this->assertTrue(Schema::hasColumns('legal_document_acceptances', [
            'user_id', 'legal_document_version_id', 'accepted_at', 'context',
        ]));

        // Existing content and historical timestamp columns must remain untouched.
        $this->assertTrue(Schema::hasTable('terms'));
        $this->assertTrue(Schema::hasTable('najm_bahar_agreements'));
        $this->assertTrue(Schema::hasColumn('users', 'terms_accepted_at'));
        $this->assertTrue(Schema::hasColumn('users', 'najm_bahar_agreement_accepted_at'));
        $this->assertSame(0, DB::table('legal_documents')->count());
        $this->assertSame(0, DB::table('legal_document_acceptances')->count());
    }
}
