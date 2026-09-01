<?php

namespace Tests\Feature;

use Tests\TestCase;

class NajmBaharMembershipUiContractTest extends TestCase
{
    public function test_membership_source_runtime_loads_before_the_dynamic_payment_form_exists(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "const hasMembershipFeeUi = Boolean(document.querySelector('#membershipFeeModal, #payMembershipForm'));",
            $app
        );
        $this->assertStringContainsString(
            'if (hasMembershipFeeUi) importFeature(() => import("./najm-bahar-membership-source.js"), "Najm Bahar membership source");',
            $app
        );
    }

    public function test_membership_runtime_makes_the_modal_body_independently_scrollable(): void
    {
        $runtime = file_get_contents(resource_path('js/najm-bahar-membership-source.js'));

        $this->assertStringContainsString("modal.style.overflowY = 'auto';", $runtime);
        $this->assertStringContainsString("panel.style.maxHeight = 'calc(100dvh - 2rem)';", $runtime);
        $this->assertStringContainsString("content.style.overflowY = 'auto';", $runtime);
        $this->assertStringContainsString("content.style.overscrollBehavior = 'contain';", $runtime);
    }
}
