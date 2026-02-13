<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Helpers\BaharMoney;
use Illuminate\Http\Request;

class NajmBaharSettingsController extends Controller
{
    /**
     * نمایش صفحه تنظیمات نجم بهار
     */
    public function index()
    {
        $settings = Setting::firstNajmBaharSettings();

        return view('admin.najm-bahar.settings.index', compact('settings'));
    }

    /**
     * ذخیره تنظیمات نجم بهار
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'najm_bahar_initial_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'najm_bahar_initial_active_percentage' => 'required|integer|min:0|max:100',
            'najm_bahar_initial_active_type' => 'required|in:percentage,fixed_amount',
            'najm_bahar_initial_active_fixed_amount' => 'nullable|regex:/^\d+(\.\d{1,2})?$/',
            'najm_bahar_auto_activation_enabled' => 'nullable|boolean',
            'najm_bahar_auto_activation_period' => 'required_if:najm_bahar_auto_activation_enabled,1|in:monthly,yearly',
            'najm_bahar_auto_activation_amount' => 'nullable|regex:/^\d+(\.\d{1,2})?$/',
            'reputation_conversion_enabled' => 'nullable|boolean',
            'reputation_to_gol_ratio' => 'required|integer|min:1',
        ]);

        // تبدیل مقدار اولیه به گل
        $validated['najm_bahar_initial_amount'] = BaharMoney::parseToGol(
            $validated['najm_bahar_initial_amount']
        );

        // تبدیل مبلغ ثابت اکتیو به گل
        if (isset($validated['najm_bahar_initial_active_fixed_amount'])) {
            $validated['najm_bahar_initial_active_fixed_amount'] = BaharMoney::parseToGol(
                $validated['najm_bahar_initial_active_fixed_amount']
            );
        }

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
            ->with('success', 'تنظیمات نجم بهار با موفقیت ذخیره شد.');
    }
}
