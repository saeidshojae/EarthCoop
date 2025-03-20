<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OccupationalField;
use App\Models\ExperienceField;
use Illuminate\Support\Facades\Auth;

class Step2Controller extends Controller
{
    /**
     * نمایش فرم مرحله ۲
     */
    public function show()
    {
        // دریافت فیلدهای صنفی و تخصصی بدون والد (سطح ۱)
        $occupationalFields = OccupationalField::whereNull('parent_id')->get();
        $experienceFields = ExperienceField::whereNull('parent_id')->get();
        
        // ارسال داده‌ها به ویو
        return view('auth.register_step2', compact('occupationalFields', 'experienceFields'));
    }

    /**
     * ذخیره داده‌های ارسال شده از فرم مرحله ۲
     */
    public function store(Request $request)
    {
        // اعتبارسنجی داده‌های ورودی
        $request->validate([
            'occupational_fields' => 'nullable|array',
            'occupational_fields.*' => 'exists:occupational_fields,id',
            'experience_fields' => 'nullable|array',
            'experience_fields.*' => 'exists:experience_fields,id',
        ]);

        // دریافت کاربر لاگین‌شده
        $user = Auth::user();

        // اگر کاربر وجود نداشت، خطا برگردانید
        if (!$user) {
            return redirect()->back()->with('error', 'کاربر یافت نشد.');
        }

        // ذخیره فیلدهای صنفی و تخصصی کاربر
        $user->occupationalFields()->sync($request->occupational_fields ?? []);
        $user->experienceFields()->sync($request->experience_fields ?? []);

        // انتقال به مرحله بعدی
        return redirect('/register/step3')->with('success', 'اطلاعات با موفقیت ذخیره شدند.');
    }

    /**
     * دریافت فیلدهای فرزند به صورت Ajax
     */
    public function getChildren(Request $request)
    {
        // اعتبارسنجی اولیه داده‌های ورودی
        $request->validate([
            'parent_id' => 'required|integer',
            'field_type' => 'required|string|in:occupational,experience', // تعیین نوع فیلد
        ]);

        $parentId = $request->input('parent_id');
        $fieldType = $request->input('field_type');

        // بررسی اینکه parent_id در جدول درست وجود دارد
        if ($fieldType === 'occupational') {
            if (!OccupationalField::where('id', $parentId)->exists()) {
                return response()->json(['error' => 'شناسه صنف نامعتبر است.'], 400);
            }
            $children = OccupationalField::where('parent_id', $parentId)->get();
        } elseif ($fieldType === 'experience') {
            if (!ExperienceField::where('id', $parentId)->exists()) {
                return response()->json(['error' => 'شناسه تخصص نامعتبر است.'], 400);
            }
            $children = ExperienceField::where('parent_id', $parentId)->get();
        }

        // برگرداندن پاسخ به صورت JSON
        return response()->json(['data' => $children]);
    }
}