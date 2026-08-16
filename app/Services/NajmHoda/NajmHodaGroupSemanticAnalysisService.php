<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Services\NajmHoda\Agents\GuideAgent;
use Carbon\CarbonInterface;

/**
 * Cognitive layer over a server-grounded group snapshot.
 *
 * The model is never trusted to render user-visible prose directly. It may only
 * return a small structured semantic interpretation whose references are checked
 * against the factual snapshot. Laravel renders the final Persian response.
 */
class NajmHodaGroupSemanticAnalysisService
{
    public function __construct(
        protected NajmHodaGroupKnowledgeService $knowledge,
        protected GuideAgent $guide
    ) {
    }

    /**
     * @return array{available:bool,text:?string,snapshot:array<string,mixed>,analysis?:array<string,mixed>}
     */
    public function analyze(Group $group, CarbonInterface $from, CarbonInterface $to, string $mode = 'summary'): array
    {
        $snapshot = $this->knowledge->snapshot($group, $from, $to, 120);

        if (! config('najm-hoda.provider.api_key') || (bool) config('najm-hoda.mock_mode', false)) {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        $source = $this->semanticSource($snapshot);
        if ($source['messages'] === [] && $source['posts'] === [] && $source['polls'] === [] && $source['action_items'] === []) {
            return [
                'available' => true,
                'text' => 'در این بازه محتوای قابل تحلیل معنایی ثبت نشده است.',
                'snapshot' => $snapshot,
                'analysis' => [],
            ];
        }

        $prompt = $this->buildStructuredPrompt($source, $mode);
        $rawResponse = trim($this->guide->ask($prompt, []));

        if ($rawResponse === '' || $this->looksUnavailable($rawResponse) || $this->containsReasoningLeakage($rawResponse)) {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        $decoded = $this->decodeStructuredResponse($rawResponse);
        if ($decoded === null) {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        $analysis = $this->validateAnalysis($decoded, $source);
        if ($analysis === null) {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        return [
            'available' => true,
            'text' => $this->renderAnalysis($analysis, $snapshot, $mode),
            'snapshot' => $snapshot,
            'analysis' => $analysis,
        ];
    }

    /** @param array<string,mixed> $source */
    protected function buildStructuredPrompt(array $source, string $mode): string
    {
        $purpose = $mode === 'minutes'
            ? 'برای تهیه صورتجلسه معنایی، موضوعات اصلی، اختلاف‌ها و موارد نیازمند پیگیری را استخراج کن.'
            : 'برای تهیه خلاصه مدیریتی، موضوعات اصلی، اختلاف‌ها و موارد نیازمند پیگیری را استخراج کن.';

        $schema = <<<'JSON'
{
  "topics": [
    {"title": "...", "insight": "...", "sources": ["message:12", "post:8"]}
  ],
  "disagreements": [
    {"title": "...", "insight": "...", "sources": ["message:12", "message:13"]}
  ],
  "followups": [
    {"title": "...", "reason": "...", "sources": ["poll:4"]}
  ],
  "data_limits": ["..."]
}
JSON;

        return "تو لایه تحلیل معنایی نجم هدا برای یک گروه EarthCoop هستی.\n\n"
            . $purpose . "\n\n"
            . "قواعد سخت:\n"
            . "1) فقط از SOURCE_JSON استفاده کن؛ هیچ شخص، علت، نتیجه، تصمیم یا رویداد خارج از آن نساز.\n"
            . "2) خروجی فقط یک JSON معتبر و دقیقاً مطابق schema زیر باشد؛ هیچ Markdown، توضیح، مقدمه، نتیجه‌گیری یا متن دیگری ننویس.\n"
            . "3) تکرارها و موارد آزمایشی را در یک topic تجمیع کن و در insight با لحن احتمالی/قابل بررسی بیان کن.\n"
            . "4) action_items تنها منبع تصمیم/وظیفه قطعی است. در topics/disagreements/followups چیزی را مصوبه قطعی ننام.\n"
            . "5) هر topic/disagreement/followup باید حداقل یک source معتبر داشته باشد. source فقط یکی از message:<id>، post:<id>، poll:<id> یا action_item:<id> باشد.\n"
            . "6) اگر اختلاف واقعی در داده نیست disagreements را [] برگردان. اگر پیگیری قابل استناد نیست followups را [] برگردان.\n"
            . "7) متن title/insight/reason فارسی، کوتاه و مدیریتی باشد.\n"
            . "8) فرایند فکر، reasoning، self-check یا تحلیل مرحله‌به‌مرحله را هرگز خروجی نده.\n\n"
            . "SCHEMA:\n{$schema}\n\n"
            . "SOURCE_JSON:\n"
            . json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string,mixed> $snapshot */
    protected function semanticSource(array $snapshot): array
    {
        return [
            'group' => $snapshot['group'] ?? [],
            'window' => $snapshot['window'] ?? [],
            'messages' => array_values(array_filter((array) ($snapshot['messages'] ?? []), function ($item): bool {
                if (! is_array($item)) {
                    return false;
                }
                $text = trim((string) ($item['text'] ?? ''));
                // Empty/voice placeholders carry no semantic content until a transcript exists.
                return $text !== '' && ! in_array($text, ['پیام صوتی', 'voice message'], true);
            })),
            'posts' => array_values((array) ($snapshot['posts'] ?? [])),
            'polls' => array_values((array) ($snapshot['polls'] ?? [])),
            'action_items' => array_values((array) ($snapshot['action_items'] ?? [])),
        ];
    }

    /** @return array<string,mixed>|null */
    protected function decodeStructuredResponse(string $response): ?array
    {
        $clean = trim($response);
        if (preg_match('/^```(?:json)?\s*(\{.*\})\s*```$/isu', $clean, $match) === 1) {
            $clean = trim((string) $match[1]);
        }

        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $decoded
     * @param array<string,mixed> $source
     * @return array<string,mixed>|null
     */
    protected function validateAnalysis(array $decoded, array $source): ?array
    {
        $allowedKeys = ['topics', 'disagreements', 'followups', 'data_limits'];
        foreach (array_keys($decoded) as $key) {
            if (! in_array((string) $key, $allowedKeys, true)) {
                return null;
            }
        }

        $allowedSources = $this->allowedSourceReferences($source);
        $topics = $this->validateInsightItems($decoded['topics'] ?? [], 'insight', $allowedSources, 8);
        $disagreements = $this->validateInsightItems($decoded['disagreements'] ?? [], 'insight', $allowedSources, 6);
        $followups = $this->validateInsightItems($decoded['followups'] ?? [], 'reason', $allowedSources, 8);
        $limits = $this->validateStringList($decoded['data_limits'] ?? [], 6, 350);

        if ($topics === null || $disagreements === null || $followups === null || $limits === null) {
            return null;
        }

        if ($topics === [] && $disagreements === [] && $followups === [] && $limits === []) {
            return null;
        }

        return [
            'topics' => $topics,
            'disagreements' => $disagreements,
            'followups' => $followups,
            'data_limits' => $limits,
        ];
    }

    /**
     * @param mixed $items
     * @param array<string,bool> $allowedSources
     * @return array<int,array<string,mixed>>|null
     */
    protected function validateInsightItems(mixed $items, string $bodyKey, array $allowedSources, int $limit): ?array
    {
        if (! is_array($items)) {
            return null;
        }

        $validated = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            if (! is_array($item)) {
                return null;
            }

            $title = $this->cleanScalar($item['title'] ?? null, 180);
            $body = $this->cleanScalar($item[$bodyKey] ?? null, 700);
            $sources = is_array($item['sources'] ?? null) ? $item['sources'] : null;
            if ($title === null || $body === null || $sources === null) {
                return null;
            }

            $validSources = [];
            foreach ($sources as $source) {
                if (! is_string($source)) {
                    return null;
                }
                $source = trim($source);
                if (isset($allowedSources[$source])) {
                    $validSources[] = $source;
                }
            }
            $validSources = array_values(array_unique($validSources));
            if ($validSources === []) {
                continue;
            }

            $validated[] = [
                'title' => $title,
                $bodyKey => $body,
                'sources' => array_slice($validSources, 0, 6),
            ];
        }

        return $validated;
    }

    /** @return array<int,string>|null */
    protected function validateStringList(mixed $items, int $limit, int $length): ?array
    {
        if (! is_array($items)) {
            return null;
        }

        $result = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $clean = $this->cleanScalar($item, $length);
            if ($clean === null) {
                return null;
            }
            $result[] = $clean;
        }

        return $result;
    }

    protected function cleanScalar(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $clean = trim(strip_tags((string) $value));
        $clean = preg_replace('/\s+/u', ' ', $clean) ?: $clean;
        if ($clean === '') {
            return null;
        }

        return mb_substr($clean, 0, $limit);
    }

    /** @param array<string,mixed> $source @return array<string,bool> */
    protected function allowedSourceReferences(array $source): array
    {
        $allowed = [];
        foreach (['messages' => 'message', 'posts' => 'post', 'polls' => 'poll', 'action_items' => 'action_item'] as $key => $prefix) {
            foreach ((array) ($source[$key] ?? []) as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $allowed[$prefix . ':' . (int) $item['id']] = true;
                }
            }
        }

        return $allowed;
    }

    /**
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $snapshot
     */
    protected function renderAnalysis(array $analysis, array $snapshot, string $mode): string
    {
        $groupName = trim((string) data_get($snapshot, 'group.name', 'گروه'));
        $title = $mode === 'minutes'
            ? "صورتجلسهٔ تحلیلی — {$groupName}"
            : "خلاصهٔ تحلیلی — {$groupName}";
        $lines = [$title];

        $topics = (array) ($analysis['topics'] ?? []);
        if ($topics !== []) {
            $lines[] = '';
            $lines[] = $mode === 'minutes' ? 'موضوعات و برداشت‌های قابل استناد:' : 'موضوعات اصلی:';
            foreach ($topics as $item) {
                $lines[] = '• ' . $item['title'] . ': ' . $item['insight'] . ' ' . $this->renderSources((array) $item['sources']);
            }
        }

        $disagreements = (array) ($analysis['disagreements'] ?? []);
        if ($disagreements !== []) {
            $lines[] = '';
            $lines[] = 'اختلاف‌ها یا دیدگاه‌های متفاوت:';
            foreach ($disagreements as $item) {
                $lines[] = '• ' . $item['title'] . ': ' . $item['insight'] . ' ' . $this->renderSources((array) $item['sources']);
            }
        }

        $actionItems = (array) ($snapshot['action_items'] ?? []);
        $lines[] = '';
        $lines[] = 'تصمیمات/وظایف قطعی ثبت‌شده:';
        if ($actionItems === []) {
            $lines[] = '• مورد ثبت‌شده‌ای وجود ندارد.';
        } else {
            foreach (array_slice($actionItems, 0, 10) as $item) {
                $titleText = trim((string) ($item['title'] ?? ''));
                $assignee = trim((string) ($item['assignee_name'] ?? ''));
                $suffix = $assignee !== '' ? " — مسئول: {$assignee}" : '';
                $lines[] = '• ' . $titleText . $suffix . ' ' . $this->renderSources(['action_item:' . (int) ($item['id'] ?? 0)]);
            }
        }

        $followups = (array) ($analysis['followups'] ?? []);
        if ($followups !== []) {
            $lines[] = '';
            $lines[] = 'پیشنهادهای نیازمند پیگیری:';
            foreach ($followups as $item) {
                $lines[] = '• ' . $item['title'] . ': ' . $item['reason'] . ' ' . $this->renderSources((array) $item['sources']);
            }
        }

        $limits = (array) ($analysis['data_limits'] ?? []);
        if ($limits !== []) {
            $lines[] = '';
            $lines[] = 'محدودیت داده:';
            foreach ($limits as $limit) {
                $lines[] = '• ' . $limit;
            }
        }

        $lines[] = '';
        $lines[] = 'این تحلیل فقط بر داده‌های واقعی snapshot گروه تکیه دارد؛ متن نهایی توسط EarthCoop از خروجی ساختاریافته و اعتبارسنجی‌شده ساخته شده است.';

        return implode("\n", $lines);
    }

    /** @param array<int,string> $sources */
    protected function renderSources(array $sources): string
    {
        $labels = [];
        foreach ($sources as $source) {
            if (! preg_match('/^(message|post|poll|action_item):(\d+)$/', (string) $source, $match)) {
                continue;
            }
            $label = match ($match[1]) {
                'message' => 'پیام',
                'post' => 'پست',
                'poll' => 'نظرسنجی',
                'action_item' => 'اقدام',
                default => 'منبع',
            };
            $labels[] = $label . ' #' . $match[2];
        }

        return $labels === [] ? '' : '(' . implode('، ', $labels) . ')';
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
