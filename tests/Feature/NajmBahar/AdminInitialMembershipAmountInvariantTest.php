<?php

namespace Tests\Feature\NajmBahar;

use App\Http\Controllers\Admin\NajmBaharDashboardController;
use App\Models\Setting;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminInitialMembershipAmountInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_initial_amount_endpoint_cannot_mutate_constitutional_membership_issuance_amount(): void
    {
        $settings = Setting::singleton();
        $settings->forceFill([
            'najm_bahar_initial_amount' => 55_555,
            'najm_bahar_amounts_in_gol' => true,
        ])->save();

        $request = Request::create(
            '/admin/najm-bahar/dashboard/initial-amount',
            'POST',
            ['najm_bahar_initial_amount' => '777.77']
        );

        app(NajmBaharDashboardController::class)->updateInitialAmount($request);

        $this->assertSame(
            55_555,
            (int) $settings->fresh()->najm_bahar_initial_amount,
            'Legacy admin settings must not be able to mutate the constitutional membership issuance amount.'
        );
        $this->assertSame(1_000_000, NajmBaharConstitution::initialMembershipGol());
    }

    public function test_admin_dashboard_uses_constitutional_amount_without_disturbing_adjacent_controls(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmBaharDashboardController.php'));
        $view = file_get_contents(resource_path('views/admin/najm-bahar/dashboard.blade.php'));

        $this->assertStringContainsString('NajmBaharConstitution::initialMembershipGol()', $controller);
        $this->assertStringNotContainsString('$settings->najm_bahar_initial_amount', $controller);

        // Regression guards: this fix must not remove or rename unrelated admin capabilities.
        $this->assertStringContainsString("route('admin.najm-bahar.threshold.update')", $view);
        $this->assertStringContainsString("route('admin.najm-bahar.settings.index')", $view);
        $this->assertStringContainsString("route('admin.najm-bahar.membership-split.update')", $view);
    }
}
