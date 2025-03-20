<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location; // مدل مربوطه را اضافه کنید

class LocationController extends Controller
{
    public function getLocations(Request $request)
    {
        $parent_id = $request->query('parent_id');

        // بررسی اعتبار مقدار ورودی
        if (!$parent_id) {
            return response()->json(['error' => 'شناسه والد ارسال نشده است'], 400);
        }

        // دریافت مکان‌های وابسته به والد مشخص‌شده
        $locations = Location::where('parent_id', $parent_id)
                            ->orderBy('name')
                            ->get();

        return response()->json($locations);
    }
}

