<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\Province;
use App\Models\County;
use App\Models\District;
use App\Models\Settlement;
use App\Models\Locality;
use App\Models\Neighborhood;
use App\Models\Street;
use App\Models\Alley;

class LocationController extends Controller
{
    public function getCountries($continentID) {
        $countries = Country::where('continent_id', $continentID)->get();
        return response()->json($countries);
    }
    
    public function getProvinces($countryID) {
        $provinces = Province::where('country_id', $countryID)->get();
        return response()->json($provinces);
    }

    public function getCounties($province_id)
    {
        $counties = County::where('province_id', $province_id)->get();
        return response()->json($counties);
    }

    public function getDistricts($county_id)
    {
        $districts = District::where('county_id', $county_id)->get();
        return response()->json($districts);
    }

    public function getSettlements($district_id)
    {
        $settlements = Settlement::where('district_id', $district_id)->get();
        return response()->json($settlements);
    }

    public function getLocalities($settlement_id)
    {
        $localities = Locality::where('settlement_id', $settlement_id)->get();
        return response()->json($localities);
    }

    public function getNeighborhoods($locality_id)
    {
        $neighborhoods = Neighborhood::where('locality_id', $locality_id)->get();
        return response()->json($neighborhoods);
    }

    public function getStreets($neighborhood_id)
    {
        $streets = Street::where('neighborhood_id', $neighborhood_id)->get();
        return response()->json($streets);
    }

    public function getAlleys($street_id)
    {
        $alleys = Alley::where('street_id', $street_id)->get();
        return response()->json($alleys);
    }
}