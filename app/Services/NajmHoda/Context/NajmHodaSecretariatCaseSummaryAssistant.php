<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only deterministic case summary. Hidden records are not counted or named.
 */
class NajmHodaSecretariatCaseSummaryAssistant
{
    public function __construct(private readonly SecretariatAclService $acl) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message): ?array
    {
        if (! $this->looksLikeCaseSummaryRequest($message)) {
            return null;
        }

        $caseId = $this->caseId($pageContext);
        if ($caseId <= 0) {
            return null;
        }

        $case = SecretariatCase::query()->with('office')->find($caseId);
        if (! $case || ! Gate::forUser($actor)->allows('view', $case)) {
            return $this->response('شما به این پرونده دبیرخانه دسترسی ندارید.', 'blocked');
        }

        $visible = $case->records()
            ->with('office')
            ->orderByDesc('secretariat_case_records.added_at')
            ->get()
            ->filter(fn (SecretariatRecord $record) => Gate::forUser($actor)->allows('view', $record))
            ->values();

        $items = [];
        foreach ($visible->take(20) as $record) {
            if ($record->confidentiality === 'confidential') {
                $this->acl->auditSensitiveAccess($record, $actor, [
                    'channel' => 'case_summary',
                    'case_id' => (int) $case->id,
                ]);
            }

            $items[] = [
                'record_id' => (int) $record->id,
                'registry_number' => $record->registry_number,
                'record_type' => $record->record_type,
                'status' => $record->status,
                'title' => $record->title,
                'role' => $record->pivot?->role,
                'link_type' => $record->pivot?->link_type,
            ];
        }

        $countsByType = collect($items)->countBy('record_type')->all();
        $lines = [
            'خلاصه پرونده دبیرخانه:',
            'شماره پرونده: ' . $case->case_number,
            'عنوان: ' . $case->title,
            'وضعیت: ' . $case->status,
            'تعداد اسناد قابل مشاهده برای شما: ' . count($items),
        ];
        if (trim((string) $case->summary) !== '') {
            $lines[] = 'شرح پرونده: ' . mb_substr((string) $case->summary, 0, 1000);
        }
        if ($countsByType !== []) {
            $lines[] = 'ترکیب اسناد قابل مشاهده: ' . collect($countsByType)
                ->map(fn ($count, $type) => $type . '=' . $count)
                ->implode('، ');
        }
        foreach ($items as $item) {
            $number = $item['registry_number'] ?: ('Record #' . $item['record_id']);
            $lines[] = '- ' . $number . ' | ' . $item['record_type'] . ' | ' . $item['status'] . ' | ' . mb_substr((string) $item['title'], 0, 160);
        }
        $lines[] = '';
        $lines[] = 'این خلاصه فقط از اسنادی ساخته شده که شما مجاز به مشاهده آن‌ها هستید؛ وجود یا تعداد اسناد غیرمجاز افشا نمی‌شود و هیچ تغییری در پرونده ایجاد نشده است.';

        return $this->response(implode("\n", $lines), 'case_summary', [
            'case_id' => (int) $case->id,
            'visible_records' => $items,
            'visible_record_count' => count($items),
        ]);
    }

    /** @param array<string,mixed> $pageContext */
    private function caseId(array $pageContext): int
    {
        if ((string) ($pageContext['resource_type'] ?? '') === 'secretariat_case') {
            return (int) ($pageContext['resource_id'] ?? 0);
        }
        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        return (int) ($resource['case_id'] ?? 0);
    }

    private function looksLikeCaseSummaryRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        foreach (['خلاصه پرونده', 'پرونده را خلاصه', 'وضعیت پرونده', 'case summary', 'summarize case'] as $needle) {
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
            'agent' => 'secretariat_case_summary',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
