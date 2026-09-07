<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Services\NajmHoda\Knowledge\NajmHodaSecretariatKnowledgeBridge;

class NajmHodaSecretariatGroundedResponder
{
    public function __construct(private readonly NajmHodaSecretariatKnowledgeBridge $bridge)
    {
    }

    /**
     * Answer explicit questions about official Secretariat records from the
     * actor's permission-safe retrieval boundary. This responder is read-only
     * and deterministic: it never invents a document, conclusion or authority.
     *
     * @param array<string,mixed> $pageContext server-validated page context only
     * @return array<string,mixed>|null
     */
    public function respond(User $actor, string $message, array $pageContext = []): ?array
    {
        $query = trim($message);
        if ($query === '' || ! $this->asksSecretariatKnowledge($query)) {
            return null;
        }

        $result = $this->bridge->retrieve($actor, $query, [
            'limit' => 6,
        ]);

        $packets = array_values(array_filter((array) ($result['packets'] ?? []), 'is_array'));
        if ($packets === []) {
            return $this->response(
                'در اسناد رسمی دبیرخانه‌ای که شما مجاز به مشاهده‌شان هستید، سند مرتبطی با این پرسش پیدا نکردم. '
                . 'اگر موضوع یا شماره ثبت دقیق‌تری دارید، می‌توانم جست‌وجو را محدودتر کنم.',
                0
            );
        }

        $lines = [
            'براساس اسناد رسمی دبیرخانه‌ای که مجاز به مشاهده‌شان هستید، این موارد مرتبط پیدا شد:',
            '',
        ];

        foreach (array_slice($packets, 0, 6) as $packet) {
            $title = trim((string) ($packet['title'] ?? '')) ?: 'سند بدون عنوان';
            $registry = trim((string) ($packet['registry_number'] ?? ''));
            $type = trim((string) ($packet['record_type'] ?? ''));
            $excerpt = $this->cleanExcerpt((string) ($packet['excerpt'] ?? ''));

            $meta = [];
            if ($registry !== '') {
                $meta[] = "شماره ثبت: {$registry}";
            }
            if ($type !== '') {
                $meta[] = "نوع: {$type}";
            }

            $lines[] = '• ' . $title . ($meta !== [] ? ' — ' . implode(' | ', $meta) : '');
            if ($excerpt !== '') {
                $lines[] = '  ' . $excerpt;
            }
        }

        $lines[] = '';
        $lines[] = 'این پاسخ فقط از رکوردهای مجاز دبیرخانه ساخته شده است؛ نبودن یک سند در این فهرست به معنی نبودن آن در کل سامانه نیست.';

        return $this->response(implode("\n", $lines), count($packets));
    }

    private function asksSecretariatKnowledge(string $message): bool
    {
        $plain = mb_strtolower($message);
        $anchors = [
            'دبیرخانه',
            'اسناد رسمی',
            'سند رسمی',
            'نامه رسمی',
            'نامه‌های رسمی',
            'مصوبه رسمی',
            'مصوبات رسمی',
            'صورتجلسه رسمی',
            'صورت‌جلسه رسمی',
            'شماره ثبت',
            'registry',
            'secretariat',
        ];

        foreach ($anchors as $anchor) {
            if (mb_stripos($plain, mb_strtolower($anchor)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function cleanExcerpt(string $excerpt): string
    {
        $excerpt = trim((string) preg_replace('/\s+/u', ' ', $excerpt));
        return mb_substr($excerpt, 0, 700);
    }

    /** @return array<string,mixed> */
    private function response(string $message, int $sourceCount): array
    {
        return [
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_knowledge',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'knowledge_source' => 'secretariat',
            'source_count' => $sourceCount,
        ];
    }
}
