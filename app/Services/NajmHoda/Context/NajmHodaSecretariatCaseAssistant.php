<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * S7 guided Case creation.
 *
 * Secretariat S5 has no draft-case lifecycle: creation allocates a race-safe
 * case number and opens the Case. Therefore S7 keeps proposal/preview pure and
 * performs the single deterministic create only after explicit confirmation.
 * It never attaches records or changes Case lifecycle automatically.
 */
class NajmHodaSecretariatCaseAssistant
{
    public function __construct(private readonly SecretariatCaseService $cases)
    {
    }

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if (! in_array((string) ($pageContext['page_kind'] ?? ''), ['secretariat_case_create', 'secretariat_cases'], true)) {
            return null;
        }

        $officeId = $this->officeId($pageContext);
        if ($officeId <= 0) {
            return null;
        }
        $office = SecretariatOffice::query()->find($officeId);
        if (! $office) {
            return null;
        }

        $pendingKey = $this->pendingKey($actor->id, $conversationId, $office->id);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->response('پیشنهاد ایجاد پرونده لغو شد و هیچ پرونده‌ای ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isCreateConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->createConfirmed($actor, $office, $pending);
        }

        if (! $this->looksLikeCaseRequest($message)) {
            return null;
        }

        $payload = $this->parseCase($message, $office);
        if ($payload === null) {
            return $this->response(
                "برای پیشنهاد پرونده، دست‌کم عنوان را مشخص کنید.\n"
                . "مثال: «پرونده بساز | عنوان: پیگیری مسئله آب | خلاصه: گردآوری مکاتبات و اسناد مرتبط | محرمانگی: office_members»",
                'needs_input'
            );
        }

        $probe = new SecretariatCase([
            'office_id' => $office->id,
            'title' => $payload['title'],
            'status' => 'open',
            'confidentiality' => $payload['confidentiality'],
        ]);
        $probe->setRelation('office', $office);
        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('شما مجوز ایجاد پرونده در این دفتر دبیرخانه را ندارید.', 'blocked');
        }

        Cache::put($pendingKey, [
            'actor_id' => (int) $actor->id,
            'office_id' => (int) $office->id,
            'payload' => $payload,
        ], now()->addMinutes(15));

        return $this->response(
            $this->preview($payload)
            . "\n\nاین فقط پیشنهاد است و هنوز هیچ پرونده‌ای ساخته نشده است. "
            . "در مدل فعلی دبیرخانه، ایجاد پرونده بلافاصله یک Case باز با شماره پرونده اختصاص می‌دهد. "
            . "اگر همین مورد باید ایجاد شود، «ایجاد پرونده» بفرستید. برای انصراف «لغو» بفرستید.",
            'awaiting_confirmation',
            ['case' => $payload]
        );
    }

    /** @param array<string,mixed> $pending */
    private function createConfirmed(User $actor, SecretariatOffice $office, array $pending): array
    {
        if ((int) ($pending['actor_id'] ?? 0) !== (int) $actor->id
            || (int) ($pending['office_id'] ?? 0) !== (int) $office->id
            || ! is_array($pending['payload'] ?? null)) {
            return $this->response('درخواست ایجاد پرونده معتبر نیست. لطفاً پیشنهاد را دوباره آماده کنید.', 'blocked');
        }

        $payload = (array) $pending['payload'];
        $probe = new SecretariatCase([
            'office_id' => $office->id,
            'title' => $payload['title'] ?? '',
            'status' => 'open',
            'confidentiality' => $payload['confidentiality'] ?? $office->default_confidentiality,
        ]);
        $probe->setRelation('office', $office);

        if (! Gate::forUser($actor)->allows('create', $probe)) {
            return $this->response('مجوز ایجاد پرونده دیگر معتبر نیست؛ هیچ پرونده‌ای ایجاد نشد.', 'blocked');
        }

        $case = $this->cases->create($office, $actor, $payload);

        return $this->response(
            "پرونده دبیرخانه ایجاد شد ({$case->case_number}). وضعیت آن open است. هیچ سندی به‌طور خودکار به پرونده افزوده نشده و هیچ تغییر lifecycle دیگری انجام نشده است.",
            'case_created',
            [
                'case_id' => (int) $case->id,
                'case_number' => (string) $case->case_number,
                'office_id' => (int) $office->id,
                'case_status' => (string) $case->status,
            ]
        );
    }

    /** @return array<string,mixed>|null */
    private function parseCase(string $message, SecretariatOffice $office): ?array
    {
        $title = $this->extract($message, ['عنوان'], ['خلاصه', 'محرمانگی']);
        if ($title === '') {
            return null;
        }
        $summary = $this->extract($message, ['خلاصه'], ['محرمانگی']);
        $confidentiality = $this->tokenValue($message, ['محرمانگی']) ?: (string) $office->default_confidentiality;
        if (! in_array($confidentiality, ['public', 'office_members', 'leadership', 'restricted', 'confidential'], true)) {
            return null;
        }

        return [
            'title' => mb_substr($title, 0, 500),
            'summary' => $summary !== '' ? mb_substr($summary, 0, 5000) : null,
            'confidentiality' => $confidentiality,
            'metadata' => ['prepared_by' => 'najm_hoda_s7'],
        ];
    }

    /** @param array<string,mixed> $payload */
    private function preview(array $payload): string
    {
        $lines = [
            'پیش‌نمایش ایجاد پرونده:',
            'عنوان: ' . (string) ($payload['title'] ?? ''),
            'محرمانگی: ' . (string) ($payload['confidentiality'] ?? ''),
        ];
        if (! empty($payload['summary'])) {
            $lines[] = 'خلاصه: ' . $payload['summary'];
        }
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $pageContext */
    private function officeId(array $pageContext): int
    {
        if ((string) ($pageContext['resource_type'] ?? '') === 'secretariat_office') {
            return (int) ($pageContext['resource_id'] ?? 0);
        }
        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        return (int) ($resource['office_id'] ?? 0);
    }

    private function looksLikeCaseRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        $case = mb_stripos($plain, 'پرونده') !== false || mb_stripos($plain, 'case') !== false;
        $verb = mb_stripos($plain, 'بساز') !== false || mb_stripos($plain, 'ایجاد') !== false
            || mb_stripos($plain, 'آماده') !== false || mb_stripos($plain, 'تهیه') !== false;
        return $case && $verb;
    }

    private function isCreateConfirmation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['ایجاد پرونده', 'پرونده را ایجاد کن', 'create case'], true);
    }

    private function isCancellation(string $message): bool
    {
        return in_array(trim(mb_strtolower($message)), ['لغو', 'انصراف', 'cancel'], true);
    }

    private function pendingKey(int $actorId, ?int $conversationId, int $officeId): string
    {
        return 'najm_hoda:secretariat_case:' . $actorId . ':' . ($conversationId ?: 0) . ':' . $officeId;
    }

    private function extract(string $text, array $labels, array $stops): string
    {
        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            $stopPattern = $stops === [] ? '$' : '(?=\\s*[|؛;]\\s*(?:' . implode('|', array_map(fn ($s) => preg_quote($s, '/'), $stops)) . ')\\s*[:：]|$)';
            if (preg_match('/(?:^|[|؛;])\\s*' . $quoted . '\\s*[:：]\\s*(.*?)' . $stopPattern . '/us', $text, $match)) {
                return trim((string) $match[1]);
            }
        }
        return '';
    }

    private function tokenValue(string $text, array $labels): string
    {
        return trim($this->extract($text, $labels, ['عنوان','خلاصه','محرمانگی']));
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_case',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
