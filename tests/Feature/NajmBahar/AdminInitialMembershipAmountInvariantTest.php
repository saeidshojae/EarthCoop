<?php

namespace Tests\Feature\NajmBahar;

use App\Http\Controllers\Admin\NajmBaharDashboardController;
use App\Http\Controllers\Admin\NajmBaharSettingsController;
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

    public function test_advanced_settings_cannot_mutate_constitutional_issuance_but_keep_operational_controls_mutable(): void
    {
        $settings = Setting::singleton();
        $settings->forceFill([
            'najm_bahar_amounts_in_gol' => true,
            'najm_bahar_initial_amount' => 55_555,
            'najm_bahar_initial_active_percentage' => 0,
            'najm_bahar_initial_active_type' => 'fixed_amount',
            'najm_bahar_initial_active_fixed_amount' => 0,
            'najm_bahar_auto_activation_enabled' => false,
            'najm_bahar_auto_activation_period' => 'yearly',
            'najm_bahar_auto_activation_amount' => 100,
            'reputation_conversion_enabled' => false,
            'reputation_to_gol_ratio' => 1,
        ])->save();

        $request = Request::create('/admin/najm-bahar/settings', 'PUT', [
            'najm_bahar_initial_amount' => '777.77',
            'najm_bahar_initial_active_percentage' => 50,
            'najm_bahar_initial_active_type' => 'percentage',
            'najm_bahar_initial_active_fixed_amount' => '5.00',
            'najm_bahar_auto_activation_enabled' => 1,
            'najm_bahar_auto_activation_period' => 'monthly',
            'najm_bahar_auto_activation_amount' => '20.00',
            'reputation_conversion_enabled' => 1,
            'reputation_to_gol_ratio' => 3,
        ]);

        app(NajmBaharSettingsController::class)->update($request);

        $fresh = $settings->fresh();
        $this->assertSame(55_555, (int) $fresh->najm_bahar_initial_amount);
        $this->assertSame(0, (int) $fresh->najm_bahar_initial_active_percentage);
        $this->assertSame('fixed_amount', $fresh->najm_bahar_initial_active_type);
        $this->assertSame(0, (int) $fresh->najm_bahar_initial_active_fixed_amount);

        $this->assertTrue((bool) $fresh->najm_bahar_auto_activation_enabled);
        $this->assertSame('monthly', $fresh->najm_bahar_auto_activation_period);
        $this->assertSame(2_000, (int) $fresh->najm_bahar_auto_activation_amount);
        $this->assertTrue((bool) $fresh->reputation_conversion_enabled);
        $this->assertSame(3, (int) $fresh->reputation_to_gol_ratio);
    }

    public function test_admin_runtime_makes_constitutional_controls_read_only_without_touching_adjacent_capabilities(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmBaharDashboardController.php'));
        $settingsController = file_get_contents(app_path('Http/Controllers/Admin/NajmBaharSettingsController.php'));
        $dashboard = file_get_contents(resource_path('views/admin/najm-bahar/dashboard.blade.php'));
        $settingsView = file_get_contents(resource_path('views/admin/najm-bahar/settings/index.blade.php'));
        $appJs = file_get_contents(resource_path('js/app.js'));
        $runtimePath = resource_path('js/najm-bahar-admin-settings.js');

        $this->assertStringContainsString('NajmBaharConstitution::initialMembershipGol()', $controller);
        $this->assertStringNotContainsString('$settings->najm_bahar_initial_amount', $controller);

        // Existing page markup and neighboring capabilities must remain intact.
        $this->assertStringContainsString("route('admin.najm-bahar.threshold.update')", $dashboard);
        $this->assertStringContainsString("route('admin.najm-bahar.settings.index')", $dashboard);
        $this->assertStringContainsString("route('admin.najm-bahar.membership-split.update')", $dashboard);
        $this->assertStringContainsString("route('admin.najm-bahar.settings.update')", $settingsView);
        $this->assertStringContainsString('name="najm_bahar_auto_activation_enabled"', $settingsView);
        $this->assertStringContainsString('name="najm_bahar_auto_activation_period"', $settingsView);
        $this->assertStringContainsString('name="najm_bahar_auto_activation_amount"', $settingsView);
        $this->assertStringContainsString('name="reputation_conversion_enabled"', $settingsView);
        $this->assertStringContainsString('name="reputation_to_gol_ratio"', $settingsView);

        // The legacy malformed inline script must be stripped at render time rather than rewriting the Blade.
        $this->assertStringContainsString('stripLegacySettingsScript', $settingsController);
        $this->assertStringContainsString('preg_replace', $settingsController);
        $this->assertStringContainsString('function toggleActiveType', $settingsController);

        $this->assertStringContainsString('./najm-bahar-admin-settings.js', $appJs);
        $this->assertFileExists($runtimePath);

        $runtime = file_get_contents($runtimePath);
        foreach ([
            'najm_bahar_initial_amount',
            'najm_bahar_initial_active_percentage',
            'najm_bahar_initial_active_type',
            'najm_bahar_initial_active_fixed_amount',
        ] as $field) {
            $this->assertStringContainsString($field, $runtime);
        }
        $this->assertStringContainsString('element.disabled = true', $runtime);
        $this->assertStringContainsString('window.toggleAutoActivation', $runtime);
        $this->assertStringContainsString('window.toggleReputationConversion', $runtime);
        $this->assertStringContainsString('window.updateReputationPreview', $runtime);
    }
}
