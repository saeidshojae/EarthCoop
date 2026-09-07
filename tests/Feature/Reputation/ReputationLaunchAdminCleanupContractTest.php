<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReputationLaunchAdminCleanupContractTest extends TestCase
{
    public function test_dead_placeholder_rules_are_not_bootstrapped_as_live_policy(): void
    {
        $config = file_get_contents(config_path('reputation.php'));

        foreach ([
            'profile_photo_uploaded',
            'social_links_added',
            'documents_uploaded',
            'bio_added',
            'report_received',
            'bid_canceled',
            'fraud',
        ] as $deadRule) {
            $this->assertStringNotContainsString("'{$deadRule}' =>", $config, $deadRule . ' must not be bootstrapped as a live rule');
        }
    }

    public function test_all_historical_or_unwired_rules_are_protected_as_deprecated(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));

        foreach ([
            'election_candidate',
            'election_participated',
            'profile_photo_uploaded',
            'social_links_added',
            'documents_uploaded',
            'bio_added',
            'report_received',
            'bid_canceled',
            'fraud',
        ] as $deprecatedRule) {
            $this->assertStringContainsString("'{$deprecatedRule}'", $controller);
        }

        $this->assertStringContainsString("'archived' => ['label' => 'آرشیو / منسوخ'", $controller);
    }

    public function test_runtime_fallback_reads_canonical_policy_defaults(): void
    {
        $service = file_get_contents(app_path('Services/ReputationService.php'));

        $this->assertStringContainsString('config("reputation.policy_defaults.{$actionKey}.dimension"', $service);
        $this->assertStringContainsString('config("reputation.policy_defaults.{$actionKey}.convertible"', $service);
    }

    public function test_membership_fee_reconciliation_command_exists_and_is_safe_by_default(): void
    {
        $path = app_path('Console/Commands/ReconcileMembershipFeeReputation.php');
        $this->assertFileExists($path);

        $command = file_get_contents($path);
        $this->assertStringContainsString('reputation:reconcile-membership-fees', $command);
        $this->assertStringContainsString('{--dry-run', $command);
        $this->assertStringContainsString('{--user=', $command);
        $this->assertStringContainsString('membership_fee_paid:user:', $command);
        $this->assertStringContainsString("->where('metadata->type', 'membership_fee')", $command);
        $this->assertStringContainsString("->where('status', 'completed')", $command);
    }

    public function test_creation_caps_have_explicit_repair_path_without_overwriting_admin_values(): void
    {
        $path = app_path('Console/Commands/RepairReputationPolicyDefaults.php');
        $this->assertFileExists($path);

        $command = file_get_contents($path);
        $this->assertStringContainsString('reputation:repair-policy-defaults', $command);
        $this->assertStringContainsString("'post_created' => 50", $command);
        $this->assertStringContainsString("'comment_created' => 20", $command);
        $this->assertStringContainsString('whereNull', $command);
        $this->assertStringContainsString('{--dry-run', $command);
    }

    public function test_repeat_policy_is_not_exposed_as_a_fake_admin_control(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));
        $view = file_get_contents(resource_path('views/admin/system-settings/reputation/index.blade.php'));

        $this->assertStringNotContainsString("'repeat_policy' => 'sometimes|array'", $controller);
        $this->assertStringNotContainsString('name="repeat_policy[', $view);
        $this->assertStringContainsString('هویت رویداد', $view);
    }

    public function test_admin_view_uses_complete_deprecated_catalogue_and_archive_explanation(): void
    {
        $view = file_get_contents(resource_path('views/admin/system-settings/reputation/index.blade.php'));

        $this->assertStringContainsString('$deprecatedRuleKeys', $view);
        $this->assertStringContainsString('برای سابقه و ممیزی نگهداری می‌شوند', $view);
        $this->assertStringContainsString('قابل فعال‌سازی یا تبدیل نیستند', $view);
    }
}
