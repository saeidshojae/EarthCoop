<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Services\NajmHoda\Agents\GuideAgent;
use Carbon\CarbonInterface;

/**
 * Read-only semantic extractor for possible meeting decisions/resolutions.
 *
 * Candidates must be grounded in an exact source excerpt. This service never
 * makes a resolution official and never mutates group state by itself.
 */
class NajmHodaGroupDecisionCandidateService
{
    public function __construct(
        protected NajmHodaGroupKnowledgeService $knowledge,
        protected GuideAgent $guide
    ) {
    }

    /** @return array{available:bool,candidates:array<int,array<string,mixed>>,snapshot:array<string,mixed>} */
    public function extract(Group $group, CarbonInterface $from, CarbonInterface $to): array
    {
        $snapshot = $this->knowledge->snapshot($group, $from, $to, 120);

        if (! config('najm-hoda.provider.api_key') || (bool) config('najm-hoda.mock_mode', false)) {
            return ['available' => false, 'candidates' => [], 'snapshot' => $snapshot];
        }

        $source = $this->source($snapshot);
        if ($source['messages'] === [] && $source['posts'] === [] && $source['polls'] === []) {
            return ['available' => true, 'candidates' => [], 'snapshot' => $snapshot];
        }

        $schema = <<<'JSON'
{
  "decision_candidates": [
    {
      "title": "عنوان کوتاه تصمیم",
      "decision": "متن دقیق تصمیم/مصوبه",
      "source": "message:12",
      "evidence": "نقل قول دقیق و پیوسته از همان source"
    }
  ]
}
JSON;

        $prompt = "تو لایه استخراج تصمیمات احتمالی نشست رسمی EarthCoop هستی.\n\n"
            . "قواعد سخت:\n"
            . "1) فقط مواردی را برگردان که SOURCE_JSON قرینه مستقیم بر تصمیم، تصویب، توافق، تعیین تکلیف یا نتیجه قطعی جلسه دارد. بحث، پیشنهاد، سؤال، نظر شخصی یا گزینه نظرسنجی به تنهایی تصمیم نیست.\n"
            . "2) همه خروجی‌ها فقط «پیشنهاد تصمیم» برای بررسی مدیر/بازرس هستند و تا تأیید انسانی مصوبه رسمی نیستند.\n"
            . "3) evidence باید نقل قول دقیق و پیوسته از source معرفی‌شده باشد.\n"
            . "4) source فقط message:<id>، post:<id> یا poll:<id> موجود در SOURCE_JSON باشد.\n"
            . "5) متن decision نباید تعهد، مسئول، موعد یا جزئیاتی اضافه کند که در evidence وجود ندارد.\n"
            . "6) خروجی فقط JSON معتبر مطابق schema باشد. حداکثر 8 candidate. اگر شاهد کافی نیست آرایه خالی بده.\n\n"
            . "SCHEMA:\n{$schema}\n\nSOURCE_JSON:\n"
            . json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $raw = trim($this->guide->ask($prompt, []));
        if ($raw === '' || $this->looksUnavailable($raw) || $this->containsReasoningLeakage($raw)) {
            return ['available' => false, 'candidates' => [], 'snapshot' => $snapshot];
        }

        $decoded = $this->decode($raw);
        if ($decoded === null || ! is_array($decoded['decision_candidates'] ?? null)) {
            return ['available' => false, 'candidates' => [], 'snapshot' => $snapshot];
        }

        $sourceMap = $this->sourceMap($source);
        $candidates = [];
        foreach (array_slice($decoded['decision_candidates'], 0, 8) as $item) {
            if (! is_array($item)) continue;

            $title = $this->clean($item['title'] ?? null, 180);
            $decision = $this->clean($item['decision'] ?? null, 900);
            $sourceRef = is_string($item['source'] ?? null) ? trim($item['source']) : '';
            $evidence = $this->clean($item['evidence'] ?? null, 600);
            if ($title === null || $decision === null || $sourceRef === '' || $evidence === null) continue;

            $sourceText = $sourceMap[$sourceRef] ?? null;
            if (! is_string($sourceText) || mb_stripos($sourceText, $evidence) === false) continue;

            $fingerprint = hash('sha256', $sourceRef . '|' . $evidence . '|' . $decision);
            $candidates[] = [
                'title' => $title,
                'decision' => $decision,
                'source' => $sourceRef,
                'evidence' => $evidence,
                'fingerprint' => $fingerprint,
                'state' => 'candidate',
            ];
        }

        return ['available' => true, 'candidates' => $candidates, 'snapshot' => $snapshot];
    }

    /** @param array<string,mixed> $snapshot */
    protected function source(array $snapshot): array
    {
        return [
            'group' => $snapshot['group'] ?? [],
            'window' => $snapshot['window'] ?? [],
            'messages' => array_values(array_filter((array) ($snapshot['messages'] ?? []), fn ($item): bool => is_array($item) && trim((string) ($item['text'] ?? '')) !== '' && ! in_array(trim((string) ($item['text'] ?? '')), ['پیام صوتی', 'voice message'], true))),
            'posts' => array_values((array) ($snapshot['posts'] ?? [])),
            'polls' => array_values((array) ($snapshot['polls'] ?? [])),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,string> */
    protected function sourceMap(array $source): array
    {
        $map = [];
        foreach ((array) ($source['messages'] ?? []) as $item) {
            if (is_array($item) && isset($item['id'])) $map['message:' . (int) $item['id']] = trim((string) ($item['text'] ?? ''));
        }
        foreach ((array) ($source['posts'] ?? []) as $item) {
            if (is_array($item) && isset($item['id'])) $map['post:' . (int) $item['id']] = trim(((string) ($item['title'] ?? '')) . "\n" . ((string) ($item['text'] ?? '')));
        }
        foreach ((array) ($source['polls'] ?? []) as $item) {
            if (is_array($item) && isset($item['id'])) $map['poll:' . (int) $item['id']] = trim((string) ($item['question'] ?? ''));
        }
        return $map;
    }

    /** @return array<string,mixed>|null */
    protected function decode(string $raw): ?array
    {
        $clean = trim($raw);
        if (preg_match('/^```(?:json)?\s*(\{.*\})\s*```$/isu', $clean, $m) === 1) $clean = trim((string) $m[1]);
        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function clean(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) return null;
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    protected function looksUnavailable(string $response): bool
    {
        $plain = mb_strtolower($response);
        return str_contains($plain, 'در حال حاضر قادر به پاسخگویی نیستم')
            || str_contains($plain, 'متأسفم، در حال حاضر')
            || str_contains($plain, 'متاسفم، در حال حاضر');
    }

    protected function containsReasoningLeakage(string $response): bool
    {
        $plain = mb_strtolower($response);
        foreach (["here's a thinking process", 'thinking process', 'chain-of-thought', 'chain of thought', 'analyze user request', 'final check', 'scratchpad'] as $marker) {
            if (str_contains($plain, $marker)) return true;
        }
        return false;
    }
}
