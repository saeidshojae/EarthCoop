<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReputationRule;

class ReputationController extends Controller
{
    public function index()
    {
        // Ensure rules are upserted from config so new keys appear in the UI
        // (updateOrCreate inside seedFromConfig makes this idempotent)
        $this->seedFromConfig();

        $rules = ReputationRule::orderBy('module')->orderBy('key')->get();

        // فارسی‌سازی عناوین برای نمایش در صفحه مدیریت
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

        // Group rules into logical tabs by key prefixes
        $groupDefinitions = [
            'stock' => [
                'label' => 'سهام و حراج',
                'prefixes' => ['bid_', 'successful_settlement', 'bid_won', 'bid_canceled'],
            ],
            'profile' => [
                'label' => 'ثبت‌نام و پروفایل',
                'prefixes' => ['profile_', 'email_verified', 'profile_photo', 'social_links', 'documents_', 'bio_'],
            ],
            'groups' => [
                'label' => 'گروه‌ها و نظرسنجی‌ها',
                'prefixes' => ['poll_', 'election_', 'elected_'],
            ],
            'content' => [
                'label' => 'محتوا و بازخورد',
                'prefixes' => ['post_', 'comment_', 'post', 'comment'],
            ],
            'moderation' => [
                'label' => 'نظارتی و گزارش‌ها',
                'prefixes' => ['report_', 'fraud'],
            ],
        ];

        $grouped = [];
        foreach ($groupDefinitions as $key => $def) {
            $grouped[$key] = [
                'label' => $def['label'],
                'rules' => [],
            ];
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
        ]);

        foreach ($data['weights'] as $key => $weight) {
            $rule = ReputationRule::where('key', $key)->first();
            if ($rule) {
                $rule->weight = (int)$weight;
                if (isset($data['active'][$key])) {
                    $rule->active = (bool)$data['active'][$key];
                } else {
                    $rule->active = false;
                }
                if (isset($data['description'][$key])) {
                    $rule->description = $data['description'][$key];
                }
                if (isset($data['daily_cap'][$key])) {
                    $rule->daily_cap = $data['daily_cap'][$key] !== null ? (int)$data['daily_cap'][$key] : null;
                }
                $rule->save();
            }
        }

        return back()->with('success', 'قواعد امتیازدهی با موفقیت ذخیره شد');
    }

    protected function seedFromConfig()
    {
        // Upsert rules from config so new keys are added and existing keys are preserved/updated
        $weights = config('reputation.weights', []);
        $dailyCaps = config('reputation.daily_caps', []);
        foreach ($weights as $key => $w) {
            ReputationRule::updateOrCreate(
                ['key' => $key],
                [
                    'label' => str_replace('_', ' ', ucfirst($key)),
                    'weight' => (int)$w,
                    'description' => null,
                    'module' => null,
                    'active' => true,
                    'daily_cap' => isset($dailyCaps[$key]) ? (int)$dailyCaps[$key] : null,
                ]
            );
        }
    }
}
