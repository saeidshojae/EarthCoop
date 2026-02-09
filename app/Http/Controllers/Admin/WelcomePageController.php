<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WelcomePageController extends Controller
{
    /**
     * Display the welcome/home page management
     */
    public function index(Request $request)
    {
        $isHome = $request->has('home') && $request->home == 1;
        $setting = Setting::find(1);
        
        $position = $isHome ? 1 : 0;
        $sliders = Slider::where('position', $position)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // آمار
        $stats = [
            'welcome_sliders' => Slider::where('position', 0)->count(),
            'home_sliders' => Slider::where('position', 1)->count(),
        ];
        
        return view('admin.welcome.index', compact('isHome', 'setting', 'sliders', 'stats'));
    }

    /**
     * Update welcome/home page content
     */
    public function update(Request $request)
    {
        $isHome = $request->has('home') && $request->home == 1;
        
        $validated = $request->validate([
            'welcome_titre' => 'nullable|string|max:255',
            'welcome_content' => 'nullable|string',
            'home_titre' => 'nullable|string|max:255',
            'home_content' => 'nullable|string',
        ]);
        
        $setting = Setting::find(1);
        
        if ($isHome) {
            if (isset($validated['home_titre'])) {
                $setting->home_titre = $validated['home_titre'];
            }
            if (isset($validated['home_content'])) {
                $setting->home_content = $validated['home_content'];
            }
        } else {
            if (isset($validated['welcome_titre'])) {
                $setting->welcome_titre = $validated['welcome_titre'];
            }
            if (isset($validated['welcome_content'])) {
                $setting->welcome_content = $validated['welcome_content'];
            }
        }
        
        $setting->save();
        
        return redirect()->route('admin.welcome-page', $isHome ? ['home' => 1] : [])
            ->with('success', 'تغییرات با موفقیت ذخیره شد.');
    }

    /**
     * Store a new slider
     */
    public function storeSlider(Request $request)
    {
        $isHome = $request->has('home') && $request->home == 1;
        
        $validated = $request->validate([
            'src' => 'required|file|mimes:png,jpg,jpeg,webp|max:5120',
            'alt' => 'nullable|string|max:255',
            'link' => 'nullable|url|max:255',
            'position' => 'required|numeric|in:0,1'
        ]);
        
        if ($request->hasFile('src') && $request->file('src')->isValid()) {
            $file = $request->file('src');
            $name = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/sliders'), $name);
            $validated['src'] = $name;
        } else {
            return back()->withErrors(['src' => 'فایل تصویر معتبر نیست.']);
        }
        
        // اطمینان از اینکه position مطابق با home است
        $validated['position'] = $isHome ? 1 : 0;
        
        Slider::create($validated);
        
        return redirect()->route('admin.welcome-page', $isHome ? ['home' => 1] : [])
            ->with('success', 'اسلایدر با موفقیت اضافه شد.');
    }

    /**
     * Delete a slider
     */
    public function destroySlider(Slider $slider)
    {
        // حذف فایل تصویر
        if ($slider->src && file_exists(public_path('images/sliders/' . $slider->src))) {
            unlink(public_path('images/sliders/' . $slider->src));
        }
        
        $isHome = $slider->position == 1;
        $slider->delete();
        
        return redirect()->route('admin.welcome-page', $isHome ? ['home' => 1] : [])
            ->with('success', 'اسلایدر با موفقیت حذف شد.');
    }
}
