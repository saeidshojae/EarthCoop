<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class DeprecatedElectionRuleAdminGuardContractTest extends TestCase
{
    public function test_deprecated_reputation_rules_cannot_be_reactivated_from_admin_policy_form(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));
        $view = file_get_contents(resource_path('views/admin/system-settings/reputation/index.blade.php'));

        $this->assertStringContainsString('private const DEPRECATED_RULE_KEYS = [', $controller);
        $this->assertStringContainsString("'election_candidate'", $controller);
        $this->assertStringContainsString("'election_participated'", $controller);
        $this->assertStringContainsString('in_array($key, self::DEPRECATED_RULE_KEYS, true)', $controller);
        $this->assertStringContainsString('$rule->active = false;', $controller);
        $this->assertStringContainsString('$rule->convertible = false;', $controller);

        $this->assertStringContainsString('$deprecatedRuleKeys', $view);
        $this->assertStringContainsString('قاعده منسوخ؛ فقط برای سابقه نگهداری می‌شود', $view);
        $this->assertStringContainsString("{{ \$isDeprecated ? 'disabled' : '' }}", $view);
    }
}
