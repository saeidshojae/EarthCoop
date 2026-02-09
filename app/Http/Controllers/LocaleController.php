<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    /**
     * تغییر زبان برنامه
     *
     * @param  string  $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function change($locale)
    {
        // زبان‌های مجاز
        $availableLocales = ['fa', 'en', 'ar'];
        
        // بررسی معتبر بودن زبان
        if (in_array($locale, $availableLocales)) {
            // ذخیره زبان در session
            Session::put('locale', $locale);
            
            // پیام موفقیت
            $messages = [
                'fa' => 'زبان با موفقیت تغییر کرد',
                'en' => 'Language changed successfully',
                'ar' => 'تم تغيير اللغة بنجاح'
            ];
            
            return redirect()->back()->with('success', $messages[$locale]);
        }
        
        return redirect()->back()->with('error', 'Invalid language selection');
    }
    
    /**
     * دریافت زبان فعلی
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function current()
    {
        return response()->json([
            'locale' => app()->getLocale(),
            'direction' => in_array(app()->getLocale(), ['fa', 'ar']) ? 'rtl' : 'ltr'
        ]);
    }
}
