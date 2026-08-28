<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Services\SecretariatRegistrationIntelligenceService;
use Illuminate\Support\Facades\Gate;

/** Read-only advisor for items eligible for Registry capture and their deterministic taxonomy. */
class NajmHodaSecretariatRegistrationAdvisor
{
    public function __construct(private readonly SecretariatRegistrationIntelligenceService $intelligence) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message): ?array
    {
        if (! $this->looksLikeRequest($message)) return null;
        $officeId = $this->officeId($pageContext);
        if ($officeId <= 0) return null;

        $office = SecretariatOffice::query()->find($officeId);
        if (! $office || ! Gate::forUser($actor)->allows('inspect', $office)) {
            return $this->response('برای بررسی موارد لازم‌الثبت، دسترسی بازرسی/مدیریتی این دفتر لازم است.', 'blocked');
        }

        $result = $this->intelligence->inspectOffice($office, $actor, 40);
        $unrecorded = $result['unrecorded'] ?? [];
        $pending = $result['pending_registry'] ?? [];

        $lines = [
            'بررسی موارد لازم‌الثبت دبیرخانه:',
            'منابع قطعی که هنوز هیچ رکورد دبیرخانه‌ای ندارند: ' . count($unrecorded),
            'منابعی که وارد Registry شده‌اند ولی هنوز رسمی نشده‌اند: ' . count($pending),
        ];

        foreach (array_slice($unrecorded, 0, 10) as $item) {
            $lines[] = sprintf(
                'لازم‌الثبت: %s #%d — %s | نوع پیشنهادی: %s | دفتر: #%d | محرمانگی اولیه: %s',
                $item['source_kind'],
                $item['source_id'],
                mb_substr((string) $item['title'], 0, 160),
                $item['suggested_record_type'],
                $item['suggested_office_id'],
                $item['suggested_confidentiality'],
            );
        }
        foreach (array_slice($pending, 0, 10) as $item) {
            $lines[] = sprintf(
                'در انتظار رسمی‌شدن: Draft #%d — %s #%d — وضعیت Registry: %s',
                $item['record_id'],
                $item['source_kind'],
                $item['source_id'],
                $item['registry_status'],
            );
        }

        if ($unrecorded === [] && $pending === []) {
            $lines[] = 'در sourceهای قطعی بررسی‌شده (صورتجلسه approved و مصوبه adopted) شکاف ثبتی مشاهده نشد.';
        }
        $lines[] = '';
        $lines[] = 'این خروجی فقط پیشنهاد deterministic است؛ هیچ Draft یا ثبت رسمی ایجاد نشده است و محرمانگی پیشنهادی فعلاً از default خود Office می‌آید، نه حدس مدل.';

        return $this->response(implode("\n", $lines), 'registration_review', $result);
    }

    /** @param array<string,mixed> $pageContext */
    private function officeId(array $pageContext): int
    {
        if ((string) ($pageContext['resource_type'] ?? '') === 'secretariat_office') return (int) ($pageContext['resource_id'] ?? 0);
        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        return (int) ($resource['office_id'] ?? 0);
    }

    private function looksLikeRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        foreach (['لازم‌الثبت','لازم الثبت','ثبت نشده','ثبت‌نشده','چه چیزهایی باید ثبت','طبقه‌بندی ثبتی','taxonomy','registration review'] as $needle) {
            if (mb_stripos($plain, $needle) !== false) return true;
        }
        return false;
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success'=>true,'message'=>$message,'agent'=>'secretariat_registration_advisor','agent_name'=>'نجم‌هدا',
            'agent_icon'=>'✦','suggestions'=>[],'grounded'=>true,'status'=>$status,
        ], $extra);
    }
}
