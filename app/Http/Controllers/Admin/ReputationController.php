<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointConversion;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;
use Illuminate\Http\Request;

class ReputationController extends Controller
{
    private const DEPRECATED_RULE_KEYS = ['election_candidate', 'election_participated'];

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
            'invite_member' => 'دعوت موفق عضو جدید',
            'membership_fee_paid' => 'پرداخت حق عضویت سالانه',
            'post_created' => 'ایجاد پست',
            'post_liked' => 'پسند پست',
            'post_upvoted' => 'پسندیدن پست',
            'comment_created' => 'ایجاد دیدگاه',
            'comment_liked' => 'پسند دیدگاه',
            'comment_upvoted' => 'پسندیدن دیدگاه',
            'bid_placed' => 'ثبت پیشنهاد',
            'bid_won' => 'برنده در مناقصه',
            'successful_settlement' => 'تسویه موفق',
            'report_received' => 'گزارش تخلف تأییدشده',
            'bid_canceled' => 'لغو پیشنهاد',
            'fraud' => 'تقلب تأییدشده',
            'poll_created' => 'ایجاد نظرسنجی',
            'poll_participated' => 'شرکت در نظرسنجی',
            'election_participated' => 'منسوخ — مشارکت عمومی انتخابات قدیمی',
            'election_candidate' => 'منسوخ — نامزدی در مدل قدیمی انتخابات',
            'elected_inspector' => 'انتخاب‌شده به عنوان بازرس',
            'elected_manager' => 'انتخاب‌شده به عنوان مدیر',
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
            'stock' => ['label' => 'سهام و حراج', 'prefixes' => ['bid_', 'successful_settlement', 'bid_won', 'bid_canceled']],
            'profile' => ['label' => 'ثبت‌نام و پروفایل', 'prefixes' => ['profile_', 'email_verified', 'profile_photo', 'social_links', 'documents_', 'bio_']],
            'groups' => ['label' => 'گروه‌ها و نظرسنجی‌ها', 'prefixes' => ['poll_']],
            'governance' => ['label' => 'حاکمیت و انتخابات', 'prefixes' => ['election_', 'elected_', 'professional_referral_']],
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

        return view('admin.system-settings.reputation.index', compact(
            'rules',
            'faLabels',
            'dimensionLabels',
            'conversionStatusLabels',
            'grouped',
            'recentPointEvents',
            'recentConversions'
        ));
    }

    public function update(Request $request)
    {
        if ($request->input('operation') === 'apply_confirmed_fraud') {
            return $this->applyConfirmedFraud($request);
        }

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

            if (in_array($key, self::DEPRECATED_RULE_KEYS, true)) {
                $rule->active = false;
                $rule->convertible = false;
                $rule->save();
                continue;
            }

            $rule->weight = (int) $weight;
            $rule->active = isset($data['active'][$key]);
            // Negative events are reputation penalties and can never create conversion capacity.
            $rule->convertible = (int) $weight > 0 && isset($data['convertible'][$key]);

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

    private function applyConfirmedFraud(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'case_reference' => 'required|string|max:120',
            'rationale' => 'nullable|string|max:1000',
        ], [
            'user_id.required' => 'شناسه کاربر الزامی است.',
            'user_id.exists' => 'کاربر انتخاب‌شده یافت نشد.',
            'case_reference.required' => 'شناسه پرونده یا مرجع تصمیم الزامی است.',
        ]);

        $rule = ReputationRule::where('key', 'fraud')->first();
        if (! $rule || ! $rule->active) {
            return back()->withErrors(['fraud' => 'قاعده «تقلب تأییدشده» غیرفعال است. ابتدا آن را در قواعد امتیازدهی فعال کنید.']);
        }

        $user = User::findOrFail($data['user_id']);
        $caseReference = trim($data['case_reference']);
        $eventKey = 'fraud:admin-case:' . hash('sha256', $caseReference) . ':user:' . $user->id;

        if (UserPointTransaction::where('event_key', $eventKey)->exists()) {
            return back()->withErrors(['fraud' => 'این پرونده برای این کاربر قبلاً در دفتر امتیازات ثبت شده است.']);
        }

        app(ReputationService::class)->applyAction(
            $user,
            'fraud',
            [
                'case_reference' => $caseReference,
                'rationale' => $data['rationale'] ?? null,
                'adjudicated_by' => auth()->id(),
            ],
            $user->id,
            'moderation.confirmed_fraud',
            $eventKey
        );

        return back()->with('success', 'اثر امتیازی تقلب تأییدشده بر اساس قاعده فعلی ثبت شد.');
    }

    protected function seedFromConfig()
    {
        $weights = config('reputation.weights', []);
        $dailyCaps = config('reputation.daily_caps', []);
        $policyDefaults = config('reputation.policy_defaults', []);

        foreach ($weights as $key => $w) {
            $policy = $policyDefaults[$key] ?? [];

            ReputationRule::firstOrCreate(
                ['key' => $key],
                [
                    'label' => str_replace('_', ' ', ucfirst($key)),
                    'weight' => (int) $w,
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
