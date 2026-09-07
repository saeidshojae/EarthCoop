<?php

namespace Tests\Feature\CommunityStories;

use Tests\TestCase;

class CommunityStoryNavigationTest extends TestCase
{
    public function test_member_story_authoring_is_discoverable_in_desktop_and_mobile_navigation(): void
    {
        $desktop = file_get_contents(resource_path('views/components/user-dropdown-unified.blade.php'));
        $mobile = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        foreach ([$desktop, $mobile] as $navigation) {
            $this->assertStringContainsString("route('community-stories.index')", $navigation);
            $this->assertStringContainsString('داستان من در EarthCoop', $navigation);
        }

        $this->assertStringContainsString('مشارکت', $mobile);
        $this->assertStringContainsString('مشارکت من', $desktop);
    }
}
