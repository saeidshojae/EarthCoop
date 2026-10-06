<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class ProfileMemberInvitationsTemporalPresentationTest extends TestCase
{
    public function test_member_invitation_timestamps_use_temporal_datetime_component(): void
    {
        $path = 'resources/views/profile/member-invitations.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertSame(4, substr_count($contents, '<x-temporal.date-time'));
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
