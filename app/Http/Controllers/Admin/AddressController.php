<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alley;
use App\Models\Neighborhood;
use App\Models\Region;
use App\Models\Rural;
use App\Models\Street;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request){
        $query = collect();
        
        // فیلتر بر اساس نوع
        $type = $request->input('type', 'all');
        
        // فیلتر بر اساس وضعیت
        $status = $request->input('status', 'all'); // all, pending, approved
        
        if ($type === 'all' || $type === 'alleies') {
            $alleys = Alley::query();
            if ($status === 'pending') {
                $alleys->where('status', 0);
            } elseif ($status === 'approved') {
                $alleys->where('status', 1);
            }
            if ($request->filled('search')) {
                $alleys->where('name', 'like', '%' . $request->search . '%');
            }
            $query = $query->merge($alleys->get()->map(function($item) {
                $item->table_name = 'alleies';
                $item->type_label = 'کوچه';
                return $item;
            }));
        }
        
        if ($type === 'all' || $type === 'streets') {
            $streets = Street::query();
            if ($status === 'pending') {
                $streets->where('status', 0);
            } elseif ($status === 'approved') {
                $streets->where('status', 1);
            }
            if ($request->filled('search')) {
                $streets->where('name', 'like', '%' . $request->search . '%');
            }
            $query = $query->merge($streets->get()->map(function($item) {
                $item->table_name = 'streets';
                $item->type_label = 'خیابان';
                return $item;
            }));
        }
        
        if ($type === 'all' || $type === 'neighborhoods') {
            $neighborhoods = Neighborhood::query();
            if ($status === 'pending') {
                $neighborhoods->where('status', 0);
            } elseif ($status === 'approved') {
                $neighborhoods->where('status', 1);
            }
            if ($request->filled('search')) {
                $neighborhoods->where('name', 'like', '%' . $request->search . '%');
            }
            $query = $query->merge($neighborhoods->get()->map(function($item) {
                $item->table_name = 'neighborhoods';
                $item->type_label = 'محله';
                return $item;
            }));
        }
        
        if ($type === 'all' || $type === 'regions') {
            $regions = Region::query();
            if ($status === 'pending') {
                $regions->where('status', 0);
            } elseif ($status === 'approved') {
                $regions->where('status', 1);
            }
            if ($request->filled('search')) {
                $regions->where('name', 'like', '%' . $request->search . '%');
            }
            $query = $query->merge($regions->get()->map(function($item) {
                $item->table_name = 'regions';
                $item->type_label = 'منطقه';
                return $item;
            }));
        }
        
        if ($type === 'all' || $type === 'rurals') {
            $rurals = Rural::query();
            if ($status === 'pending') {
                $rurals->where('status', 0);
            } elseif ($status === 'approved') {
                $rurals->where('status', 1);
            }
            if ($request->filled('search')) {
                $rurals->where('name', 'like', '%' . $request->search . '%');
            }
            $query = $query->merge($rurals->get()->map(function($item) {
                $item->table_name = 'rurals';
                $item->type_label = 'دهستان';
                return $item;
            }));
        }

        // آمار
        $stats = [
            'total' => Alley::count() + Street::count() + Neighborhood::count() + Region::count() + Rural::count(),
            'pending' => Alley::where('status', 0)->count() + Street::where('status', 0)->count() + Neighborhood::where('status', 0)->count() + Region::where('status', 0)->count() + Rural::where('status', 0)->count(),
            'approved' => Alley::where('status', 1)->count() + Street::where('status', 1)->count() + Neighborhood::where('status', 1)->count() + Region::where('status', 1)->count() + Rural::where('status', 1)->count(),
            'alleies' => Alley::where('status', 0)->count(),
            'streets' => Street::where('status', 0)->count(),
            'neighborhoods' => Neighborhood::where('status', 0)->count(),
            'regions' => Region::where('status', 0)->count(),
            'rurals' => Rural::where('status', 0)->count(),
        ];

        return view('admin.address.index', compact('query', 'stats'));
    }

    /**
     * Store a new address item
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'table' => 'required|string|in:alleies,streets,neighborhoods,regions,rurals',
            'parent_id' => 'nullable|integer',
            'district_id' => 'nullable|integer', // برای rurals
        ], [
            'name.required' => 'نام الزامی است',
            'name.max' => 'نام نمی‌تواند بیشتر از 255 کاراکتر باشد',
            'table.required' => 'نوع آدرس الزامی است',
            'table.in' => 'نوع آدرس معتبر نیست',
        ]);

        $modelMap = [
            'alleies' => \App\Models\Alley::class,
            'streets' => \App\Models\Street::class,
            'regions' => \App\Models\Region::class,
            'rurals' => \App\Models\Rural::class,
            'neighborhoods' => \App\Models\Neighborhood::class,
        ];

        $table = $request->input('table');
        if (!isset($modelMap[$table])) {
            return back()->with('error', 'نوع آدرس معتبر نیست.');
        }

        $model = $modelMap[$table];
        $data = [
            'name' => $request->input('name'),
            'status' => 0, // به صورت پیش‌فرض در انتظار تأیید
        ];

        // اضافه کردن parent_id یا district_id بر اساس نوع
        if ($table === 'rurals' && $request->filled('district_id')) {
            $data['district_id'] = $request->input('district_id');
        } elseif ($request->filled('parent_id')) {
            $data['parent_id'] = $request->input('parent_id');
        }

        $model::create($data);

        return back()->with('success', 'آدرس جدید با موفقیت ایجاد شد.');
    }

    public function update(Request $request, $id)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'table' => 'required|string',
    ], [
        'name.required' => 'نام الزامی است',
        'name.max' => 'نام نمی‌تواند بیشتر از 255 کاراکتر باشد',
    ]);

    $modelMap = [
        'alleies' => \App\Models\Alley::class,
        'streets' => \App\Models\Street::class,
        'regions' => \App\Models\Region::class,
        'rural' => \App\Models\Rural::class,
        'neighborhoods' => \App\Models\Neighborhood::class,
    ];
    
    $table = $request->input('table');
    if (isset($modelMap[$table])) {
        $model = $modelMap[$table];
        $item = $model::findOrFail($id);
        $item->update(['name' => $request->input('name')]);
        
        return back()->with('success', 'نام آدرس با موفقیت به‌روزرسانی شد.');
    }
    
    return back()->with('error', 'خطا در به‌روزرسانی آدرس.');
}

public function edit(Request $request, $id)
{
    $table = $request->input('table');
    $updated = false;
    $modelMap = [
        'alleies' => \App\Models\Alley::class,
        'streets' => \App\Models\Street::class,
        'regions' => \App\Models\Region::class,
        'rural' => \App\Models\Rural::class,
        'neighborhoods' => \App\Models\Neighborhood::class,
    ];

    $fieldMap = [
        'alleies' => 'alley_id',
        'streets' => 'street_id',
        'regions' => 'region_id',
        'rural' => 'rural_district_id',
        'neighborhoods' => 'neighborhood_id',
    ];

    if (isset($modelMap[$table]) && isset($fieldMap[$table])) {
        $model = $modelMap[$table];
        $field = $fieldMap[$table];

        $item = $model::findOrFail($id);
        $item->update(['status' => 1]); // آیتم تأیید می‌شه

        // پیدا کردن تمام آدرس‌هایی که این آیتم را استفاده می‌کنند
        $addresses = \App\Models\Address::where($field, $id)->get();

        foreach ($addresses as $address) {
            if ($this->isAddressFullyApproved($address)) {
                $address->update(['status' => 1]);
            }
        }

        $updated = true;
    }

    return $updated
        ? back()->with('success', 'آدرس تأیید شد و آدرس‌های کامل نیز فعال شدند.')
        : back()->with('error', 'خطا در تأیید آدرس.');
}

public function isAddressFullyApproved($address)
{
    return
        (!$address->region_id || optional($address->region)->status == 1) &&
        (!$address->neighborhood_id || optional($address->neighborhood)->status == 1) &&
        (!$address->street_id || optional($address->street)->status == 1) &&
        (!$address->alley_id || optional($address->alley)->status == 1);
}

    
    public function delete(Request $request, $id){
        $table = $request->input('table');

        if($table == 'alleies'){
            Alley::findOrFail($id)->delete();
        }elseif($table == 'streets'){
            Street::findOrFail($id)->delete();
        }elseif($table == 'regions'){
            Region::findOrFail($id)->delete();
        }elseif($table == 'rural'){
            Rural::findOrFail($id)->delete();
        }elseif($table == 'neighborhoods'){
            Neighborhood::findOrFail($id)->delete();
        }

        return back()->with('success', 'آدرس با موفقیت حذف شد');
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|string'
        ]);

        $modelMap = [
            'alleies' => \App\Models\Alley::class,
            'streets' => \App\Models\Street::class,
            'regions' => \App\Models\Region::class,
            'rural' => \App\Models\Rural::class,
            'neighborhoods' => \App\Models\Neighborhood::class,
        ];

        $fieldMap = [
            'alleies' => 'alley_id',
            'streets' => 'street_id',
            'regions' => 'region_id',
            'rural' => 'rural_district_id',
            'neighborhoods' => 'neighborhood_id',
        ];

        $approvedCount = 0;
        $addressesUpdated = 0;

        foreach ($request->input('items') as $itemString) {
            // Parse the item string (format: "table_id")
            $parts = explode('_', $itemString);
            if (count($parts) >= 2) {
                $table = $parts[0];
                $id = end($parts);

                if (isset($modelMap[$table]) && isset($fieldMap[$table])) {
                    $model = $modelMap[$table];
                    $field = $fieldMap[$table];

                    $item = $model::find($id);
                    if ($item && $item->status == 0) {
                        $item->update(['status' => 1]);
                        $approvedCount++;

                        // Update related addresses
                        $addresses = \App\Models\Address::where($field, $id)->get();
                        foreach ($addresses as $address) {
                            if ($this->isAddressFullyApproved($address)) {
                                $address->update(['status' => 1]);
                                $addressesUpdated++;
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$approvedCount} آدرس تأیید شد و {$addressesUpdated} آدرس کامل فعال شد."
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|string'
        ]);

        $modelMap = [
            'alleies' => \App\Models\Alley::class,
            'streets' => \App\Models\Street::class,
            'regions' => \App\Models\Region::class,
            'rural' => \App\Models\Rural::class,
            'neighborhoods' => \App\Models\Neighborhood::class,
        ];

        $deletedCount = 0;

        foreach ($request->input('items') as $itemString) {
            // Parse the item string (format: "table_id")
            $parts = explode('_', $itemString);
            if (count($parts) >= 2) {
                $table = $parts[0];
                $id = end($parts);

                if (isset($modelMap[$table])) {
                    $model = $modelMap[$table];
                    $item = $model::find($id);
                    if ($item) {
                        $item->delete();
                        $deletedCount++;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} آدرس با موفقیت حذف شد."
        ]);
    }

    /**
     * Get parent hierarchy for an address item
     */
    public static function getParents($item)
    {
        $parents = [];
        $table = $item->table_name ?? $item->getTable();
        
        switch ($table) {
            case 'alleies':
                $street = \App\Models\Street::find($item->parent_id);
                if ($street) {
                    $parents[] = ['label' => 'خیابان', 'name' => $street->name];
                    $neighborhood = \App\Models\Neighborhood::find($street->parent_id);
                    if ($neighborhood) {
                        $parents[] = ['label' => 'محله', 'name' => $neighborhood->name];
                        $region = \App\Models\Region::find($neighborhood->parent_id);
                        if ($region) {
                            $parents[] = ['label' => 'منطقه', 'name' => $region->name];
                            $city = \App\Models\City::find($region->parent_id);
                            if ($city) {
                                $parents[] = ['label' => 'شهر', 'name' => $city->name];
                                $district = \App\Models\District::find($city->district_id);
                                if ($district) {
                                    $parents[] = ['label' => 'بخش', 'name' => $district->name];
                                }
                                $county = \App\Models\County::find($city->counties_id);
                                if ($county) {
                                    $parents[] = ['label' => 'شهرستان', 'name' => $county->name];
                                }
                                $province = \App\Models\Province::find($city->province_id);
                                if ($province) {
                                    $parents[] = ['label' => 'استان', 'name' => $province->name];
                                    $country = \App\Models\Country::find($province->country_id);
                                    if ($country) {
                                        $parents[] = ['label' => 'کشور', 'name' => $country->name];
                                    }
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'streets':
                $neighborhood = \App\Models\Neighborhood::find($item->parent_id);
                if ($neighborhood) {
                    $parents[] = ['label' => 'محله', 'name' => $neighborhood->name];
                    $region = \App\Models\Region::find($neighborhood->parent_id);
                    if ($region) {
                        $parents[] = ['label' => 'منطقه', 'name' => $region->name];
                        $city = \App\Models\City::find($region->parent_id);
                        if ($city) {
                            $parents[] = ['label' => 'شهر', 'name' => $city->name];
                            $district = \App\Models\District::find($city->district_id);
                            if ($district) {
                                $parents[] = ['label' => 'بخش', 'name' => $district->name];
                            }
                            $county = \App\Models\County::find($city->counties_id);
                            if ($county) {
                                $parents[] = ['label' => 'شهرستان', 'name' => $county->name];
                            }
                            $province = \App\Models\Province::find($city->province_id);
                            if ($province) {
                                $parents[] = ['label' => 'استان', 'name' => $province->name];
                                $country = \App\Models\Country::find($province->country_id);
                                if ($country) {
                                    $parents[] = ['label' => 'کشور', 'name' => $country->name];
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'neighborhoods':
                $region = \App\Models\Region::find($item->parent_id);
                if ($region) {
                    $parents[] = ['label' => 'منطقه', 'name' => $region->name];
                    $city = \App\Models\City::find($region->parent_id);
                    if ($city) {
                        $parents[] = ['label' => 'شهر', 'name' => $city->name];
                        $district = \App\Models\District::find($city->district_id);
                        if ($district) {
                            $parents[] = ['label' => 'بخش', 'name' => $district->name];
                        }
                        $county = \App\Models\County::find($city->counties_id);
                        if ($county) {
                            $parents[] = ['label' => 'شهرستان', 'name' => $county->name];
                        }
                        $province = \App\Models\Province::find($city->province_id);
                        if ($province) {
                            $parents[] = ['label' => 'استان', 'name' => $province->name];
                            $country = \App\Models\Country::find($province->country_id);
                            if ($country) {
                                $parents[] = ['label' => 'کشور', 'name' => $country->name];
                            }
                        }
                    }
                }
                break;
                
            case 'regions':
                $city = \App\Models\City::find($item->parent_id);
                if ($city) {
                    $parents[] = ['label' => 'شهر', 'name' => $city->name];
                    $district = \App\Models\District::find($city->district_id);
                    if ($district) {
                        $parents[] = ['label' => 'بخش', 'name' => $district->name];
                    }
                    $county = \App\Models\County::find($city->counties_id);
                    if ($county) {
                        $parents[] = ['label' => 'شهرستان', 'name' => $county->name];
                    }
                    $province = \App\Models\Province::find($city->province_id);
                    if ($province) {
                        $parents[] = ['label' => 'استان', 'name' => $province->name];
                        $country = \App\Models\Country::find($province->country_id);
                        if ($country) {
                            $parents[] = ['label' => 'کشور', 'name' => $country->name];
                        }
                    }
                }
                break;
                
            case 'rurals':
                $district = \App\Models\District::find($item->district_id);
                if ($district) {
                    $parents[] = ['label' => 'بخش', 'name' => $district->name];
                    $county = \App\Models\County::find($district->counties_id ?? null);
                    if ($county) {
                        $parents[] = ['label' => 'شهرستان', 'name' => $county->name];
                        $province = \App\Models\Province::find($county->province_id ?? null);
                        if ($province) {
                            $parents[] = ['label' => 'استان', 'name' => $province->name];
                            $country = \App\Models\Country::find($province->country_id);
                            if ($country) {
                                $parents[] = ['label' => 'کشور', 'name' => $country->name];
                            }
                        }
                    }
                }
                break;
        }
        
        return array_reverse($parents);
    }

    /**
     * Get available parents for a given type (API endpoint)
     */
    public function getAvailableParents($type)
    {
        $parents = [];
        
        switch ($type) {
            case 'streets':
                $parents = Street::where('status', 1)->get(['id', 'name'])->map(function($item) {
                    return ['id' => $item->id, 'name' => $item->name];
                });
                break;
            case 'neighborhoods':
                $parents = Neighborhood::where('status', 1)->get(['id', 'name'])->map(function($item) {
                    return ['id' => $item->id, 'name' => $item->name];
                });
                break;
            case 'regions':
                $parents = Region::where('status', 1)->get(['id', 'name'])->map(function($item) {
                    return ['id' => $item->id, 'name' => $item->name];
                });
                break;
            case 'cities':
                $parents = \App\Models\City::all()->map(function($item) {
                    return ['id' => $item->id, 'name' => $item->name];
                });
                break;
        }
        
        return response()->json($parents->values());
    }

}
