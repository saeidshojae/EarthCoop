<?php

namespace Tests\Feature\Legal;

use App\Models\NajmBaharAgreement;
use App\Models\Term;
use App\Services\Legal\LegalDocumentSourceSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentSourceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_captures_terms_parent_and_children_without_modifying_editor_rows(): void
    {
        $root = Term::create(['title' => 'اساسنامه', 'message' => 'مقدمه', 'parent_id' => null]);
        $clause = Term::create(['title' => 'ماده اول', 'message' => 'حقوق اعضا', 'parent_id' => $root->id]);
        Term::create(['title' => 'سند مستقل', 'message' => 'بخش دیگر', 'parent_id' => null]);

        $snapshot = json_decode(app(LegalDocumentSourceSnapshotService::class)->capture('terms', $root->id), true);
        $this->assertSame('earthcoop-legal-document-v1', $snapshot['format']);
        $this->assertSame('اساسنامه', $snapshot['root']['title']);
        $this->assertSame([$clause->id], array_column($snapshot['root']['children'], 'source_id'));
        $this->assertSame('حقوق اعضا', $snapshot['root']['children'][0]['content']);
        $this->assertSame(3, Term::count());
    }

    public function test_it_captures_financial_clauses_in_admin_sort_order(): void
    {
        $root = NajmBaharAgreement::create(['title' => 'توافقنامه مالی', 'content' => 'دیباچه', 'order' => 0]);
        NajmBaharAgreement::create(['title' => 'ماده دوم', 'content' => 'ب', 'parent_id' => $root->id, 'order' => 2]);
        NajmBaharAgreement::create(['title' => 'ماده اول', 'content' => 'الف', 'parent_id' => $root->id, 'order' => 1]);

        $snapshot = json_decode(app(LegalDocumentSourceSnapshotService::class)->capture('najm_bahar_agreements', $root->id), true);
        $this->assertSame(['ماده اول', 'ماده دوم'], array_column($snapshot['root']['children'], 'title'));
        $this->assertSame('دیباچه', $snapshot['root']['content']);
    }
}
