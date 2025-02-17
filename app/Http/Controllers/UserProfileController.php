<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Province;
use App\Models\County;
use App\Models\District;
use App\Models\Settlement;
use App\Models\Locality;
use App\Models\Neighborhood;
use App\Models\Street;
use App\Models\Alley;
use App\Models\IndustrialField;
use App\Models\Specialization;

class UserProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $continents = Continent::all();
        $countries = Country::all();
        $provinces = Province::all();
        $counties = County::all();
        $districts = District::all();
        $settlements = Settlement::all();
        $localities = Locality::all();
        $neighborhoods = Neighborhood::all();
        $streets = Street::all();
        $alleys = Alley::all();
        $industrialFields = IndustrialField::all();
        $specializations = Specialization::all();
        return view('profile.edit', compact('user', 'continents', 'countries', 'provinces', 'counties', 'districts', 'settlements', 'localities', 'neighborhoods', 'streets', 'alleys', 'industrialFields', 'specializations'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
            'national_id' => 'required|string|max:255|unique:users,national_id,' . $user->id,
            'phone' => 'required|string|max:255|unique:users,phone,' . $user->id,
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'industrial_fields' => 'required|array',
            'specializations' => 'required|array',
            'continent' => 'required|exists:continents,id',
            'country' => 'required|exists:countries,id',
            'province' => 'required|exists:provinces,id',
            'county' => 'required|exists:counties,id',
            'district' => 'required|exists:districts,id',
            'settlement' => 'required|exists:settlements,id',
            'locality' => 'required|exists:localities,id',
            'neighborhood' => 'required|exists:neighborhoods,id',
            'street' => 'required|exists:streets,id',
            'alley' => 'required|exists:alleys,id',
        ]);

        $user->update($validated);
        $user->industrialFields()->sync($request->industrial_fields);
        $user->specializations()->sync($request->specializations);
        $user->continent_id = $request->continent;
        $user->country_id = $request->country;
        $user->province_id = $request->province;
        $user->county_id = $request->county;
        $user->district_id = $request->district;
        $user->settlement_id = $request->settlement;
        $user->locality_id = $request->locality;
        $user->neighborhood_id = $request->neighborhood;
        $user->street_id = $request->street;
        $user->alley_id = $request->alley;
        $user->save();

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }
}