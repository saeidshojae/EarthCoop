<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupSetting;
use Illuminate\Http\Request;

class GroupSettingController extends Controller
{
    /**
     * Display election settings list
     */
    public function index(Request $request)
    {
        $sort = $request->get('sort');
        
        if ($sort) {
            if (in_array($sort, ['experience', 'job', 'age', 'gender'])) {
                $groupSettings = GroupSetting::where('level', 'LIKE', '%' . $sort . '%')
                    ->orderBy('created_at', 'asc')
                    ->get();
            } else {
                // عمومی (total)
                $groupSettings = GroupSetting::where('id', '<=', 10)
                    ->orWhere('id', 42)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        } else {
            $groupSettings = GroupSetting::orderBy('created_at', 'desc')->get();
        }
        
        return view('admin.system-settings.elections.index', compact('groupSettings', 'sort'));
    }
    
    /**
     * Toggle election status
     */
    public function edit(GroupSetting $setting)
    {
        $setting->election_status = $setting->election_status == 1 ? 0 : 1;
        $setting->save();
        
        $status = $setting->election_status == 1 ? 'فعال' : 'غیرفعال';
        return back()->with('success', "وضعیت انتخابات برای {$setting->name()} به {$status} تغییر یافت.");
    }
    
    /**
     * Update election settings
     */
    public function update(Request $request, GroupSetting $setting)
    {
        $validated = $request->validate([
            'manager_count' => 'required|integer|min:0',
            'inspector_count' => 'required|integer|min:0',
            'election_time' => 'required|integer|min:1',
            'max_for_election' => 'required|integer|min:1',
            'second_election_time' => 'required|integer|min:1',
        ], [
            'manager_count.required' => 'تعداد مدیران الزامی است',
            'manager_count.integer' => 'تعداد مدیران باید عدد باشد',
            'manager_count.min' => 'تعداد مدیران نمی‌تواند منفی باشد',
            'inspector_count.required' => 'تعداد بازرسان الزامی است',
            'inspector_count.integer' => 'تعداد بازرسان باید عدد باشد',
            'inspector_count.min' => 'تعداد بازرسان نمی‌تواند منفی باشد',
            'election_time.required' => 'زمان انتخابات الزامی است',
            'election_time.integer' => 'زمان انتخابات باید عدد باشد',
            'election_time.min' => 'زمان انتخابات باید حداقل 1 روز باشد',
            'max_for_election.required' => 'تعداد برای شروع انتخابات الزامی است',
            'max_for_election.integer' => 'تعداد برای شروع انتخابات باید عدد باشد',
            'max_for_election.min' => 'تعداد برای شروع انتخابات باید حداقل 1 باشد',
            'second_election_time.required' => 'زمان ثانویه انتخابات الزامی است',
            'second_election_time.integer' => 'زمان ثانویه انتخابات باید عدد باشد',
            'second_election_time.min' => 'زمان ثانویه انتخابات باید حداقل 1 روز باشد',
        ]);

        $setting->update($validated);
        
        return back()->with('success', "تنظیمات انتخابات برای {$setting->name()} با موفقیت به‌روزرسانی شد.");
    }
}
