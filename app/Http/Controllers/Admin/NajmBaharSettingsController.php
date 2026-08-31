<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Helpers\BaharMoney;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use Illuminate\Http\Request;

class NajmBaharSettingsController extends Controller
{
    /**
     * نمایش صفحه تنظیمات نجم بهار
     */
    public function index()
    {
        $settings = Setting::firstNajmBaharSettings();

        // Constitutional issuance is immutable. Keep legacy persisted fields
        // untouched for compatibility, but never present them as policy truth.
        $settings->setAttribute('najm_bahar_initial_amount', NajmBaharConstitution::initialMembershipGol());
        $settings->setAttribute('najm_bahar_initial_active_percentage', 0);
        $settings->setAttribute('najm_bahar_initial_active_type', 'fixed_amount');
        $settings->setAttribute('najm_bahar_initial_active_fixed_amount', 0);

        return view('admin.najm-bahar.settings.index', compact('settings'));
    }

    /**
     * ذخیره تنظیمات عملیاتی نجم بهار.
     *
     * مبلغ صدور اولیه و وضعیت Active اولیه از قانون اساسی می‌آیند و عمداً
     * در write-set این endpoint نیستند. سایر کنترل‌های عملیاتی حفظ می‌شوند.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'najm_bahar_auto_activation_enabled' => 'nullable|boolean',
            'najm_bahar_auto_activation_period' => 'required_if:najm_bahar_auto_activation_enabled,1|in:monthly,yearly',
            'najm_bahar_auto_activation_amount' => 'nullable|regex:/^\d+(\.\d{1,2})?$/',
            'reputation_conversion_enabled' => 'nullable|boolean',
            'reputation_to_gol_ratio' => 'required|integer|min:1',
        ]);

        // تبدیل مقدار فعال‌سازی خودکار به گل
        if (isset($validated['najm_bahar_auto_activation_amount'])) {
            $validated['najm_bahar_auto_activation_amount'] = BaharMoney::parseToGol(
                $validated['najm_bahar_auto_activation_amount']
            );
        }

        // تبدیل checkbox به boolean
        $validated['najm_bahar_auto_activation_enabled'] = $request->has('najm_bahar_auto_activation_enabled');
        $validated['reputation_conversion_enabled'] = $request->has('reputation_conversion_enabled');

        $settings = Setting::firstOrCreate(['id' => 1]);
        $settings->update($validated);

        return redirect()->route('admin.najm-bahar.settings.index')
            ->with('success', 'تنظیمات عملیاتی نجم بهار با موفقیت ذخیره شد. مقادیر صدور اولیه طبق قانون اساسی ثابت هستند.');
    }
}
