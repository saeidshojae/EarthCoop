<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * واکشی کشورها بر اساس قاره
     */
    public function getCountries($continentId)
    {
        $countries = Location::where('parent_id', $continentId)->get();
        return response()->json($countries);
    }

    /**
     * واکشی استان‌ها بر اساس کشور
     */
    public function getProvinces($countryId)
    {
        $provinces = Location::where('parent_id', $countryId)->get();
        return response()->json($provinces);
    }

    /**
     * واکشی شهرستان‌ها بر اساس استان
     */
    public function getCounties($provinceId)
    {
        $counties = Location::where('parent_id', $provinceId)->get();
        return response()->json($counties);
    }

    /**
     * واکشی بخش‌ها بر اساس شهرستان
     */
    public function getDistricts($countyId)
    {
        $districts = Location::where('parent_id', $countyId)->get();
        return response()->json($districts);
    }

    /**
     * واکشی شهرها/دهستان‌ها بر اساس بخش
     */
    public function getSettlements($districtId)
    {
        $settlements = Location::where('parent_id', $districtId)->get();
        return response()->json($settlements);
    }

    /**
     * واکشی روستاها/مناطق بر اساس شهر/دهستان
     */
    public function getLocalities($settlementId)
    {
        $localities = Location::where('parent_id', $settlementId)->get();
        return response()->json($localities);
    }

    /**
     * واکشی محله‌ها بر اساس روستا/منطقه
     */
    public function getNeighborhoods($localityId)
    {
        $neighborhoods = Location::where('parent_id', $localityId)->get();
        return response()->json($neighborhoods);
    }

    /**
     * واکشی خیابان‌ها بر اساس محله
     */
    public function getStreets($neighborhoodId)
    {
        $streets = Location::where('parent_id', $neighborhoodId)->get();
        return response()->json($streets);
    }

    /**
     * واکشی کوچه‌ها بر اساس خیابان
     */
    public function getAlleys($streetId)
    {
        $alleys = Location::where('parent_id', $streetId)->get();
        return response()->json($alleys);
    }
}