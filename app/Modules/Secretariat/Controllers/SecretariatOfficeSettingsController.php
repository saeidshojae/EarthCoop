<?php

namespace App\Modules\Secretariat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Secretariat\Models\SecretariatOffice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecretariatOfficeSettingsController extends Controller
{
    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];

    public function edit(SecretariatOffice $office)
    {
        $this->authorize('manage', $office);

        return view('secretariat.settings', [
            'office' => $office,
            'confidentialities' => self::CONFIDENTIALITIES,
        ]);
    }

    public function update(Request $request, SecretariatOffice $office): RedirectResponse
    {
        $this->authorize('manage', $office);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_confidentiality' => ['required', Rule::in(self::CONFIDENTIALITIES)],
            'numbering_format' => [
                'required',
                'string',
                'max:160',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! str_contains((string) $value, '{SEQ}')) {
                        $fail('قالب شماره ثبت باید شامل {SEQ} باشد.');
                    }
                },
            ],
            'sequence_width' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $office->update([
            'name' => trim($validated['name']),
            'default_confidentiality' => $validated['default_confidentiality'],
            'numbering_policy' => [
                'format' => trim($validated['numbering_format']),
                'sequence_width' => (int) $validated['sequence_width'],
            ],
        ]);

        return redirect()
            ->route('secretariat.index', $office)
            ->with('success', 'تنظیمات دبیرخانه ذخیره شد.');
    }
}
