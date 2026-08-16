<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Services\NajmHoda\Agents\GuideAgent;
use Carbon\CarbonInterface;

/**
 * Cognitive layer over a server-grounded group snapshot.
 *
 * The model never receives authority to mutate group state. It may interpret,
 * cluster and summarize only the factual records supplied by the snapshot and
 * must keep proposed decisions/actions distinct from confirmed system records.
 */
class NajmHodaGroupSemanticAnalysisService
{
    public function __construct(
        protected NajmHodaGroupKnowledgeService $knowledge,
        protected GuideAgent $guide
    ) {
    }

    /**
     * @return array{available:bool,text:?string,snapshot:array<string,mixed>}
     */
    public function analyze(Group $group, CarbonInterface $from, CarbonInterface $to, string $mode = 'summary'): array
    {
        $snapshot = $this->knowledge->snapshot($group, $from, $to, 120);

        if (!config('najm-hoda.provider.api_key') || (bool) config('najm-hoda.mock_mode', false)) {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        $source = $this->semanticSource($snapshot);
        if ($source['messages'] === [] && $source['posts'] === [] && $source['polls'] === [] && $source['action_items'] === []) {
            return ['available' => true, 'text' => 'در این بازه محتوای قابل تحلیل معنایی ثبت نشده است.', 'snapshot' => $snapshot];
        }

        $instruction = $mode === 'minutes'
            ? 'یک پیش‌نویس صورتجلسه معنایی تهیه کن: موضوعات اصلی، دیدگاه‌ها/اختلاف‌ها، نتیجه نظرسنجی‌های قابل استناد، تصمیمات قطعی ثبت‌شده، و در پایان «پیشنهادهای قابل بررسی» را جداگانه بیاور.'
            : 'یک خلاصه معنایی مدیریتی تهیه کن: موضوعات اصلی را خوشه‌بندی کن، تکرارها را یکی کن، دیدگاه‌ها و اختلاف‌های مهم را توضیح بده و موارد نیازمند پیگیری را مشخص کن.';

        $prompt = <<<PROMPT
تو لایه تحلیل معنایی نجم هدا برای یک گروه EarthCoop هستی.

{$instruction}

قواعد سخت:
1) فقط از SOURCE_JSON زیر استفاده کن. هیچ رویداد، نظر، تصمیم، شخص، نتیجه یا علت خارج از آن نساز.
2) آمار خام را تکرار نکن مگر برای فهم مطلب لازم باشد؛ هدف «فهم محتوا» است.
3) پست/نظرسنجی/پیام‌های تکراری یا آزمایشی را در صورت قابل تشخیص بودن تجمیع کن و صریحاً بگو که تکراری/آزمایشی به نظر می‌رسند، نه اینکه آن را واقعیت قطعی اعلام کنی.
4) «تصمیم قطعی/مصوبه/وظیفه ثبت‌شده» فقط چیزی است که در action_items وجود دارد. سایر موارد را فقط «پیشنهاد/برداشت قابل بررسی» بنام.
5) برای هر نکته مهم، منبع را با شناسه داخلی در پرانتز ذکر کن؛ مانند (پیام #12)، (پست #8)، (نظرسنجی #4). این شناسه‌ها فقط برای audit هستند و کاربر لازم نیست آنها را بداند یا وارد کند.
6) اگر داده برای یک نتیجه کافی نیست، صریحاً بگو کافی نیست.
7) فارسی روان، مدیریتی و کوتاه بنویس.
8) فرایند فکر، chain-of-thought، تحلیل مرحله‌به‌مرحله، یادداشت داخلی، بررسی قواعد، self-check یا توضیح اینکه چگونه به جواب رسیدی را هرگز خروجی نده.
9) فقط پاسخ نهایی قابل نمایش به مدیر را تولید کن و آن را دقیقاً بین تگ‌های <final> و </final> قرار بده. بیرون این دو تگ هیچ متنی ننویس.

SOURCE_JSON:
PROMPT;

        $rawResponse = trim($this->guide->ask(
            $prompt . "\n" . json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            []
        ));

        if ($rawResponse === '' || $this->looksUnavailable($rawResponse)) {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        $response = $this->extractSafeFinalAnswer($rawResponse);
        if ($response === null || $response === '') {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        return ['available' => true, 'text' => $response, 'snapshot' => $snapshot];
    }

    /** @param array<string,mixed> $snapshot */
    protected function semanticSource(array $snapshot): array
    {
        return [
            'group' => $snapshot['group'] ?? [],
            'window' => $snapshot['window'] ?? [],
            'messages' => array_values(array_filter((array) ($snapshot['messages'] ?? []), function ($item): bool {
                if (!is_array($item)) {
                    return false;
                }
                $text = trim((string) ($item['text'] ?? ''));
                // Empty/voice placeholders carry no semantic content until a transcript exists.
                return $text !== '' && !in_array($text, ['پیام صوتی', 'voice message'], true);
            })),
            'posts' => array_values((array) ($snapshot['posts'] ?? [])),
            'polls' => array_values((array) ($snapshot['polls'] ?? [])),
            'action_items' => array_values((array) ($snapshot['action_items'] ?? [])),
        ];
    }

    /**
     * Provider/model scratchpads are never user-visible. Semantic output is
     * fail-closed: only the explicit final envelope is accepted. If a provider
     * ignores the contract or emits reasoning leakage, the caller falls back to
     * the deterministic grounded report rather than exposing internal analysis.
     */
    protected function extractSafeFinalAnswer(string $response): ?string
    {
        if (preg_match('/<final>\s*(.*?)\s*<\/final>/isu', $response, $match) === 1) {
            $final = trim((string) ($match[1] ?? ''));
            if ($final === '' || $this->containsReasoningLeakage($final)) {
                return null;
            }

            return mb_substr($final, 0, 12000);
        }

        if ($this->containsReasoningLeakage($response)) {
            return null;
        }

        // Older/alternative providers may obey the no-reasoning instruction but
        // omit the requested envelope. Accept only a concise clean response; a
        // verbose unstructured answer is safer to reject and fall back.
        $clean = trim($response);
        if ($clean !== '' && mb_strlen($clean) <= 6000) {
            return $clean;
        }

        return null;
    }

    protected function containsReasoningLeakage(string $response): bool
    {
        $plain = mb_strtolower($response);
        $markers = [
            "here's a thinking process",
            'thinking process',
            'chain-of-thought',
            'chain of thought',
            'analyze user request',
            'analyse user request',
            'examine source_json',
            'process content per rules',
            "let's draft",
            'final check',
            'check against rules',
            'reasoning:',
            'internal reasoning',
            'scratchpad',
        ];

        foreach ($markers as $marker) {
            if (str_contains($plain, $marker)) {
                return true;
            }
        }

        return false;
    }

    protected function looksUnavailable(string $response): bool
    {
        $plain = mb_strtolower($response);
        return str_contains($plain, 'در حال حاضر قادر به پاسخگویی نیستم')
            || str_contains($plain, 'متأسفم، در حال حاضر')
            || str_contains($plain, 'متاسفم، در حال حاضر');
    }
}
