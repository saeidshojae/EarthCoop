<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class ActivateController extends Controller
{
    /**
     * Display activation settings page
     */
    public function index()
    {
        $setting = Setting::find(1);
        return view('admin.system-settings.activate.index', compact('setting'));
    }

    /**
     * Update activation settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'invation_status' => 'nullable|boolean',
            'finger_status' => 'nullable|boolean',
            'expire_invation_time' => 'nullable|integer|min:1|max:8760', // 1 hour to 1 year
            'count_invation' => 'nullable|integer|min:0',
        ], [
            'expire_invation_time.integer' => 'زمان انقضا باید عدد باشد',
            'expire_invation_time.min' => 'زمان انقضا باید حداقل 1 ساعت باشد',
            'expire_invation_time.max' => 'زمان انقضا نمی‌تواند بیشتر از 8760 ساعت (یک سال) باشد',
            'count_invation.integer' => 'تعداد کد دعوت باید عدد باشد',
            'count_invation.min' => 'تعداد کد دعوت نمی‌تواند منفی باشد',
        ]);

        $setting = Setting::find(1);
        
        $inputs = [
            'invation_status' => $request->has('invation_status') ? 1 : 0,
            'finger_status' => $request->has('finger_status') ? 1 : 0,
            'expire_invation_time' => $validated['expire_invation_time'] ?? $setting->expire_invation_time ?? 72,
            'count_invation' => $validated['count_invation'] ?? $setting->count_invation ?? 0,
        ];

        $setting->update($inputs);

        return back()->with('success', 'تنظیمات فعال‌سازی با موفقیت به‌روزرسانی شد.');
    }
}
