<?php

namespace App\Services\NajmHoda\Knowledge;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StewardKnowledgeUrlIngestor
{
    private const MAX_BODY_BYTES = 2_000_000;
    private const MAX_TEXT_CHARS = 500_000;

    public function ingest(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('آدرس منبع معتبر نیست.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('فقط لینک‌های HTTP و HTTPS مجاز هستند.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('لینک منبع نباید شامل نام کاربری یا رمز عبور باشد.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            throw new RuntimeException('آدرس‌های محلی به‌عنوان منبع دانش مجاز نیستند.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, [80, 443], true)) {
            throw new RuntimeException('فقط پورت‌های استاندارد وب 80 و 443 برای منابع دانش مجاز هستند.');
        }

        $resolvedIp = $this->resolvePublicHost($host);
        $resolvedTarget = str_contains($resolvedIp, ':') ? "[{$resolvedIp}]" : $resolvedIp;

        $response = Http::connectTimeout(5)
            ->timeout(12)
            ->withHeaders([
                'User-Agent' => 'EarthCoop-Knowledge-Importer/1.0',
                'Accept' => 'text/html,text/plain,application/xhtml+xml;q=0.9,*/*;q=0.1',
            ])
            ->withOptions([
                'allow_redirects' => false,
                'progress' => function (
                    int $downloadTotal,
                    int $downloadedBytes,
                    int $uploadTotal,
                    int $uploadedBytes
                ): void {
                    if ($downloadTotal > self::MAX_BODY_BYTES || $downloadedBytes > self::MAX_BODY_BYTES) {
                        throw new RuntimeException('حجم محتوای لینک بیشتر از حد مجاز 2MB است.');
                    }
                },
                'curl' => [
                    CURLOPT_RESOLVE => ["{$host}:{$port}:{$resolvedTarget}"],
                ],
            ])
            ->get($url);

        if ($response->status() >= 300 && $response->status() < 400) {
            throw new RuntimeException('لینک منبع تغییر مسیر می‌دهد؛ لطفاً آدرس نهایی HTTPS را ثبت کنید.');
        }

        if (!$response->successful()) {
            throw new RuntimeException('دریافت منبع با HTTP ' . $response->status() . ' ناموفق بود.');
        }

        $contentLength = (int) ($response->header('Content-Length') ?? 0);
        if ($contentLength > self::MAX_BODY_BYTES) {
            throw new RuntimeException('حجم محتوای لینک بیشتر از حد مجاز 2MB است.');
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (
            !str_contains($contentType, 'text/html')
            && !str_contains($contentType, 'text/plain')
            && !str_contains($contentType, 'application/xhtml+xml')
        ) {
            throw new RuntimeException('نوع محتوای این لینک برای استخراج متن پشتیبانی نمی‌شود.');
        }

        $body = (string) $response->body();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new RuntimeException('حجم محتوای لینک بیشتر از حد مجاز 2MB است.');
        }

        $text = str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml')
            ? $this->extractHtmlText($body)
            : $body;

        $text = trim(preg_replace('/[\x{00A0}\s]+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        if ($text === '') {
            throw new RuntimeException('متن قابل استفاده‌ای از این لینک استخراج نشد.');
        }

        return [
            'url' => $url,
            'title' => $this->extractTitle($body, $url),
            'content' => mb_substr($text, 0, self::MAX_TEXT_CHARS),
            'content_type' => $contentType,
        ];
    }

    private function resolvePublicHost(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);
            return $host;
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            throw new RuntimeException('دامنه منبع قابل resolve نیست.');
        }

        $ips = [];
        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === []) {
            throw new RuntimeException('برای دامنه منبع IP معتبری پیدا نشد.');
        }

        $publicIps = [];
        foreach (array_unique($ips) as $ip) {
            $this->assertPublicIp($ip);
            $publicIps[] = $ip;
        }

        usort($publicIps, fn (string $a, string $b) => (str_contains($a, ':') <=> str_contains($b, ':')));

        return $publicIps[0];
    }

    private function assertPublicIp(string $ip): void
    {
        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($valid === false) {
            throw new RuntimeException('لینک به شبکه خصوصی یا رزروشده اشاره می‌کند و مجاز نیست.');
        }
    }

    private function extractHtmlText(string $html): string
    {
        if (!class_exists(\DOMDocument::class)) {
            return strip_tags($html);
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

            foreach (['script', 'style', 'noscript', 'svg'] as $tag) {
                $nodes = $dom->getElementsByTagName($tag);
                for ($i = $nodes->length - 1; $i >= 0; $i--) {
                    $node = $nodes->item($i);
                    $node?->parentNode?->removeChild($node);
                }
            }

            return (string) ($dom->textContent ?? '');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function extractTitle(string $body, string $url): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches) === 1) {
            $title = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($title !== '') {
                return mb_substr($title, 0, 255);
            }
        }

        return mb_substr($url, 0, 255);
    }
}
