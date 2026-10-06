<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final class CommunicationDeliveryBoundaryTest extends TestCase
{
    /**
     * Direct provider access belongs only to the explicit outbound adapter.
     * Production callers must create canonical communications instead.
     */
    private const ALLOWED_DIRECT_MAIL_FILES = [
        'app/Services/Communication/EmailDeliveryAdapter.php',
    ];

    public function test_production_code_cannot_bypass_the_communication_center_with_direct_mail_calls(): void
    {
        $root = dirname(__DIR__, 2);
        $target = $root.'/app';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        $phpFiles = new RegexIterator($iterator, '/\.php$/i');

        foreach ($phpFiles as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');

            if (in_array($relative, self::ALLOWED_DIRECT_MAIL_FILES, true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (! preg_match('/(?:\\\\Illuminate\\\\Support\\\\Facades\\\\Mail|(?<![A-Za-z0-9_])Mail)::(?:send|to|raw|html|mailer|queue|later)\s*\(/', $contents)) {
                continue;
            }

            $violations[] = $relative;
        }

        $violations = array_values(array_unique($violations));
        sort($violations);

        $this->assertSame([], $violations,
            "Direct production mail delivery detected outside Communication Center adapter:\n"
            .implode("\n", $violations)
        );
    }
    public function test_production_notifications_cannot_use_mail_channel_or_mail_route(): void
    {
        $root = dirname(__DIR__, 2);
        $target = $root.'/app';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        $phpFiles = new RegexIterator($iterator, '/\.php$/i');

        foreach ($phpFiles as $file) {
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');

            $usesMailRoute = preg_match(
                '/(?:Notification|NotificationFacade|\\\\Illuminate\\\\Support\\\\Facades\\\\Notification)::route\s*\(\s*[\'\"]mail[\'\"]/',
                $contents
            ) === 1;

            $notificationMailChannel = str_starts_with($relative, 'app/Notifications/')
                && (
                    preg_match('/return\s*\[[^\]]*[\'\"]mail[\'\"][^\]]*\]/s', $contents) === 1
                    || preg_match('/\$channels\[\]\s*=\s*[\'\"]mail[\'\"]/', $contents) === 1
                );

            if ($usesMailRoute || $notificationMailChannel) {
                $violations[] = $relative;
            }
        }

        $violations = array_values(array_unique($violations));
        sort($violations);

        $this->assertSame([], $violations,
            "Laravel Notification mail delivery detected outside Communication Center:\n"
            .implode("\n", $violations)
        );
    }

}
