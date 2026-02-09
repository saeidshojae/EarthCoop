<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExperienceField;
use App\Models\OccupationalField;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    public function index(Request $request){
        $query = collect();
        
        // فیلتر بر اساس نوع
        $type = $request->input('type', 'all');
        
        // فیلتر بر اساس وضعیت
        $status = $request->input('status', 'all'); // all, pending, approved
        
        if ($type === 'all' || $type === 'experience') {
            $experience = ExperienceField::query();
            if ($status === 'pending') {
                $experience->where('status', 0);
            } elseif ($status === 'approved') {
                $experience->where('status', 1);
            }
            if ($request->filled('search')) {
                $experience->where('name', 'like', '%' . $request->search . '%');
            }
            $query = $query->merge($experience->get()->map(function($item) {
                $item->table_name = 'experience_fields';
                $item->type_label = 'تخصص';
                return $item;
            }));
        }
        
        if ($type === 'all' || $type === 'occupational') {
            $occupational = OccupationalField::query();
            if ($status === 'pending') {
                $occupational->where('status', 0);
            } elseif ($status === 'approved') {
                $occupational->where('status', 1);
            }
            if ($request->filled('search')) {
                $occupational->where('name', 'like', '%' . $request->search . '%');
            }
            $query = $query->merge($occupational->get()->map(function($item) {
                $item->table_name = 'occupational_fields';
                $item->type_label = 'صنف';
                return $item;
            }));
        }

        // آمار
        $stats = [
            'total' => ExperienceField::count() + OccupationalField::count(),
            'pending' => ExperienceField::where('status', 0)->count() + OccupationalField::where('status', 0)->count(),
            'approved' => ExperienceField::where('status', 1)->count() + OccupationalField::where('status', 1)->count(),
            'experience' => ExperienceField::where('status', 0)->count(),
            'occupational' => OccupationalField::where('status', 0)->count(),
        ];

        return view('admin.experience.index', compact('query', 'stats'));
    }

    /**
     * Store a new experience/occupational field
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'table' => 'required|string|in:experience_fields,occupational_fields',
            'parent_id' => 'nullable|integer',
        ], [
            'name.required' => 'نام الزامی است',
            'name.max' => 'نام نمی‌تواند بیشتر از 255 کاراکتر باشد',
            'table.required' => 'نوع آیتم الزامی است',
            'table.in' => 'نوع آیتم معتبر نیست',
        ]);

        $table = $request->input('table');
        $data = [
            'name' => $request->input('name'),
            'status' => 0, // به صورت پیش‌فرض در انتظار تأیید
        ];

        if ($request->filled('parent_id')) {
            $data['parent_id'] = $request->input('parent_id');
        }

        if ($table === 'experience_fields') {
            \App\Models\ExperienceField::create($data);
        } elseif ($table === 'occupational_fields') {
            \App\Models\OccupationalField::create($data);
        }

        return back()->with('success', 'آیتم جدید با موفقیت ایجاد شد.');
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

        $table = $request->input('table');
    
        if ($table == 'experience_fields') {
            \App\Models\ExperienceField::findOrFail($id)->update(['name' => $request->input('name')]);
        } elseif ($table == 'occupational_fields') {
            \App\Models\OccupationalField::findOrFail($id)->update(['name' => $request->input('name')]);
        }
    
        return back()->with('success', 'نام آیتم با موفقیت به‌روزرسانی شد.');
    }
    
    
    public function edit(Request $request, $id)
    {
        $table = $request->input('table');
    
        if ($table == 'experience_fields') {
            \App\Models\ExperienceField::findOrFail($id)->update(['status' => 1]);
    
            // بررسی کاربران دارای این فیلد
            $userIds = \DB::table('user_experience_field')
                ->where('experience_field_id', $id)
                ->pluck('user_id');
    
            foreach ($userIds as $userId) {
                $user = \App\Models\User::find($userId);
    
                if (
                    $user->experiences()
                         ->where('status', '!=', 1)
                         ->count() == 0
                ) {
                    $user->update(['experience_status' => 1]);
                }
            }
    
        } elseif ($table == 'occupational_fields') {
            \App\Models\OccupationalField::findOrFail($id)->update(['status' => 1]);
    
            // بررسی کاربران دارای این فیلد
            $userIds = \DB::table('user_occupational_field')
                ->where('occupational_field_id', $id)
                ->pluck('user_id');
    
            foreach ($userIds as $userId) {
                $user = \App\Models\User::find($userId);
    
                if (
                    $user->specialties()
                         ->where('status', '!=', 1)
                         ->count() == 0
                ) {
                    $user->update(['occupational_status' => 1]);
                }
            }
        }
    
        return back()->with('success', 'آیتم با موفقیت تایید شد');
    }
    


    public function delete(Request $request, $id){
        $table = $request->input('table');

        if($table == 'experience_fields'){
            ExperienceField::findOrFail($id)->delete();
        }elseif($table == 'occupational_fields'){
            OccupationalField::findOrFail($id)->delete();
        }

        return back()->with('success', 'آیتم با موفقیت حذف شد');
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|string'
        ]);

        $modelMap = [
            'experience_fields' => \App\Models\ExperienceField::class,
            'occupational_fields' => \App\Models\OccupationalField::class,
            'experience' => \App\Models\ExperienceField::class,  // Add this mapping
            'occupational' => \App\Models\OccupationalField::class,  // Add this mapping
        ];

        $approvedCount = 0;
        $usersUpdated = 0;

        foreach ($request->input('items') as $itemString) {
            // Parse the item string (format: "table_id")
            $parts = explode('_', $itemString);
            if (count($parts) >= 2) {
                $table = $parts[0];
                $id = end($parts);

                if (isset($modelMap[$table])) {
                    $model = $modelMap[$table];
                    $item = $model::find($id);
                    
                    if ($item && $item->status == 0) {
                        $item->update(['status' => 1]);
                        $approvedCount++;

                        // Update related users based on table type
                        if ($table == 'experience_fields' || $table == 'experience') {
                            $userIds = \DB::table('user_experience_field')
                                ->where('experience_field_id', $id)
                                ->pluck('user_id');

                            foreach ($userIds as $userId) {
                                $user = \App\Models\User::find($userId);
                                if ($user && $user->experiences()->where('status', '!=', 1)->count() == 0) {
                                    $user->update(['experience_status' => 1]);
                                    $usersUpdated++;
                                }
                            }
                        } elseif ($table == 'occupational_fields' || $table == 'occupational') {
                            $userIds = \DB::table('user_occupational_field')
                                ->where('occupational_field_id', $id)
                                ->pluck('user_id');

                            foreach ($userIds as $userId) {
                                $user = \App\Models\User::find($userId);
                                if ($user && $user->specialties()->where('status', '!=', 1)->count() == 0) {
                                    $user->update(['occupational_status' => 1]);
                                    $usersUpdated++;
                                }
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$approvedCount} آیتم تأیید شد و {$usersUpdated} کاربر فعال شد."
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|string'
        ]);

        $modelMap = [
            'experience_fields' => \App\Models\ExperienceField::class,
            'occupational_fields' => \App\Models\OccupationalField::class,
            'experience' => \App\Models\ExperienceField::class,  // Add this mapping
            'occupational' => \App\Models\OccupationalField::class,  // Add this mapping
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
            'message' => "{$deletedCount} آیتم با موفقیت حذف شد."
        ]);
    }

    /**
     * Get parent hierarchy for an experience/occupational field
     */
    public static function getParents($item)
    {
        $parents = [];
        $table = $item->table_name ?? $item->getTable();
        
        if ($table === 'experience_fields') {
            $current = $item;
            while ($current && $current->parent_id) {
                $parent = ExperienceField::find($current->parent_id);
                if ($parent) {
                    $parents[] = ['label' => 'والد', 'name' => $parent->name];
                    $current = $parent;
                } else {
                    break;
                }
            }
        } elseif ($table === 'occupational_fields') {
            $current = $item;
            while ($current && $current->parent_id) {
                $parent = OccupationalField::find($current->parent_id);
                if ($parent) {
                    $parents[] = ['label' => 'والد', 'name' => $parent->name];
                    $current = $parent;
                } else {
                    break;
                }
            }
        }
        
        return array_reverse($parents);
    }

    /**
     * Get available parents for a given type (API endpoint)
     */
    public function getAvailableParents($type)
    {
        $parents = [];
        
        if ($type === 'experience_fields') {
            $parents = ExperienceField::where('status', 1)->get(['id', 'name'])->map(function($item) {
                return ['id' => $item->id, 'name' => $item->name];
            });
        } elseif ($type === 'occupational_fields') {
            $parents = OccupationalField::where('status', 1)->get(['id', 'name'])->map(function($item) {
                return ['id' => $item->id, 'name' => $item->name];
            });
        }
        
        return response()->json($parents->values());
    }
}
