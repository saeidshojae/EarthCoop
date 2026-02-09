<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvitationCode;
use App\Models\Invitation;
use App\Models\Category;
use App\Models\GroupSetting;

class SystemSettingsController extends Controller
{
    /**
     * Display the system settings dashboard
     */
    public function index()
    {
        // آمار کلی
        $stats = [
            'invitation_codes' => InvitationCode::count(),
            'invitation_requests' => Invitation::where('status', 0)->count(), // درخواست‌های در انتظار
            'categories' => Category::count(),
            'group_settings' => GroupSetting::count(),
        ];
        
        return view('admin.system-settings.index', compact('stats'));
    }
}
