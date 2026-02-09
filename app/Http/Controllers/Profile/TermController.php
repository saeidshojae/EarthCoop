<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function store(Request $request)
    {
        $setting = Setting::find(1);

        $request->validate([
            'finger' => ($setting && $setting->finger_status == 1)
                ? 'required|image|mimes:png,jpg,jpeg,webp|max:5020'
                : 'nullable|image|mimes:png,jpg,jpeg,webp|max:5020',
        ]);

        $updateData = ['terms_accepted_at' => now()];

        if ($request->hasFile('finger')) {
            $name = time() . '.' . $request->finger->extension();
            $request->finger->move(public_path('images/fingers/'), $name);
            $updateData['fingerprint_id'] = $name;
        }

        if (auth()->check()) {
            auth()->user()->update($updateData);
        }

        return redirect('/')->with('success', 'تایید اساسنامه باموفقیت انجام شد، به مراحل ثبت نام بازگردید');
    }
}
