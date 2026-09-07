<?php

namespace App\Services;

use App\Helpers\BaharMoney;
use App\Models\ReputationRule;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use Illuminate\Support\Collection;

class ParticipationCreditRegulationService
{
    private const DIMENSION_LABELS = [
        'participation' => 'مشارکت',
        'reliability' => 'اعتمادپذیری',
        'expertise' => 'تخصص',
        'civic_trust' => 'اعتماد مدنی',
    ];

    private const ACTION_LABELS = [
        'email_verified' => 'تأیید ایمیل',
        'profile_completed' => 'تکمیل پروفایل',
        'profile_photo_uploaded' => 'افزودن تصویر پروفایل',
        'social_links_added' => 'افزودن لینک شبکه‌های اجتماعی',
        'documents_uploaded' => 'افزودن مدارک',
        'bio_added' => 'افزودن معرفی‌نامه',
        'invite_member' => 'دعوت موفق عضو جدید',
        'membership_fee_paid' => 'پرداخت حق عضویت سالانه',
        'post_created' => 'ایجاد پست',
        'post_liked' => 'پسند پست',
        'post_upvoted' => 'رأی مثبت به پست',
        'comment_created' => 'ایجاد دیدگاه',
        'comment_liked' => 'پسند دیدگاه',
        'comment_upvoted' => 'رأی مثبت به دیدگاه',
        'poll_created' => 'ایجاد نظرسنجی',
        'poll_participated' => 'مشارکت در نظرسنجی',
        'bid_placed' => 'ثبت پیشنهاد خرید',
        'bid_won' => 'برنده‌شدن در پیشنهاد',
        'successful_settlement' => 'تسویه موفق',
        'elected_manager' => 'پذیرش مسئولیت مدیر منتخب',
        'elected_inspector' => 'پذیرش مسئولیت بازرس منتخب',
        'professional_referral_completed' => 'تکمیل ارجاع تخصصی تأییدشده',
        'report_received' => 'گزارش تخلف تأییدشده',
        'bid_canceled' => 'لغو پیشنهاد',
        'fraud' => 'تقلب تأییدشده',
    ];

    private const TIER_LABELS = [
        'Bronze' => 'برنزی',
        'Silver' => 'نقره‌ای',
        'Gold' => 'طلایی',
        'Platinum' => 'پلاتینی',
    ];

    private const REPEAT_POLICY_LABELS = [
        'once' => 'فقط یک‌بار',
        'once_per_context' => 'یک‌بار برای هر مصداق',
        'daily' => 'یک‌بار در روز',
        'repeatable' => 'تکرارپذیر',
    ];

    public function __construct(private readonly MonetaryPolicyService $monetaryPolicyService)
    {
    }

    public function snapshot(): array
    {
        $policy = $this->monetaryPolicyService->current();
        $policyVersion = ! empty($policy['version_id'])
            ? MonetaryPolicyVersion::find($policy['version_id'])
            : null;

        $ratio = max(1, (int) data_get($policy, 'parameters.reputation_to_gol_ratio', 100));

        return [
            'policySnapshot' => [
                'monetary_version' => $policy['version'],
                'monetary_source' => $policy['source'],
                'monetary_source_label' => $policy['source'] === 'versioned_policy'
                    ? 'سیاست پولی نسخه‌دار'
                    : 'تنظیمات سازگار قدیمی',
                'effective_from' => $policyVersion?->effective_from,
                'effective_until' => $policyVersion?->effective_until,
                'generated_at' => now(),
            ],
            'dimensions' => $this->dimensions(),
            'tiers' => $this->tiers(),
            'actionRules' => $this->actionRules(),
            'conversion' => [
                'enabled' => (bool) data_get($policy, 'parameters.reputation_conversion_enabled', false),
                'points_per_gol' => $ratio,
                'gol_per_bahar' => BaharMoney::GOL_PER_BAHAR,
                'points_per_bahar' => $ratio * BaharMoney::GOL_PER_BAHAR,
                'minimum_convertible_points' => $ratio,
                'policy_version' => $policy['version'],
                'policy_source' => $policy['source'],
            ],
        ];
    }

