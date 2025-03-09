<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location; // مدل مربوطه را اضافه کنید

class LocationController extends Controller
{
    public function getLocations(Request $request)
    {
        $level = $request->query('level'); // مقدار سطح مکانی
        $parent_id = $request->query('parent_id'); // مقدار ID والد

        // بررسی اعتبار مقادیر ورودی
        if (!$level || !$parent_id) {
            return response()->json(['error' => 'پارامترهای لازم ارسال نشده‌اند'], 400);
        }

        // دریافت مکان‌های وابسته به سطح و والد مشخص‌شده
        $locations = Location::where('level', $level)
                            ->where('parent_id', $parent_id)
                            ->get();

        return response()->json($locations);
    }
}

