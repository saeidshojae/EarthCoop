<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class LiveReputationRuleRuntimeContractTest extends TestCase
{
    public function test_profile_milestone_rules_have_real_runtime_wiring(): void
    {
        $observerPath = app_path('Observers/ProfileMilestoneReputationObserver.php');
        $provider = file_get_contents(app_path('Providers/EventServiceProvider.php'));

        $this->assertFileExists($observerPath);
        $observer = file_get_contents($observerPath);
        $this->assertStringContainsString('ProfileMilestoneReputationObserver::class', $provider);
        $this->assertStringContainsString("$action . ':user:'", $observer);

        foreach (['profile_photo_uploaded', 'social_links_added', 'documents_uploaded', 'bio_added'] as $action) {
            $this->assertStringContainsString("'{$action}'", $observer);
            $this->assertSame('participation', config("reputation.policy_defaults.{$action}.dimension"));
            $this->assertFalse(config("reputation.policy_defaults.{$action}.convertible"));
            $this->assertSame('once_per_context', config("reputation.policy_defaults.{$action}.repeat_policy"));
        }
    }

    public function test_bid_cancellation_rule_is_wired_to_actual_cancellation_event(): void
    {
        $listenerPath = app_path('Listeners/AwardBidCancellationReputation.php');
        $provider = file_get_contents(app_path('Providers/EventServiceProvider.php'));

        $this->assertFileExists($listenerPath);
        $listener = file_get_contents($listenerPath);
        $this->assertStringContainsString('AwardBidCancellationReputation::class', $provider);
        $this->assertStringContainsString("'bid_canceled'", $listener);
        $this->assertStringContainsString("'bid_canceled:bid:'", $listener);
        $this->assertSame('reliability', config('reputation.policy_defaults.bid_canceled.dimension'));
        $this->assertFalse(config('reputation.policy_defaults.bid_canceled.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.bid_canceled.repeat_policy'));
    }

    public function test_only_confirmed_report_resolutions_drive_report_penalty(): void
    {
        $observerPath = app_path('Observers/ConfirmedReportReputationObserver.php');
        $provider = file_get_contents(app_path('Providers/EventServiceProvider.php'));

        $this->assertFileExists($observerPath);
        $observer = file_get_contents($observerPath);
        $this->assertStringContainsString('ConfirmedReportReputationObserver::class', $provider);
        $this->assertStringContainsString("'report_received'", $observer);
        $this->assertStringContainsString("'resolved'", $observer);
        $this->assertStringContainsString("'resolved_by_group_manager'", $observer);
        $this->assertStringContainsString("'resolved_by_admin'", $observer);
        $this->assertSame('reliability', config('reputation.policy_defaults.report_received.dimension'));
        $this->assertFalse(config('reputation.policy_defaults.report_received.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.report_received.repeat_policy'));
    }

    public function test_fraud_rule_has_explicit_admin_confirmed_runtime_path(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));
        $view = file_get_contents(resource_path('views/admin/system-settings/reputation/index.blade.php'));

        $this->assertStringContainsString('applyConfirmedFraud', $controller);
        $this->assertStringContainsString("'apply_confirmed_fraud'", $controller);
        $this->assertStringContainsString("'fraud'", $controller);
        $this->assertStringContainsString("'moderation.confirmed_fraud'", $controller);
        $this->assertStringContainsString("'fraud:admin-case:'", $controller);
        $this->assertStringContainsString('apply_confirmed_fraud', $view);
        $this->assertStringContainsString('ثبت تقلب تأییدشده', $view);
        $this->assertSame('reliability', config('reputation.policy_defaults.fraud.dimension'));
        $this->assertFalse(config('reputation.policy_defaults.fraud.convertible'));
        $this->assertSame('once_per_context', config('reputation.policy_defaults.fraud.repeat_policy'));
    }

    public function test_only_legacy_election_rules_are_deprecated(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));

        $this->assertStringContainsString("private const DEPRECATED_RULE_KEYS = ['election_candidate', 'election_participated'];", $controller);

        foreach (['profile_photo_uploaded', 'social_links_added', 'documents_uploaded', 'bio_added', 'report_received', 'bid_canceled', 'fraud'] as $action) {
            $this->assertArrayHasKey($action, config('reputation.weights'));
        }
    }
}