    private function dimensions(): array
    {
        return collect(self::DIMENSION_LABELS)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    private function tiers(): array
    {
        return collect(config('reputation.tiers', []))
            ->map(function ($threshold, $name) {
                return [
                    'key' => (string) $name,
                    'label' => self::TIER_LABELS[$name] ?? (string) $name,
                    'minimum_points' => (int) $threshold,
                ];
            })
            ->sortBy('minimum_points')
            ->values()
            ->all();
    }

    private function actionRules(): array
    {
        $databaseRules = ReputationRule::query()->get()->keyBy('key');
        $keys = collect(config('reputation.weights', []))
            ->keys()
            ->merge($databaseRules->keys())
            ->unique()
            ->reject(fn (string $key) => in_array($key, ['election_candidate', 'election_participated'], true));

        return $keys
            ->map(fn (string $key) => $this->resolveRule($key, $databaseRules))
            ->sortBy([
                ['group_order', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function resolveRule(string $key, Collection $databaseRules): array
    {
        $rule = $databaseRules->get($key);
        $policyDefault = config("reputation.policy_defaults.{$key}", []);
        $configuredCap = config("reputation.daily_caps.{$key}");

        $weight = $rule ? (int) $rule->weight : (int) config("reputation.weights.{$key}", 0);
        // Match ReputationService::applyAction() exactly when the DB rule is absent.
        $dimension = $rule?->dimension ?: (string) config("reputation.dimensions.{$key}", 'participation');
        $convertible = $rule
            ? (bool) $rule->convertible
            : (bool) config("reputation.convertible.{$key}", false);
        $dailyCap = $rule
            ? ($rule->daily_cap !== null ? (int) $rule->daily_cap : null)
            : ($configuredCap !== null ? (int) $configuredCap : null);
        // Repeat policy is descriptive metadata when no DB rule exists; actual
        // fallback award behavior remains governed by the runtime call site.
        $repeatPolicy = $rule?->repeat_policy ?: ($policyDefault['repeat_policy'] ?? null);
        [$groupKey, $groupLabel, $groupOrder] = $this->groupFor($key, $rule?->module);

        return [
            'key' => $key,
            'label' => self::ACTION_LABELS[$key] ?? ($rule?->label ?: str_replace('_', ' ', $key)),
            'description' => $rule?->description,
            'weight' => $weight,
            'effect' => $weight > 0 ? 'award' : ($weight < 0 ? 'penalty' : 'neutral'),
            'active' => $rule ? (bool) $rule->active : true,
            'dimension' => $dimension,
            'dimension_label' => self::DIMENSION_LABELS[$dimension] ?? $dimension,
            'convertible' => $weight > 0 && $convertible,
            'daily_cap' => $dailyCap,
            'repeat_policy' => $repeatPolicy,
            'repeat_policy_label' => self::REPEAT_POLICY_LABELS[$repeatPolicy] ?? 'مطابق منطق همان رویداد',
            'source' => $rule ? 'database' : 'config_fallback',
            'source_label' => $rule ? 'تنظیم جاری سامانه' : 'پیش‌فرض اجرایی سامانه',
            'group_key' => $groupKey,
            'group_label' => $groupLabel,
            'group_order' => $groupOrder,
        ];
    }

    private function groupFor(string $key, ?string $module): array
    {
        if (in_array($key, ['invite_member', 'membership_fee_paid'], true)) {
            return ['membership', 'عضویت و دعوت', 10];
        }
        if (str_starts_with($key, 'profile_') || in_array($key, ['email_verified', 'social_links_added', 'documents_uploaded', 'bio_added'], true)) {
            return ['profile', 'ثبت‌نام و پروفایل', 20];
        }
        if (str_starts_with($key, 'post_') || str_starts_with($key, 'comment_')) {
            return ['content', 'محتوا و بازخورد', 30];
        }
        if (str_starts_with($key, 'poll_')) {
            return ['groups', 'گروه‌ها و نظرسنجی‌ها', 40];
        }
        if (str_starts_with($key, 'elected_') || str_starts_with($key, 'professional_referral_')) {
            return ['governance', 'حاکمیت و مسئولیت‌ها', 50];
        }
        if (str_starts_with($key, 'bid_') || $key === 'successful_settlement') {
            return ['stock', 'سهام و حراج', 60];
        }
        if (in_array($key, ['report_received', 'fraud'], true)) {
            return ['moderation', 'نظارت و تخلفات', 70];
        }

        return [$module ?: 'other', 'سایر رویدادها', 90];
    }
}
