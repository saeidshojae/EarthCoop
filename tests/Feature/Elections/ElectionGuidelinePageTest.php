<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class ElectionGuidelinePageTest extends TestCase
{
    public function test_guideline_is_public_and_contains_the_core_e0_user_contract(): void
    {
        $this->withoutVite();

        $response = $this->get(route('elections.guideline'));

        $response->assertOk()
            ->assertSee('انتخابات در EarthCoop چگونه کار می‌کند؟')
            ->assertSee('بدون نامزدی رسمی')
            ->assertSee('حریم خصوصی')
            ->assertSee('پذیرش مسئولیت')
            ->assertSee('اعتراض و بازبینی');
    }

    public function test_systemic_ballot_links_to_the_guideline_page(): void
    {
        $source = file_get_contents(resource_path('views/groups/modals/election_modal.blade.php'));

        $this->assertStringContainsString("route('elections.guideline')", $source);
        $this->assertStringContainsString('شیوه‌نامه انتخابات', $source);
    }
}
