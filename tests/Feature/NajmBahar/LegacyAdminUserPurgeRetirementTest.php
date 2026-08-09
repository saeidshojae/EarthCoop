<?php

namespace Tests\Feature\NajmBahar;

use Tests\TestCase;

class LegacyAdminUserPurgeRetirementTest extends TestCase
{
    public function test_destructive_admin_user_purge_helpers_are_physically_retired(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/UserController.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('purgeNajmBaharAccounts', $source);
        $this->assertStringNotContainsString('decrementSystemSubAccountBalance', $source);
        $this->assertStringContainsString(
            'Direct admin user deletion is retired. Resolve the safe admin controller boundary.',
            $source
        );
        $this->assertStringContainsString(
            'Direct admin bulk deletion is retired. Resolve the safe admin controller boundary.',
            $source
        );
    }
}
