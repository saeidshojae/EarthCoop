<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReputationLatestBranchIntegrationContractTest extends TestCase
{
    public function test_latest_launch_branch_contains_r6_reputation_user_surfaces(): void
    {
        $summaryService = app_path('Services/ParticipationPointSummaryService.php');
        $publicProfile = resource_path('views/profile/profile-member.blade.php');
        $history = resource_path('views/history/index.blade.php');

        $this->assertFileExists($summaryService);
        $this->assertFileExists(resource_path('views/profile/profile-member-base.blade.php'));
        $this->assertFileExists(resource_path('views/history/index-base.blade.php'));

        $this->assertStringContainsString(
            'publicReputationSummary',
            file_get_contents($summaryService)
        );

        $this->assertStringContainsString(
            "@extends('profile.profile-member-base')",
            file_get_contents($publicProfile)
        );

        $historySource = file_get_contents($history);
        $this->assertStringContainsString("@extends('history.index-base')", $historySource);
        $this->assertStringContainsString('تبدیل امتیاز مشارکت به بهار', $historySource);
        $this->assertStringContainsString("route('reputation.conversion.convert')", $historySource);
    }
}
