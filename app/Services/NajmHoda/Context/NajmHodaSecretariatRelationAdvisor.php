<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatRelationSuggestionService;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only S7 advisor for deterministic Secretariat relation suggestions.
 * It never creates a relation; mutation remains an explicit human-authorized workflow.
 */
class NajmHodaSecretariatRelationAdvisor
{
    public function __construct(private readonly SecretariatRelationSuggestionService $suggestions) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message): ?array
    {
        if (! $this->looksLikeRelationRequest($message)) {
            return null;
        }

        $recordId = $this->recordId($pageContext);
        if ($recordId <= 0) {
            return null;
        }

        $record = SecretariatRecord::query()->find($recordId);
        if (! $record || ! Gate::forUser($actor)->allows('view', $record)) {
            return $this->response('شما به این سند دبیرخانه دسترسی ندارید.', 'blocked');
        }

        $items = $this->suggestions->suggestForRecord($record, $actor);
        if ($items === []) {
            return $this->response(
                'برای این سند، رابطه‌ای که از provenance صریح و قابل‌اثبات به‌دست آید پیدا نشد. نجم هدا از شباهت عنوان یا متن برای حدس‌زدن رابطه استفاده نمی‌کند.',
                'no_relation_suggestion',
                ['relation_suggestions' => []]
            );
        }

        $labels = [
            'report_of' => 'گزارشِ',
            'responds_to' => 'پاسخ به',
            'decision_of' => 'تصمیمِ',
        ];
        $lines = ['روابط قابل پیشنهاد بر پایه منشأ صریح سند:'];
        foreach ($items as $item) {
            $label = $labels[$item['relation_type']] ?? $item['relation_type'];
            $number = $item['target_registry_number'] ?: ('Draft #' . $item['target_record_id']);
            $title = trim((string) ($item['target_title'] ?? ''));
            $suffix = $title !== '' ? ' — ' . mb_substr($title, 0, 140) : '';
            $lines[] = "{$label}: {$number}{$suffix}";
        }
        $lines[] = '';
        $lines[] = 'این‌ها فقط پیشنهاد خواندنی‌اند؛ هیچ رابطه‌ای ایجاد یا تغییر داده نشده است.';

        return $this->response(implode("\n", $lines), 'relation_suggestions', ['relation_suggestions' => $items]);
    }

    /** @param array<string,mixed> $pageContext */
    private function recordId(array $pageContext): int
    {
        if ((string) ($pageContext['resource_type'] ?? '') === 'secretariat_record') {
            return (int) ($pageContext['resource_id'] ?? 0);
        }
        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        return (int) ($resource['record_id'] ?? 0);
    }

    private function looksLikeRelationRequest(string $message): bool
    {
        $plain = mb_strtolower($message);

        // Readiness/evidence prompts belong to the dedicated readiness advisor.
        foreach (['آمادگی', 'ناقص', 'کمبود', 'چه چیزی کم', 'شواهد', 'evidence', 'ready'] as $readinessCue) {
            if (mb_stripos($plain, $readinessCue) !== false) {
                return false;
            }
        }

        // Avoid the overly broad single token "مرتبط". Relation advice requires
        // an explicit relation-oriented phrase so normal evidence/search prompts
        // cannot be intercepted accidentally.
        foreach (['رابطه', 'ارتباط سند', 'اسناد مرتبط', 'پاسخ به چه', 'گزارش کدام', 'مصوبه مرتبط', 'relation', 'related record'] as $needle) {
            if (mb_stripos($plain, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_relation_advisor',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
