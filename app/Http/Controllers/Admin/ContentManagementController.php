<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Page;
use App\Models\Term;
use App\Models\Setting;
use App\Models\Slider;
use Illuminate\Http\Request;

class ContentManagementController extends Controller
{
    /**
     * Display content management dashboard
     */
    public function index()
    {
        // آمار کلی
        $stats = [
            'announcements' => Announcement::count(),
            'pages' => Page::count(),
            'published_pages' => Page::where('is_published', true)->count(),
            'draft_pages' => Page::where('is_published', false)->count(),
            'rules' => Term::count(),
            'welcome_sliders' => Slider::where('position', 0)->count(),
            'home_sliders' => Slider::where('position', 1)->count(),
        ];

        // آخرین اطلاعیه‌ها
        $recentAnnouncements = Announcement::latest()->take(5)->get();

        // آخرین صفحات
        $recentPages = Page::latest()->take(5)->get();

        // آخرین اساسنامه‌ها
        $recentRules = Term::latest()->take(5)->get();

        return view('admin.content.index', compact('stats', 'recentAnnouncements', 'recentPages', 'recentRules'));
    }
}

