<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExperienceField;

class ExperienceFieldController extends Controller
{
    public function getFields(Request $request)
    {
        $parent_id = $request->query('parent_id');

        // بررسی اعتبار مقدار ورودی
        if (!$parent_id) {
            return response()->json(['error' => 'شناسه والد ارسال نشده است'], 400);
        }

        $fields = ExperienceField::where('parent_id', $parent_id)
                                ->orderBy('name')
                                ->get();

        return response()->json($fields);
    }
} 