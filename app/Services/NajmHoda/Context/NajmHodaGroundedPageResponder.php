<?php

namespace App\Services\NajmHoda\Context;

/**
 * Deterministic responder for questions whose answer already exists in
 * server-validated page context. These questions must not depend on an LLM:
 * doing so adds latency/provider fragility and can hallucinate UI that does not
 * exist. The LLM remains responsible for open-ended advice and drafting.
 */
class NajmHodaGroundedPageResponder
{
    /**
     * @param array<string,mixed> $pageContext
     * @return array<string,mixed>|null
     */
    public function respond(string $message, array $pageContext): ?array
    {
        $plain = $this->normalize($message);
        if ($plain === '') {
            return null;
        }

        $contracts = array_values(array_filter(
            (array) ($pageContext['capability_contracts'] ?? []),
            'is_array'
        ));
        $delegated = array_values(array_filter(
            (array) ($pageContext['delegated_actions'] ?? []),
            'is_array'
        ));

        if ($this->asksHowTo($plain)) {
            $contract = $this->findRequestedContract($plain, $contracts);
            if ($contract !== null) {
                return $this->response($this->renderContractHowTo($contract));
            }
        }

        if (!$this->asksPageIdentity($plain) && !$this->asksPageCapabilities($plain)) {
            return null;
        }

        $label = trim((string) ($pageContext['page_label'] ?? ''));
        if ($label === '') {
            return $this->response('در حال حاضر اطلاعات معتبر کافی برای تشخیص صفحه بازشده در اختیار ندارم.');
        }

        $lines = ["شما اکنون در صفحه «{$label}» هستید."];

        if ($contracts === []) {
            $lines[] = 'در این لحظه قابلیت قابل‌اعتمادی برای این صفحه در قراردادهای سیستم ثبت نشده است.';
        } else {
            $lines[] = '';
            $lines[] = 'کارهایی که خودتان در همین صفحه می‌توانید انجام دهید:';
            foreach ($contracts as $contract) {
                $lines[] = $this->renderContractSummary($contract);
            }
        }

        if ($this->mentionsDelegation($plain) || $delegated !== []) {
            $lines[] = '';
            if ($delegated === []) {
                $lines[] = 'در این صفحه فعلاً اقدام اجرایی قابل تفویضی به نجم هدا برای نقش فعلی شما ثبت نشده است.';
            } else {
                $lines[] = 'کارهایی که می‌توانید در چت خصوصی به نجم هدا بسپارید:';
                foreach ($delegated as $action) {
                    $label = trim((string) ($action['label'] ?? $action['id'] ?? 'اقدام'));
                    $suffix = (bool) ($action['requires_confirmation'] ?? false)
                        ? ' — قبل از اجرا پیش‌نمایش می‌دهم و تأیید شما لازم است.'
                        : '';
                    $lines[] = "• {$label}{$suffix}";
                }
                $lines[] = 'گفت‌وگوی اجرایی در همین چت خصوصی می‌ماند و فقط نتیجهٔ تأییدشده در گروه منتشر می‌شود.';
            }
        }

        return $this->response(implode("\n", $lines));
    }

    protected function asksPageIdentity(string $plain): bool
    {
        return $this->containsAny($plain, [
            'چه صفحه', 'کجا هستم', 'کجای', 'در کجا', 'صفحه فعلی', 'صفحه کنونی',
        ]);
    }

    protected function asksPageCapabilities(string $plain): bool
    {
        return $this->containsAny($plain, [
            'چه کارهایی', 'چه کارهائی', 'چه امکانات', 'چه قابلیت', 'میتونم انجام',
            'می توانم انجام', 'می‌تونم انجام', 'می‌توانم انجام', 'به تو بسپار', 'بسپارم',
        ]);
    }

    protected function asksHowTo(string $plain): bool
    {
        return $this->containsAny($plain, ['چطور', 'چگونه', 'روش ', 'مراحل ', 'دقیقاً چطور', 'دقیقا چطور']);
    }

    protected function mentionsDelegation(string $plain): bool
    {
        return $this->containsAny($plain, ['به تو بسپار', 'بسپارم', 'تفویض', 'تو انجام بده', 'برام انجام بده']);
    }

    /**
     * @param array<int,array<string,mixed>> $contracts
     * @return array<string,mixed>|null
     */
    protected function findRequestedContract(string $plain, array $contracts): ?array
    {
        $keywords = [
            'create_poll' => ['نظرسنجی', 'نظر سنجی'],
            'create_post' => ['پست', 'نوشته'],
            'send_message' => ['پیام متنی', 'پیام بفرستم', 'پیام ارسال'],
            'send_voice' => ['پیام صوتی', 'صدا', 'ویس'],
            'vote' => ['رای', 'رأی'],
            'read_group_feed' => ['گفتگو', 'گفت‌وگو', 'فید'],
        ];

        foreach ($contracts as $contract) {
            $id = (string) ($contract['id'] ?? '');
            $needles = $keywords[$id] ?? [];
            $label = trim((string) ($contract['label'] ?? ''));
            if ($label !== '') {
                $needles[] = $this->normalize($label);
            }

            if ($this->containsAny($plain, $needles)) {
                return $contract;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $contract */
    protected function renderContractSummary(array $contract): string
    {
        $label = trim((string) ($contract['label'] ?? $contract['id'] ?? 'قابلیت'));
        $summary = trim((string) ($contract['summary'] ?? ''));

        if ($summary === '') {
            return "• {$label}";
        }

        return "• {$label}: {$summary}";
    }

    /** @param array<string,mixed> $contract */
    protected function renderContractHowTo(array $contract): string
    {
        $label = trim((string) ($contract['label'] ?? $contract['id'] ?? 'این کار'));
        $summary = trim((string) ($contract['summary'] ?? ''));
        $steps = array_values(array_filter(array_map(
            static fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
            (array) data_get($contract, 'ui.steps', [])
        )));

        $lines = ["برای «{$label}» در همین صفحه:"];
        if ($summary !== '') {
            $lines[] = $summary;
        }
        foreach ($steps as $index => $step) {
            $lines[] = ($index + 1) . '. ' . $step;
        }

        return implode("\n", $lines);
    }

    /** @return array<string,mixed> */
    protected function response(string $message): array
    {
        return [
            'success' => true,
            'message' => $message,
            'agent' => 'runtime',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '🧭',
            'suggestions' => [],
            'grounded_page_response' => true,
        ];
    }

    protected function normalize(string $value): string
    {
        $plain = mb_strtolower(trim(strip_tags($value)));
        $plain = str_replace(['ي', 'ك', 'ۀ'], ['ی', 'ک', 'ه'], $plain);
        return preg_replace('/\s+/u', ' ', $plain) ?: $plain;
    }

    /** @param array<int,string> $needles */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
