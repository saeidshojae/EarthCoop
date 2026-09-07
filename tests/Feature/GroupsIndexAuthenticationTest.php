<?php

namespace Tests\Feature;

use Tests\TestCase;

class GroupsIndexAuthenticationTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_groups_index(): void
    {
        $response = $this->get(route('groups.index'));

        $response->assertRedirect(route('login'));
    }
}
