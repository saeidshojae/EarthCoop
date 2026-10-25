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
            'terms' => 'required|accepted',
            'finger' => ($setting && $setting->finger_status == 1)
                ? 'required|image|mimes:png,jpg,jpeg,webp|max:5020'
                : 'nullable|image|mimes:png,jpg,jpeg,webp|max:5020',
        ], [
            'terms.required' => __('legal_page.terms_acceptance_required'),
            'terms.accepted' => __('legal_page.terms_acceptance_required'),
        ]);

        $consentVersions = app(\App\Services\Legal\LegalMembershipConsentService::class)->validateForm($request);
        $updateData = ['terms_accepted_at' => now()];

        if ($request->hasFile('finger')) {
            $name = time() . '.' . $request->finger->extension();
            $request->finger->move(public_path('images/fingers/'), $name);
            $updateData['fingerprint_id'] = $name;
        }

        if (auth()->check()) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($updateData, $consentVersions) {
                app(\App\Services\Legal\LegalMembershipConsentService::class)
                    ->recordForUser((int) auth()->id(), $consentVersions, 'membership');
                auth()->user()->update($updateData);
            });
        }

        return redirect('/')->with('success', __('legal_page.terms_accept_success'));
    }
}
