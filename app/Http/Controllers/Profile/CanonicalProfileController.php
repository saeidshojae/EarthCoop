<?php

namespace App\Http\Controllers\Profile;

use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

final class CanonicalProfileController extends ProfileController
{
    public function updateExperience(Request $request)
    {
        if (! (bool) config('location-governance.registration_enabled', false)) {
            return parent::updateExperience($request);
        }

        $validated = $request->validate([
            'occupational_fields' => 'required|array',
            'occupational_fields.*' => 'exists:occupational_fields,id',
            'experience_fields' => 'required|array',
            'experience_fields.*' => 'exists:experience_fields,id',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user, $validated): void {
            $user->occupationalFields()->sync($validated['occupational_fields']);
            $user->experienceFields()->sync($validated['experience_fields']);
            $user->unsetRelation('occupationalFields');
            $user->unsetRelation('experienceFields');

            if ((bool) config('location-governance.groups_enabled', false)) {
                app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
            }
        });

        app(ProfileCompletionService::class)->maybeAward($user->fresh());

        return redirect()->route('profile.edit')
            ->with('success', 'زمینه‌های صنفی و تجربی با موفقیت به‌روزرسانی شدند.');
    }

    public function updateGeneral(Request $request)
    {
        if (! (bool) config('location-governance.registration_enabled', false)) {
            return parent::updateGeneral($request);
        }

        $inputs = $request->validate([
            'first_name' => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\\s]+$/u',
            'last_name' => 'nullable|string|max:50|regex:/^[آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی\\s]+$/u',
            'birth_date' => 'nullable|array|min:3',
            'gender' => 'nullable|in:male,female',
            'national_id' => 'nullable|string|regex:/^\\d{10}$/|unique:users,national_id,'.Auth::id(),
            'phone' => 'nullable|regex:/^(0)?9\\d{9}$/|unique:users,phone,'.Auth::id(),
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4084',
            'document_names.*' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:4084',
            'biografie' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = User::findOrFail(Auth::id());

        if (isset($inputs['national_id']) && $inputs['national_id'] !== null
            && ! $this->isValidIranianNationalCode($inputs['national_id'])) {
            return back()->with('error', 'کد ملی وارد شده معتبر نیست')->withInput();
        }

        if ($request->has('remove_avatar') && $request->remove_avatar === '1') {
            if ($user->avatar) {
                $oldAvatarPath = public_path('images/users/avatars/'.$user->avatar);
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }
            $inputs['avatar'] = null;
        } elseif ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            if ($user->avatar) {
                $oldAvatarPath = public_path('images/users/avatars/'.$user->avatar);
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }

            $file = $request->file('avatar');
            $name = time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('images/users/avatars/'), $name);
            $inputs['avatar'] = $name;
        }

        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            $documentNames = $request->input('document_names', []);
            $existingDocuments = [];

            if ($user->documents) {
                $decoded = json_decode($user->documents, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $existingDocuments = $decoded;
                } else {
                    foreach (explode(',', $user->documents) as $existingFile) {
                        $existingFile = trim($existingFile);
                        if ($existingFile !== '') {
                            $existingDocuments[] = [
                                'filename' => $existingFile,
                                'name' => 'مدرک',
                                'type' => strtolower(pathinfo($existingFile, PATHINFO_EXTENSION)),
                            ];
                        }
                    }
                }
            }

            if (count($existingDocuments) + count($files) > 5) {
                return back()->with('error', 'شما می‌توانید حداکثر ۵ فایل داشته باشید. لطفاً ابتدا برخی از فایل‌های موجود را حذف کنید.')->withInput();
            }

            foreach ($files as $index => $file) {
                $name = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                $file->move(public_path('images/users/documents'), $name);
                $existingDocuments[] = [
                    'filename' => $name,
                    'name' => ! empty($documentNames[$index]) ? trim($documentNames[$index]) : 'مدرک',
                    'type' => strtolower($file->getClientOriginalExtension()),
                ];
            }

            $inputs['documents'] = json_encode($existingDocuments, JSON_UNESCAPED_UNICODE);
        }

        if (isset($inputs['birth_date']) && is_array($inputs['birth_date'])) {
            $inputs['birth_date'] = (new Jalalian(
                (int) $inputs['birth_date'][2],
                (int) $inputs['birth_date'][1],
                (int) $inputs['birth_date'][0],
            ))->toCarbon();
        }

        DB::transaction(function () use ($user, $inputs): void {
            $user->update($inputs);

            if ($user->first_name !== null
                && $user->last_name !== null
                && $user->gender !== null
                && $user->national_id !== null
                && $user->phone !== null) {
                $user->status = 1;
                $user->edited = 1;
                $user->save();
            }

            if ((bool) config('location-governance.groups_enabled', false)) {
                app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
            }
        });

        app(ProfileCompletionService::class)->maybeAward($user->fresh());

        return back()->with('success', 'پروفایل با موفقیت ویرایش شد');
    }
}
