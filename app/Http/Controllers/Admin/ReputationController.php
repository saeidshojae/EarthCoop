<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReputationRule;
use App\Models\UserPointConversion;
use App\Models\UserPointTransaction;
use Illuminate\Http\Request;

class ReputationController extends Controller
{
    private const DEPRECATED_RULE_KEYS = [
        'election_candidate',
        'election_participated',
        'profile_photo_uploaded',
        'social_links_added',
        'documents_uploaded',
        'bio_added',
        'report_received',
        'bid_canceled',
        'fraud',
    ];

    public function index()
    {
        $this->seedFromConfig();

        $rules = ReputationRule::orderBy('module')->orderBy('key')->get();

        $faLabels = [
            'email_verified' => 'تأیید ایمیل',
            'profile_completed' => 'تکمیل پروفایل',
            'profile_photo_uploaded' => 'منسوخ — آپلود تصویر پروفایل',
            'social_links_added' => 'منسوخ — افزودن لینک شبکه‌های اجتماعی',
            'documents_uploaded' => 'منسوخ — آپلود مدارک',
            'bio_added' => 'منسوخ — افزودن بیوگرافی',
            'invite_member' => 'دعوت موفق عضو جدید',
            'membership_fee_paid' => 'پرداخت حق عضویت سالانه',
            'post_created' => 'ایجاد پست',
            'post_liked' => 'پسندیدن پست دیگران',
            'post_upvoted' => 'دریافت پسند برای پست',
            'comment_created' => 'ایجاد دیدگاه',
            'comment_liked' => 'پسندیدن دیدگاه دیگران',
            'comment_upvoted' => 'دریافت پسند برای دیدگاه',
            'bid_placed' => 'ثبت پیشنهاد',
            'bid_won' => 'برنده حراج',
            'successful_settlement' => 'تسویه موفق',
            'report_received' => 'منسوخ — گزارش دریافت‌شده',
            'bid_canceled' => 'منسوخ — لغو پیشنهاد',
            'fraud' => 'منسوخ — تقلب',
            'poll_created' => 'ایجاد نظرسنجی',
            'poll_participated' => 'شرکت در نظرسنجی',
            'election_participated' => 'منسوخ — مشارکت عمومی انتخابات قدیمی',
            'election_candidate' => 'منسوخ — نامزدی در مدل قدیمی انتخابات',
            'elected_inspector' => 'انتخاب‌شدن به عنوان بازرس',
            'elected_manager' => 'انتخاب‌شدن به عنوان مدیر',
            'professional_referral_completed' => 'تکمیل ارجاع تخصصی تأییدشده',
        ];

        $dimensionLabels = [
            'participation' => 'مشارکت',
            'reliability' => 'اعتمادپذیری',
            'expertise' => 'تخصص',
            'civic_trust' => 'اعتماد مدنی',
        ];

        $conversionStatusLabels = [
            'pending' => 'در انتظار',
            'applied' => 'انجام‌شده',
            'failed' => 'ناموفق',
            'cancelled' => 'لغوشده',
            'canceled' => 'لغوشده',
        ];

        $groupDefinitions = [
            'membership' => ['label' => 'عضویت و دعوت', 'prefixes' => ['invite_member', 'membership_fee_paid']],
            'stock' => ['label' => 'سهام و حراج', 'prefixes' => ['bid_', 'successful_settlement', 'bid_won']],
            'profile' => ['label' => 'ثبت‌نام و پروفایل', 'prefixes' => ['profile_completed', 'email_verified']],
            'groups' => ['label' => 'گروه‌ها و نظرسنجی‌ها', 'prefixes' => ['poll_']],
            'governance' => ['label' => 'حاکمیت و انتخابات', 'prefixes' => ['elected_', 'professional_referral_']],
            'content' => ['label' => 'محتوا و بازخورد', 'prefixes' => ['post_', 'comment_']],
            'archived' => ['label' => 'آرشیو / منسوخ', 'prefixes' => []],
        ];

        $grouped = [];
        foreach ($groupDefinitions as $key => $def) {
            $grouped[$key] = ['label' => $def['label'], 'rules' => []];
        }
        $grouped['other'] = ['label' => 'سایر', 'rules' => []];

        foreach ($rules as $rule) {
            if (in_array($rule->key, self::DEPRECATED_RULE_KEYS, true)) {
                $grouped['archived']['rules'][] = $rule;
                continue;
            }

            $placed = false;
            foreach ($groupDefinitions as $gk => $def) {
                if ($gk === 'archived') {
                    continue;
                }
                foreach ($def['prefixes'] as $prefix) {
                    if (str_starts_with($rule->key, $prefix) || $rule->key === $prefix) {
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

        $recentPointEvents = UserPointTransaction::query()
            ->with('user:id,first_name,last_name,email')
            ->withSum('consumptions as consumed_points_total', 'points_consumed')
            ->latest('id')
            ->limit(50)
            ->get();

        $recentConversions = UserPointConversion::query()
            ->withSum('consumptions as consumed_points_total', 'points_consumed')
            ->latest('id')
            ->limit(30)
            ->get();

        $deprecatedRuleKeys = self::DEPRECATED_RULE_KEYS;

        return view('admin.system-settings.reputation.index', compact(
            'rules',
            'faLabels',
            'dimensionLabels',
            'conversionStatusLabels',
            'grouped',
            'recentPointEvents',
            'recentConversions',
            'deprecatedRuleKeys'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'weights' => 'required|array',
            'weights.*' => 'integer',
            'active' => 'sometimes|array',
            'description' => 'sometimes|array',
            'daily_cap' => 'sometimes|array',
            'daily_cap.*' => 'nullable|integer|min:0',
            'dimension' => 'sometimes|array',
            'dimension.*' => 'in:participation,reliability,expertise,civic_trust',
            'convertible' => 'sometimes|array',
        ]);

        foreach ($data['weights'] as $key => $weight) {
            $rule = ReputationRule::where('key', $key)->first();
            if (! $rule) {
                continue;
            }

            if (in_array($key, self::DEPRECATED_RULE_KEYS, true)) {
                $rule->active = false;
                $rule->convertible = false;
                $rule->save();
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

            $rule->save();
        }

        return back()->with('success', 'قواعد امتیازدهی با موفقیت ذخیره شد');
    }

    protected function seedFromConfig()
    {
        $weights = config('reputation.weights', []);
        $dailyCaps = config('reputation.daily_caps', []);
        $policyDefaults = config('reputation.policy_defaults', []);

        foreach ($weights as $key => $weight) {
            $policy = $policyDefaults[$key] ?? [];

            ReputationRule::firstOrCreate(
                ['key' => $key],
                [
                    'label' => str_replace('_', ' ', ucfirst($key)),
                    'weight' => (int) $weight,
                    'description' => null,
                    'module' => null,
                    'active' => true,
                    'daily_cap' => isset($dailyCaps[$key]) ? (int) $dailyCaps[$key] : null,
                    'dimension' => $policy['dimension'] ?? 'participation',
                    'convertible' => (bool) ($policy['convertible'] ?? false),
                    'repeat_policy' => $policy['repeat_policy'] ?? null,
                ]
            );
        }
    }
}
