<?php

namespace App\Http\Controllers\Auth\Register;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Alley;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Location;
use App\Models\Neighborhood;
use App\Models\Province;
use App\Models\Region;
use App\Models\Street;
use App\Models\Village;
use App\Services\GroupService;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class Step3Controller extends Controller
{
    public function show()
    {
        if ((bool) config('location-governance.registration_enabled')) {
            $hasPrimaryResidence = auth()->user()?->locationRelationships()
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ends_at')
                ->exists();

            if ($hasPrimaryResidence) {
                return redirect()->route('home')->with('error', 'محل سکونت اصلی شما قبلا ثبت شده است.');
            }
        } else {
            $address = Address::where('user_id', auth()->user()->id)->first();
            if ($address) {
                return redirect()->route('home')->with('error', 'آدرس شما قبلا ثبت شده است.');
            }
        }

        $continents = Continent::where('status', 1)->get();
        $countries = Country::where('continent_id', 4)->where('status', 1)->get();
        $provinces = Province::where('country_id', 74)->get();

        return view('auth.register_step3', compact('continents', 'countries', 'provinces'));
    }

    public function process(
        Request $request,
        LocationTreeResolver $locationTreeResolver,
        ResidenceService $residenceService,
    ) {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login')->withErrors('ابتدا وارد حساب خود شوید.');
        }

        if ((bool) config('location-governance.registration_enabled')) {
            $validated = $request->validate([
                'location_id' => 'required|integer|exists:locations,id',
            ]);

            $location = Location::query()->findOrFail($validated['location_id']);

            if ($location->status !== 'active' || ! $locationTreeResolver->residenceEndpointAllowed($location)) {
                throw ValidationException::withMessages([
                    'location_id' => 'لطفاً یک محل سکونت معتبر و قابل انتخاب را مشخص کنید.',
                ]);
            }

            $residenceService->setInitialPrimaryResidence($user, $location, [
                'source' => 'registration_step3',
            ]);

            app(ProfileCompletionService::class)->maybeAward($user->fresh());

            return redirect()->route('home')->with('success', 'تبریک میگوییم، محل سکونت اصلی شما ثبت شد و ثبت نام شما تکمیل شد.');
        }

        $validated = $request->validate([
            'continent_id'     => 'required|exists:continents,id',
            'country_id'       => 'required|exists:countries,id',
            'province_id'      => 'required|exists:provinces,id',
            'county_id'        => 'required|exists:counties,id',
            'section_id'       => 'required|exists:districts,id',
            'city_id'          => 'required',
            'region_id'        => 'required',
            'neighborhood_id'  => 'required|exists:neighborhoods,id',
            'street_id'        => 'nullable|exists:streets,id',
            'alley_id'         => 'nullable|exists:alleies,id',
        ]);

        if (! method_exists($user, 'locations')) {
            return redirect()->route('home')->withErrors('متد locations در مدل User تعریف نشده است.');
        }

        $addressData = [
            'status' => 1,
            'user_id' => $user->id,
            'continent_id' => $validated['continent_id'],
            'country_id' => $validated['country_id'],
            'province_id' => $validated['province_id'],
            'county_id' => $validated['county_id'],
            'section_id' => $validated['section_id'],
            'neighborhood_id' => $validated['neighborhood_id'],
            'street_id' => $validated['street_id'] ?? null,
            'alley_id' => $validated['alley_id'] ?? null,
        ];

        if (strpos($validated['city_id'], 'rural_') === 0) {
            $ruralId = (int) str_replace('rural_', '', $validated['city_id']);
            $addressData['rural_id'] = $ruralId;
            $addressData['village_id'] = $validated['region_id'];
            $addressData['city_id'] = null;
            $addressData['region_id'] = null;
        } elseif (strpos($validated['city_id'], 'city_') === 0) {
            $cityId = (int) str_replace('city_', '', $validated['city_id']);
            $addressData['city_id'] = $cityId;
            $addressData['region_id'] = $validated['region_id'];
            $addressData['rural_id'] = null;
            $addressData['village_id'] = null;
        } else {
            return back()->withErrors(['city_id' => 'فرمت شهر/دهستان نامعتبر است.']);
        }

        if (isset($addressData['region_id']) && $addressData['region_id']) {
            $region = Region::find($addressData['region_id']);
        } elseif (isset($addressData['village_id']) && $addressData['village_id']) {
            $region = Village::find($addressData['village_id']);
        } else {
            return back()->withErrors(['region_id' => 'منطقه یا روستا انتخاب نشده است.']);
        }

        $neighborhood = Neighborhood::find($addressData['neighborhood_id']);
        $street = $addressData['street_id'] ? Street::find($addressData['street_id']) : null;
        $alley = $addressData['alley_id'] ? Alley::find($addressData['alley_id']) : null;

        if ($region && $region->status == 0) {
            $addressData['status'] = 0;
        } elseif ($neighborhood && $neighborhood->status == 0) {
            $addressData['status'] = 0;
        } elseif ($street && $street->status == 0) {
            $addressData['status'] = 0;
        } elseif ($alley && $alley->status == 0) {
            $addressData['status'] = 0;
        }

        Address::create($addressData);

        $user->refresh();
        $user->load([
            'address.continent',
            'address.country',
            'address.province',
            'address.county',
            'address.section',
            'address.city',
            'address.rural',
            'address.region',
            'address.village',
            'address.neighborhood',
            'address.street',
            'address.alley',
            'specialties',
            'experiences',
        ]);

        $groupService = new GroupService();
        $groupService->generateGroupsForUser($user);

        app(ProfileCompletionService::class)->maybeAward($user);

        return redirect()->route('home')->with('success', 'تبریک میگوییم، داده های شما دریافت و ثبت نام شما تکمیل شد و شما در گروه های مربوطه عضو شدید.
اکنون به داشبورد وارد میشوید. برای ایجاد حساب مالی نجم بهار، روی لینک "حساب مالی نجم بهار" کلیک کنید.

با تشکر تیم توسعه EarthCoop');
    }
}
