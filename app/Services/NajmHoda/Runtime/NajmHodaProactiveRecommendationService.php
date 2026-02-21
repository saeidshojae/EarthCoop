<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaProactiveRecommendationService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @param array<int, string> $goals
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $goals, array $context): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.recommendations.enabled', true)) {
            return [];
        }

        $recommendations = [];
        $maxItems = max(1, (int) config('najm-hoda.runtime.autonomy.recommendations.max_items', 5));
        $minConfidence = max(0.0, min(1.0, (float) config('najm-hoda.runtime.autonomy.recommendations.min_confidence', 0.4)));

        $errorRate = (float) ($context['error_rate_percent'] ?? 0.0);
        $unresolved = (int) ($context['unresolved_requests'] ?? 0);

        $chatMessages = (int) data_get($context, 'modules.chat.messages_recent', 0);
        $overdueItems = (int) data_get($context, 'modules.assignments.overdue', 0);
        $openItems = (int) data_get($context, 'modules.assignments.open', 0);

        if (in_array('stabilize_operations', $goals, true) && ($errorRate > 10 || $unresolved > 2)) {
            $confidence = $this->boundedConfidence(0.75 + min(0.2, $errorRate / 100));
            $recommendations[] = [
                'key' => 'ops_health_focus',
                'title' => 'تمرکز فوری روی پایداری عملیات',
                'module' => 'ops',
                'target' => 'runtime',
                'action_hint' => 'run_ops_monitor',
                'confidence' => $confidence,
                'reason' => 'نرخ خطا/درخواست‌های unresolved بالاتر از محدوده مطلوب است.',
            ];
        }

        if (in_array('improve_user_experience', $goals, true) && $chatMessages >= 10) {
            $confidence = $this->boundedConfidence(0.55 + min(0.25, $chatMessages / 200));
            $recommendations[] = [
                'key' => 'chat_engagement_push',
                'title' => 'پیشنهاد کمپین مشارکت گفت‌وگویی در گروه‌ها',
                'module' => 'chat',
                'target' => 'groups',
                'action_hint' => 'propose_engagement_recommendations',
                'confidence' => $confidence,
                'reason' => 'حجم گفت‌وگو در بازه اخیر مناسب است و ظرفیت افزایش مشارکت وجود دارد.',
            ];
        }

        if ($overdueItems > 0 || $openItems > 10) {
            $pressure = max($overdueItems, (int) floor($openItems / 2));
            $confidence = $this->boundedConfidence(0.5 + min(0.35, $pressure / 40));
            $recommendations[] = [
                'key' => 'assignment_backlog_relief',
                'title' => 'کاهش backlog تسک‌های نجم‌هدا',
                'module' => 'assignments',
                'target' => 'group_action_items',
                'action_hint' => 'prioritize_overdue_action_items',
                'confidence' => $confidence,
                'reason' => 'تعداد تسک‌های باز/معوق بالا است و نیاز به بازاولویت‌دهی وجود دارد.',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'key' => 'steady_improvement',
                'title' => 'حفظ روند پایدار و بهبود تدریجی',
                'module' => 'autonomy',
                'target' => 'global',
                'action_hint' => 'propose_engagement_recommendations',
                'confidence' => 0.45,
                'reason' => 'سیگنال بحرانی مشاهده نشد؛ تمرکز روی بهبود مستمر مناسب است.',
            ];
        }

        $recommendations = array_values(array_filter($recommendations, static function (array $item) use ($minConfidence): bool {
            return (float) ($item['confidence'] ?? 0.0) >= $minConfidence;
        }));

        usort($recommendations, static function (array $a, array $b): int {
            return (float) ($b['confidence'] ?? 0.0) <=> (float) ($a['confidence'] ?? 0.0);
        });

        $recommendations = array_slice($recommendations, 0, $maxItems);

        $this->eventBus->emit('najm_hoda.autonomy.recommendations.generated', [
            'goals' => $goals,
            'count' => count($recommendations),
            'top_key' => data_get($recommendations, '0.key'),
            'top_confidence' => (float) data_get($recommendations, '0.confidence', 0.0),
        ]);

        return $recommendations;
    }

    protected function boundedConfidence(float $value): float
    {
        return round(max(0.0, min(1.0, $value)), 2);
    }
}
