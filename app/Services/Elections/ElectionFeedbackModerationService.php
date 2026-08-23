<?php

namespace App\Services\Elections;

/**
 * First-line moderation for E0 §7.2.
 *
 * This service is intentionally conservative: clearly clean text may be
 * auto-approved, while any suspicious signal is routed to human review.
 * Automatic rejection is avoided so contextual Persian/Arabic/English text
 * is not silently censored by brittle heuristics.
 */
class ElectionFeedbackModerationService
{
    public function screen(string $body): array
    {
        $text = trim($body);
        $reasons = [];

        if ($this->containsPii($text)) {
            $reasons[] = 'possible_personal_information';
        }
        if ($this->containsThreat($text)) {
            $reasons[] = 'possible_threat';
        }
        if ($this->containsAbuse($text)) {
            $reasons[] = 'possible_abuse_or_hate';
        }
        if ($this->looksLikeSpam($text)) {
            $reasons[] = 'possible_spam';
        }

        return [
            'status' => $reasons === [] ? 'approved' : 'pending_review',
            'reasons' => $reasons,
            'source' => 'e0_rule_screen_v1',
        ];
    }

    private function containsPii(string $text): bool
    {
        return preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', $text) === 1
            || preg_match('/(?<!\d)(?:\+?\d[\d\s().-]{7,}\d)(?!\d)/u', $text) === 1;
    }

    private function containsThreat(string $text): bool
    {
        $patterns = [
            'می.?کشمت', 'می.?زنمت', 'نابودت می.?کنم', 'تهدیدت می.?کنم',
            'kill you', 'hurt you', 'destroy you', 'threaten you',
        ];

        return $this->containsAny($text, $patterns);
    }

    private function containsAbuse(string $text): bool
    {
        // Deliberately narrow. The purpose is review routing, not final judgment.
        $patterns = [
            'احمق', 'بی.?شعور', 'حرامزاده', 'کثافت', 'نفرت از',
            'idiot', 'moron', 'bastard', 'hate all',
        ];

        return $this->containsAny($text, $patterns);
    }

    private function looksLikeSpam(string $text): bool
    {
        if (preg_match('/https?:\/\/|www\./iu', $text) === 1) {
            return true;
        }

        if (preg_match('/(.)\1{9,}/u', $text) === 1) {
            return true;
        }

        $words = preg_split('/\s+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 8) {
            return false;
        }

        $counts = array_count_values($words);
        return max($counts) >= 6;
    }

    private function containsAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('/'.$pattern.'/iu', $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
