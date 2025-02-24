<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
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
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showStep1Form()
    {
        return view('auth.register_step1');
    }

    public function postStep1Form(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
            'national_id' => 'required|string|max:255|unique:users',
            'phone' => 'required|string|max:255|unique:users',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'email' => 'required|string|email|max:255|unique:users',
        ]);

        $request->session()->put('register_step1', $request->all());

        return redirect()->route('register.step2');
    }

    public function showStep2Form()
    {
        $industrialFields = IndustrialField::with('children')->whereNull('parent_id')->get();
        $specializations = Specialization::all();
        return view('auth.register_step2', compact('industrialFields', 'specializations'));
    }

    public function postStep2Form(Request $request)
    {
        $request->validate([
            'industrial_fields' => 'required|array',
            'specializations' => 'required|array',
        ]);

        $request->session()->put('register_step2', $request->all());

        return redirect()->route('register.step3');
    }

    public function showStep3Form()
    {
        return view('auth.register_step3', [
            'continents' => Continent::all(), // تنها داده پر شده
            
            // بقیه سطوح با collection خالی
            'countries' => collect(),
            'provinces' => collect(),
            'counties' => collect(),
            'districts' => collect(),
            'settlements' => collect(),
            'localities' => collect(),
            'neighborhoods' => collect(),
            'streets' => collect(),
            'alleys' => collect()
        ]);
    }

    public function postStep3Form(Request $request)
    {
        $request->validate([
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

        $data = array_merge(
            $request->session()->get('register_step1'),
            $request->session()->get('register_step2'),
            $request->all()
        );

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'nationality' => $data['nationality'],
            'national_id' => $data['national_id'],
            'phone' => $data['phone'],
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'continent_id' => $data['continent'],
            'country_id' => $data['country'],
            'province_id' => $data['province'],
            'county_id' => $data['county'],
            'district_id' => $data['district'],
            'settlement_id' => $data['settlement'],
            'locality_id' => $data['locality'],
            'neighborhood_id' => $data['neighborhood'],
            'street_id' => $data['street'],
            'alley_id' => $data['alley'],
        ]);

        $user->industrialFields()->sync($data['industrial_fields']);
        $user->specializations()->sync($data['specializations']);

        return redirect($this->redirectPath());
    }
}