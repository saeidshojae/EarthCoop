<?php

namespace Tests\Feature\NajmHoda;

use App\Models\OccupationalField;
use App\Services\NajmHoda\FounderOps\FounderReferenceApprovalCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderReferenceApprovalCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_normalized_duplicate_is_high_risk(): void
    {
        OccupationalField::create(['name'=>'مهندسی نرم افزار','status'=>1]);
        $pending = OccupationalField::create(['name'=>'مهندسی نرم‌افزار','status'=>0]);

        $candidate = collect(app(FounderReferenceApprovalCandidateService::class)->candidates())
            ->first(fn (array $item): bool => $item['type']==='occupational' && $item['id']===$pending->id);

        $this->assertIsArray($candidate);
        $this->assertSame('high', $candidate['duplicate_risk']);
        $this->assertSame('review_duplicate', $candidate['recommendation']);
        $this->assertNotEmpty($candidate['similar']);
    }

    public function test_unrelated_pending_reference_is_likely_unique(): void
    {
        OccupationalField::create(['name'=>'کشاورزی','status'=>1]);
        $pending = OccupationalField::create(['name'=>'مهندسی هوافضا','status'=>0]);

        $candidate = collect(app(FounderReferenceApprovalCandidateService::class)->candidates())
            ->first(fn (array $item): bool => $item['type']==='occupational' && $item['id']===$pending->id);

        $this->assertSame('low', $candidate['duplicate_risk']);
        $this->assertSame('likely_unique', $candidate['recommendation']);
    }
}
