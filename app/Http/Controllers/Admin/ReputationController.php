<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReputationRule;

class ReputationController extends Controller
{
    public function index()
    {
        // Ensure config-defined rules exist without overwriting admin-authored DB values.
        $this->seedFromConfig();

        $rules = ReputationRule::orderBy('module')->orderBy('key')->get();

        $faLabels = [
            'email_verified' => 'تأیید ایمیل',
            'profile_completed' => 'تکمیل پروفایل',
            'profile_photo_uploaded' => 'آپلود تصویر پروفایل',
            'social_links_added' => 'افزودن لینک شبکه‌های اجتماعی',
            'documents_uploaded' => 'آپلود مدارک',
            'bio_added' => 'افزودن بیوگرافی',
            'post_created' => 'ایجاد پست',
            'post_upvoted' => 'پسندیدن پست',
            'comment_created' => 'ایجاد دیدگاه',
            'comment_upvoted' => 'پسندیدن دیدگاه',
            'bid_placed' => 'ثبت پیشنهاد',
            'bid_won' => 'برنده در مناقصه',
            'successful_settlement' => 'تسویه موفق',
            'report_received' => 'گزارش دریافت‌شده',
            'bid_canceled' => 'لغو پیشنهاد',
            'fraud' => 'تقلب',
            'poll_created' => 'ایجاد نظرسنجی',
            'poll_participated' => 'شرکت در نظرسنجی',
            'election_participated' => 'شرکت در انتخابات',
            'election_candidate' => 'نامزد انتخابات',
            'elected_inspector' => 'انتخاب‌شده به عنوان بازرس',
            'elected_manager' => 'انتخاب‌شده به عنوان مدیر',
        ];

        $groupDefinitions = [
            'stock' => ['label' => 'سهام و حراج', 'prefixes' => ['bid_', 'successful_settlement', 'bid_won', 'bid_canceled']],
            'profile' => ['label' => 'ثبت‌نام و پروفایل', 'prefixes' => ['profile_', 'email_verified', 'profile_photo', 'social_links', 'documents_', 'bio_']],
            'groups' => ['label' => 'گروه‌ها و نظرسنجی‌ها', 'prefixes' => ['poll_', 'election_', 'elected_']],
            'content' => ['label' => 'محتوا و بازخورد', 'prefixes' => ['post_', 'comment_', 'post', 'comment']],
            'moderation' => ['label' => 'نظارتی و گزارش‌ها', 'prefixes' => ['report_', 'fraud']],
        ];

        $grouped = [];
        foreach ($groupDefinitions as $key => $def) {
            $grouped[$key] = ['label' => $def['label'], 'rules' => []];
        }
        $grouped['other'] = ['label' => 'سایر', 'rules' => []];

        foreach ($rules as $rule) {
            $placed = false;
            foreach ($groupDefinitions as $gk => $def) {
                foreach ($def['prefixes'] as $p) {
                    if (str_starts_with($rule->key, $p) || $rule->key === $p) {
                        $grouped[$gk]['rules'][] = $rule;
                        $placed = true;
                        break 2;
                    }
                }
            }
            if (! $placed) {
                $grouped['other']['rules'][] = $rule;
            }
        }

        return view('admin.system-settings.reputation.index', compact('rules', 'faLabels', 'grouped'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'weights' => 'required|array',
            'weights.*' => 'integer',
            'active' => 'sometimes|array',
            'description' => 'sometimes|array',
            'daily_cap' => 'sometimes|array',
            'daily_cap.*' => 'nullable|integer',
            'dimension' => 'sometimes|array',
            'dimension.*' => 'in:participation,reliability,expertise,civic_trust',
            'convertible' => 'sometimes|array',
            'repeat_policy' => 'sometimes|array',
            'repeat_policy.*' => 'nullable|in:once,once_per_context,daily,repeatable',
        ]);

        foreach ($data['weights'] as $key => $weight) {
            $rule = ReputationRule::where('key', $key)->first();
            if (! $rule) {
                continue;
            }

            $rule->weight = (int) $weight;
            $rule->active = isset($data['active'][$key]);
            $rule->convertible = isset($data['convertible'][$key]);

            if (isset($data['description'][$key])) {
                $rule->description = $data['description'][$key];
            }
            if (array_key_exists($key, $data['daily_cap'] ?? [])) {
                $rule->daily_cap = $data['daily_cap'][$key] !== null && $data['daily_cap'][$key] !== '' ? (int) $data['daily_cap'][$key] : null;
            }
            if (isset($data['dimension'][$key])) {
                $rule->dimension = $data['dimension'][$key];
            }
            if (array_key_exists($key, $data['repeat_policy'] ?? [])) {
                $rule->repeat_policy = $data['repeat_policy'][$key] ?: null;
            }

            $rule->save();
        }

        return back()->with('success', 'قواعد امتیازدهی با موفقیت ذخیره شد');
    }

    protected function seedFromConfig()
    {
        $weights = config('reputation.weights', []);
        $dailyCaps = config('reputation.daily_caps', []);
        foreach ($weights as $key => $w) {
            ReputationRule::firstOrCreate(
                ['key' => $key],
                [
                    'label' => str_replace('_', ' ', ucfirst($key)),
                    'weight' => (int) $w,
                    'description' => null,
                    'module' => null,
                    'active' => true,
                    'daily_cap' => isset($dailyCaps[$key]) ? (int) $dailyCaps[$key] : null,
                ]
            );
        }
    }
}
