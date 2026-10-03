<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class ProfileMemberBaseTemporalPresentationTest extends TestCase
{
    public function test_profile_member_base_uses_temporal_components_for_birth_and_membership_dates(): void
    {
        $path = 'resources/views/profile/profile-member-base.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertSame(2, substr_count($contents, '<x-temporal.date'));
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
