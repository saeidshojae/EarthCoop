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
 * against the factual snapshot. Laravel validates evidence and renders the final
 * Persian response.
 */
class NajmHodaGroupSemanticAnalysisService
{
    public function __construct(
        protected NajmHodaGroupKnowledgeService $knowledge,
        protected GuideAgent $guide
    ) {
    }

    /** @return array{available:bool,text:?string,snapshot:array<string,mixed>,analysis?:array<string,mixed>} */
    public function analyze(Group $group, CarbonInterface $from, CarbonInterface $to, string $mode = 'summary'): array
    {
        $snapshot = $this->knowledge->snapshot($group, $from, $to, 120);
        if (! config('najm-hoda.provider.api_key') || (bool) config('najm-hoda.mock_mode', false)) {
            return ['available' => false, 'text' => null, 'snapshot' => $snapshot];
        }

        $source = $this->semanticSource($snapshot);
        if ($source['messages'] === [] && $source['posts'] === [] && $source['polls'] === [] && $source['action_items'] === []) {
            return ['available' => true, 'text' => 'در این بازه محتوای قابل تحلیل معنایی ثبت نشده است.', 'snapshot' => $snapshot, 'analysis' => []];
        }

        $rawResponse = trim($this->guide->ask($this->buildStructuredPrompt($source, $mode), []));
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
    {"title":"...","claim_type":"fact|interpretation|suggestion","insight":"...","sources":["message:12"],"evidence":[{"source":"message:12","quote":"عبارت دقیق موجود در منبع"}]}
  ],
  "disagreements": [
    {"title":"...","claim_type":"fact|interpretation","insight":"...","sources":["message:12","message:13"],"evidence":[{"source":"message:12","quote":"..."}]}
  ],
  "followups": [
    {"title":"...","claim_type":"suggestion","reason":"...","sources":["poll:4"],"evidence":[{"source":"poll:4","quote":"..."}]}
  ],
  "data_limits": ["..."]
}
JSON;

        return "تو لایه تحلیل معنایی نجم هدا برای یک گروه EarthCoop هستی.\n\n"
            . $purpose . "\n\n"
            . "قواعد سخت:\n"
            . "1) فقط از SOURCE_JSON استفاده کن؛ هیچ شخص، علت، نتیجه، تصمیم یا رویداد خارج از آن نساز.\n"
            . "2) خروجی فقط یک JSON معتبر مطابق schema باشد؛ هیچ Markdown یا متن دیگری ننویس.\n"
            . "3) claim_type=fact فقط وقتی مجاز است که همه جزئیات insight مستقیماً از evidence پشتیبانی شوند. هیچ واژه محتوایی تازه‌ای مثل ساعت/زمان/مکان/علت/نتیجه اضافه نکن.\n"
            . "4) interpretation برداشت تحلیلی قابل بررسی است، نه واقعیت قطعی. suggestion فقط پیشنهاد پیگیری است.\n"
            . "5) evidence.quote باید عیناً بخشی از متن همان source باشد؛ ساختن یا بازنویسی quote ممنوع است.\n"
            . "6) تکرارها و موارد آزمایشی را در یک topic تجمیع کن و claim_type آن را interpretation قرار بده مگر evidence مستقیم چیز دیگری را ثابت کند.\n"
            . "7) action_items تنها منبع تصمیم/وظیفه قطعی است.\n"
            . "8) هر item باید حداقل یک source و evidence معتبر داشته باشد. source فقط message:<id>، post:<id>، poll:<id> یا action_item:<id> است.\n"
            . "9) اگر اختلاف واقعی نیست disagreements=[] و اگر پیگیری قابل استناد نیست followups=[].\n"
            . "10) متن فارسی، کوتاه و مدیریتی باشد و reasoning/self-check هرگز خروجی داده نشود.\n\n"
            . "SCHEMA:\n{$schema}\n\nSOURCE_JSON:\n"
            . json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string,mixed> $snapshot */
    protected function semanticSource(array $snapshot): array
    {
        return [
            'group' => $snapshot['group'] ?? [],
            'window' => $snapshot['window'] ?? [],
            'messages' => array_values(array_filter((array) ($snapshot['messages'] ?? []), function ($item): bool {
                if (! is_array($item)) return false;
                $text = trim((string) ($item['text'] ?? ''));
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

    /** @param array<string,mixed> $decoded @param array<string,mixed> $source @return array<string,mixed>|null */
    protected function validateAnalysis(array $decoded, array $source): ?array
    {
        $allowedKeys = ['topics', 'disagreements', 'followups', 'data_limits'];
        foreach (array_keys($decoded) as $key) {
            if (! in_array((string) $key, $allowedKeys, true)) return null;
        }

        $sourceTexts = $this->sourceTextMap($source);
        $topics = $this->validateInsightItems($decoded['topics'] ?? [], 'insight', $sourceTexts, 8, ['fact', 'interpretation', 'suggestion']);
        $disagreements = $this->validateInsightItems($decoded['disagreements'] ?? [], 'insight', $sourceTexts, 6, ['fact', 'interpretation']);
        $followups = $this->validateInsightItems($decoded['followups'] ?? [], 'reason', $sourceTexts, 8, ['suggestion']);
        $limits = $this->validateStringList($decoded['data_limits'] ?? [], 6, 350);
        if ($topics === null || $disagreements === null || $followups === null || $limits === null) return null;
        if ($topics === [] && $disagreements === [] && $followups === [] && $limits === []) return null;

        return compact('topics', 'disagreements', 'followups') + ['data_limits' => $limits];
    }

    /** @param mixed $items @param array<string,string> $sourceTexts @param array<int,string> $allowedClaimTypes @return array<int,array<string,mixed>>|null */
    protected function validateInsightItems(mixed $items, string $bodyKey, array $sourceTexts, int $limit, array $allowedClaimTypes): ?array
    {
        if (! is_array($items)) return null;
        $validated = [];

        foreach (array_slice($items, 0, $limit) as $item) {
            if (! is_array($item)) return null;
            $title = $this->cleanScalar($item['title'] ?? null, 180);
            $body = $this->cleanScalar($item[$bodyKey] ?? null, 700);
            $claimType = is_string($item['claim_type'] ?? null) ? trim((string) $item['claim_type']) : '';
            $sources = is_array($item['sources'] ?? null) ? $item['sources'] : null;
            $evidence = is_array($item['evidence'] ?? null) ? $item['evidence'] : null;
            if ($title === null || $body === null || ! in_array($claimType, $allowedClaimTypes, true) || $sources === null || $evidence === null) return null;

            $validSources = [];
            foreach ($sources as $source) {
                if (! is_string($source)) return null;
                $source = trim($source);
                if (isset($sourceTexts[$source])) $validSources[] = $source;
            }
            $validSources = array_values(array_unique($validSources));
            if ($validSources === []) continue;

            $validEvidence = $this->validateEvidence($evidence, $sourceTexts, $validSources);
            if ($validEvidence === []) continue;

            if ($claimType === 'fact' && ! $this->factSupportedByEvidence($body, $validEvidence)) {
                $claimType = 'interpretation';
            }

            $validated[] = [
                'title' => $title,
                'claim_type' => $claimType,
                $bodyKey => $body,
                'sources' => array_slice($validSources, 0, 6),
                'evidence' => array_slice($validEvidence, 0, 6),
            ];
        }

        return $validated;
    }

    /** @param array<int,mixed> $evidence @param array<string,string> $sourceTexts @param array<int,string> $validSources @return array<int,array{source:string,quote:string}> */
    protected function validateEvidence(array $evidence, array $sourceTexts, array $validSources): array
    {
        $valid = [];
        foreach (array_slice($evidence, 0, 8) as $item) {
            if (! is_array($item) || ! is_string($item['source'] ?? null) || ! is_scalar($item['quote'] ?? null)) continue;
            $source = trim((string) $item['source']);
            $quote = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item['quote'])) ?: (string) $item['quote']);
            if ($quote === '' || ! in_array($source, $validSources, true) || ! isset($sourceTexts[$source])) continue;
            if (! str_contains($this->normalizeForEvidence($sourceTexts[$source]), $this->normalizeForEvidence($quote))) continue;
            $valid[] = ['source' => $source, 'quote' => mb_substr($quote, 0, 350)];
        }
        return $valid;
    }

    /** @param array<int,array{source:string,quote:string}> $evidence */
    protected function factSupportedByEvidence(string $claim, array $evidence): bool
    {
        $evidenceText = implode(' ', array_map(static fn (array $item): string => $item['quote'], $evidence));
        $evidenceTokens = array_fill_keys($this->meaningfulTokens($evidenceText), true);
        $claimTokens = $this->meaningfulTokens($claim);
        if ($claimTokens === []) return false;
        foreach ($claimTokens as $token) {
            if (! isset($evidenceTokens[$token])) return false;
        }
        return true;
    }

    /** @return array<int,string> */
    protected function meaningfulTokens(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $this->normalizeForEvidence($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = array_fill_keys(['از','به','در','با','برای','که','و','یا','این','آن','را','یک','است','هست','شده','شود','فعلی','فعلا','حال','مورد','موارد','درباره','روی','توسط','می','های','ها'], true);
        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 3 || isset($stop[$part])) continue;
            $tokens[$part] = true;
        }
        return array_keys($tokens);
    }

    protected function normalizeForEvidence(string $text): string
    {
        $text = mb_strtolower(strip_tags($text));
        $text = str_replace(['ي', 'ك', 'ۀ', '‌'], ['ی', 'ک', 'ه', ' '], $text);
        return trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
    }

    /** @return array<int,string>|null */
    protected function validateStringList(mixed $items, int $limit, int $length): ?array
    {
        if (! is_array($items)) return null;
        $result = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $clean = $this->cleanScalar($item, $length);
            if ($clean === null) return null;
            $result[] = $clean;
        }
        return $result;
    }

    protected function cleanScalar(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) return null;
        $clean = trim(strip_tags((string) $value));
        $clean = preg_replace('/\s+/u', ' ', $clean) ?: $clean;
        return $clean === '' ? null : mb_substr($clean, 0, $limit);
    }

    /** @param array<string,mixed> $source @return array<string,string> */
    protected function sourceTextMap(array $source): array
    {
        $map = [];
        foreach ((array) ($source['messages'] ?? []) as $item) if (is_array($item) && isset($item['id'])) $map['message:' . (int) $item['id']] = trim((string) ($item['text'] ?? ''));
        foreach ((array) ($source['posts'] ?? []) as $item) if (is_array($item) && isset($item['id'])) $map['post:' . (int) $item['id']] = trim((string) ($item['title'] ?? '') . ' ' . (string) ($item['text'] ?? ''));
        foreach ((array) ($source['polls'] ?? []) as $item) if (is_array($item) && isset($item['id'])) $map['poll:' . (int) $item['id']] = trim((string) ($item['question'] ?? '') . ' ' . implode(' ', array_map('strval', (array) ($item['options'] ?? []))));
        foreach ((array) ($source['action_items'] ?? []) as $item) if (is_array($item) && isset($item['id'])) $map['action_item:' . (int) $item['id']] = trim(implode(' ', [(string) ($item['title'] ?? ''), (string) ($item['details'] ?? ''), (string) ($item['assignee_name'] ?? ''), (string) ($item['status'] ?? '')]));
        return $map;
    }

    /** @param array<string,mixed> $analysis @param array<string,mixed> $snapshot */
    protected function renderAnalysis(array $analysis, array $snapshot, string $mode): string
    {
        $lines = [];
        $topics = (array) ($analysis['topics'] ?? []);
        if ($topics !== []) {
            $lines[] = $mode === 'minutes' ? 'موضوعات و برداشت‌های قابل استناد:' : 'موضوعات اصلی:';
            foreach ($topics as $item) {
                $prefix = match ((string) ($item['claim_type'] ?? 'interpretation')) { 'fact' => 'واقعیت مستند', 'suggestion' => 'پیشنهاد', default => 'برداشت تحلیلی' };
                $lines[] = '• [' . $prefix . '] ' . $item['title'] . ': ' . $item['insight'] . ' ' . $this->renderSources((array) $item['sources']);
            }
        }

        $disagreements = (array) ($analysis['disagreements'] ?? []);
        if ($disagreements !== []) {
            $lines[] = '';
            $lines[] = 'اختلاف‌ها یا دیدگاه‌های متفاوت:';
            foreach ($disagreements as $item) {
                $prefix = (($item['claim_type'] ?? '') === 'fact') ? 'واقعیت مستند' : 'برداشت تحلیلی';
                $lines[] = '• [' . $prefix . '] ' . $item['title'] . ': ' . $item['insight'] . ' ' . $this->renderSources((array) $item['sources']);
            }
        }

        $actionItems = (array) ($snapshot['action_items'] ?? []);
        $lines[] = '';
        $lines[] = 'تصمیمات/وظایف قطعی ثبت‌شده:';
        if ($actionItems === []) {
            $lines[] = '• مورد ثبت‌شده‌ای وجود ندارد.';
        } else {
            foreach (array_slice($actionItems, 0, 10) as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                $assignee = trim((string) ($item['assignee_name'] ?? ''));
                $lines[] = '• ' . $title . ($assignee !== '' ? " — مسئول: {$assignee}" : '') . ' ' . $this->renderSources(['action_item:' . (int) ($item['id'] ?? 0)]);
            }
        }

        $followups = (array) ($analysis['followups'] ?? []);
        if ($followups !== []) {
            $lines[] = '';
            $lines[] = 'پیشنهادهای نیازمند پیگیری:';
            foreach ($followups as $item) $lines[] = '• ' . $item['title'] . ': ' . $item['reason'] . ' ' . $this->renderSources((array) $item['sources']);
        }

        $limits = (array) ($analysis['data_limits'] ?? []);
        if ($limits !== []) {
            $lines[] = '';
            $lines[] = 'محدودیت داده:';
            foreach ($limits as $limit) $lines[] = '• ' . $limit;
        }

        return trim(implode("\n", $lines));
    }

    /** @param array<int,string> $sources */
    protected function renderSources(array $sources): string
    {
        $labels = [];
        foreach ($sources as $source) {
            if (! preg_match('/^(message|post|poll|action_item):(\d+)$/', (string) $source, $match)) continue;
            $label = match ($match[1]) { 'message' => 'پیام', 'post' => 'پست', 'poll' => 'نظرسنجی', 'action_item' => 'اقدام', default => 'منبع' };
            $labels[] = $label . ' #' . $match[2];
        }
        return $labels === [] ? '' : '(' . implode('، ', $labels) . ')';
    }

    protected function containsReasoningLeakage(string $response): bool
    {
        $plain = mb_strtolower($response);
        foreach (["here's a thinking process",'thinking process','chain-of-thought','chain of thought','analyze user request','analyse user request','examine source_json','process content per rules',"let's draft",'final check','check against rules','reasoning:','internal reasoning','scratchpad'] as $marker) {
            if (str_contains($plain, $marker)) return true;
        }
        return false;
    }

    protected function looksUnavailable(string $response): bool
    {
        $plain = mb_strtolower($response);
        return str_contains($plain, 'در حال حاضر قادر به پاسخگویی نیستم') || str_contains($plain, 'متأسفم، در حال حاضر') || str_contains($plain, 'متاسفم، در حال حاضر');
    }
}
