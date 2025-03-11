<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\OccupationalField;
use App\Models\ExperienceField;
use App\Models\Location;

class ProfileController extends Controller
{
    // نمایش صفحه پروفایل (نمایش اطلاعات غیر قابل تغییر از قبیل هویتی)
    public function showProfile()
    {
        $user = auth()->user();
        return view('profile.profile', compact('user'));
    }

    // نمایش فرم ویرایش اطلاعات تغییرپذیر (صنف، تخصص، مکان و عکس)
    public function editModifiable()
    {
        $user = auth()->user();
        // دریافت لیست‌های اولیه جهت انتخاب‌های چندگانه
        $occupationalFields = OccupationalField::whereNull('parent_id')->get();
        $experienceFields   = ExperienceField::whereNull('parent_id')->get();
        $continents = Location::where('level', 'continent')->get();
        return view('profile.edit', compact('user', 'occupationalFields', 'experienceFields', 'continents'));
    }

    // پردازش به‌روز رسانی اطلاعات تغییرپذیر
    public function updateModifiable(Request $request)
    {
        // اعتبارسنجی اطلاعات جدید
        $request->validate([
            'occupational_fields' => 'required|array',
            'experience_fields'   => 'required|array',
            'continent_id'        => 'required|exists:locations,id',
            'country_id'          => 'required|exists:locations,id',
            'province_id'         => 'required|exists:locations,id',
            'county_id'           => 'required|exists:locations,id',
            'section_id'          => 'required|exists:locations,id',
            'city_id'             => 'required|exists:locations,id',
            'region_id'           => 'required|exists:locations,id',
            'neighborhood_id'     => 'required|exists:locations,id',
            'avatar'              => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $user = auth()->user();

        // به‌روز رسانی ارتباط‌های چند به چند صنف و تخصص
        $user->occupationalFields()->sync($request->occupational_fields);
        $user->experienceFields()->sync($request->experience_fields);

        // به‌روز رسانی مکان: جمع‌آوری شناسه‌های انتخاب شده در آرایه
        $locationIds = [
            $request->continent_id,
            $request->country_id,
            $request->province_id,
            $request->county_id,
            $request->section_id,
            $request->city_id,
            $request->region_id,
            $request->neighborhood_id,
        ];
        if ($request->filled('street_id')) {
            $locationIds[] = $request->street_id;
        }
        if ($request->filled('alley_id')) {
            $locationIds[] = $request->alley_id;
        }
        $user->locations()->sync($locationIds);

        // پردازش آپلود عکس پروفایل در صورت وجود
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = time().'_'.$file->getClientOriginalName();
            $filePath = public_path('images/avatars');
            $file->move($filePath, $filename);
            $user->avatar = 'images/avatars/' . $filename;
            $user->save();
        }

        return redirect()->route('profile.edit')->with('success', 'اطلاعات تغییرپذیر پروفایل به‌روز شد.');
    }
}
