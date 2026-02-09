<?php

namespace App\Services\NajmHoda\CodeScanner;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * سرویس رفع خودکار مشکلات
 */
class AutoFixerService
{
    protected BackupManagerService $backupManager;
    protected CodeAnalyzerService $analyzer;

    public function __construct()
    {
        $this->backupManager = app(BackupManagerService::class);
        $this->analyzer = app(CodeAnalyzerService::class);
    }

    /**
     * سطوح خودکارسازی
     */
    const LEVEL_OFF = 'off';           // غیرفعال
    const LEVEL_SAFE = 'safe';         // فقط تغییرات امن (Formatting)
    const LEVEL_MODERATE = 'moderate'; // تغییرات متوسط + تایید
    const LEVEL_AGGRESSIVE = 'aggressive'; // همه چیز + تایید

    /**
     * رفع خودکار یک issue
     */
    public function autoFix(array $issue, string $filePath): array
    {
        $level = config('najm-hoda.auto_fixer.level', self::LEVEL_OFF);

        if ($level === self::LEVEL_OFF) {
            return [
                'success' => false,
                'message' => 'Auto-Fixer غیرفعال است',
            ];
        }

        // چک کردن آیا این issue قابل رفع خودکار است
        if (!$this->isAutoFixable($issue, $level)) {
            return [
                'success' => false,
                'message' => 'این مشکل نیاز به تایید دستی دارد',
                'requires_approval' => true,
            ];
        }

        try {
            // Backup قبل از تغییر
            $backupId = $this->backupManager->createBackup($filePath);

            // دریافت پیشنهاد از AI
            $fileContent = File::get($filePath);
            $suggestion = $this->analyzer->generateCodeSuggestion($issue, $fileContent);

            if (!$suggestion['success']) {
                throw new \Exception('خطا در تولید پیشنهاد');
            }

            // اعمال تغییرات
            $result = $this->applyFix($filePath, $issue, $suggestion);

            if ($result['success']) {
                // لاگ تغییرات
                $this->logChange($filePath, $issue, $suggestion, $backupId);

                return [
                    'success' => true,
                    'message' => 'مشکل به صورت خودکار رفع شد',
                    'backup_id' => $backupId,
                    'changes' => $result['changes'],
                ];
            } else {
                // Rollback در صورت خطا
                $this->backupManager->restore($backupId);
                throw new \Exception($result['error']);
            }

        } catch (\Exception $e) {
            Log::error('خطا در Auto-Fix: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * بررسی قابلیت رفع خودکار
     */
    protected function isAutoFixable(array $issue, string $level): bool
    {
        // تغییرات همیشه امن (در هر سطحی)
        $safeFixes = [
            'Long Line',
            'Commented Code',
            'Debug Code',
        ];

        // تغییرات متوسط
        $moderateFixes = [
            'Inefficient Count',
            'Query in Loop', // با احتیاط
        ];

        // تغییرات پیشرفته (نیاز به تایید)
        $advancedFixes = [
            'N+1 Query',
            'SQL Injection',
            'XSS',
            'Mass Assignment',
        ];

        $issueType = $issue['type'];

        switch ($level) {
            case self::LEVEL_SAFE:
                return in_array($issueType, $safeFixes);

            case self::LEVEL_MODERATE:
                return in_array($issueType, array_merge($safeFixes, $moderateFixes));

            case self::LEVEL_AGGRESSIVE:
                // همه چیز قابل رفع است اما نیاز به تایید دارد
                return !in_array($issueType, $advancedFixes);

            default:
                return false;
        }
    }

    /**
     * اعمال تغییرات
     */
    protected function applyFix(string $filePath, array $issue, array $suggestion): array
    {
        try {
            $content = File::get($filePath);
            $originalCode = $suggestion['original_code'];
            $suggestedCode = $suggestion['suggested_code'];

            // جایگزینی کد
            $newContent = str_replace($originalCode, $suggestedCode, $content);

            if ($newContent === $content) {
                return [
                    'success' => false,
                    'error' => 'کد اصلی یافت نشد - ممکن است قبلاً تغییر کرده باشد',
                ];
            }

            // ذخیره فایل
            File::put($filePath, $newContent);

            return [
                'success' => true,
                'changes' => [
                    'file' => $filePath,
                    'lines_changed' => substr_count($originalCode, "\n") + 1,
                    'original' => $originalCode,
                    'new' => $suggestedCode,
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * لاگ تغییرات
     */
    protected function logChange(string $filePath, array $issue, array $suggestion, string $backupId): void
    {
        $logEntry = [
            'timestamp' => now()->toIso8601String(),
            'file' => str_replace(base_path(), '', $filePath),
            'issue_type' => $issue['type'],
            'severity' => $issue['severity'],
            'backup_id' => $backupId,
            'changes' => [
                'original' => $suggestion['original_code'],
                'new' => $suggestion['suggested_code'],
            ],
        ];

        $logFile = storage_path('najm-hoda/auto-fixer-log.json');
        
        if (!File::exists(dirname($logFile))) {
            File::makeDirectory(dirname($logFile), 0755, true);
        }

        $logs = [];
        if (File::exists($logFile)) {
            $logs = json_decode(File::get($logFile), true) ?? [];
        }

        $logs[] = $logEntry;

        // نگه‌داشتن فقط 1000 لاگ آخر
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }

        File::put($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * دریافت لاگ‌ها
     */
    public function getLogs(int $limit = 50): array
    {
        $logFile = storage_path('najm-hoda/auto-fixer-log.json');

        if (!File::exists($logFile)) {
            return [];
        }

        $logs = json_decode(File::get($logFile), true) ?? [];

        return array_slice(array_reverse($logs), 0, $limit);
    }

    /**
     * رفع دسته‌ای مشکلات
     */
    public function batchFix(array $issues, array $filePaths): array
    {
        $results = [
            'total' => count($issues),
            'fixed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($issues as $index => $issue) {
            $filePath = $filePaths[$index] ?? null;

            if (!$filePath || !File::exists($filePath)) {
                $results['skipped']++;
                continue;
            }

            $result = $this->autoFix($issue, $filePath);

            $results['details'][] = [
                'issue' => $issue,
                'file' => $filePath,
                'result' => $result,
            ];

            if ($result['success']) {
                $results['fixed']++;
            } elseif (isset($result['requires_approval'])) {
                $results['skipped']++;
            } else {
                $results['failed']++;
            }

            // محدودیت Rate Limiting
            if ($results['fixed'] >= config('najm-hoda.auto_fixer.max_fixes_per_run', 10)) {
                $results['message'] = 'محدودیت تعداد رفع خودکار در هر اجرا';
                break;
            }
        }

        return $results;
    }

    /**
     * بررسی وضعیت Auto-Fixer
     */
    public function getStatus(): array
    {
        return [
            'enabled' => config('najm-hoda.auto_fixer.enabled', false),
            'level' => config('najm-hoda.auto_fixer.level', self::LEVEL_OFF),
            'max_fixes_per_run' => config('najm-hoda.auto_fixer.max_fixes_per_run', 10),
            'require_approval' => config('najm-hoda.auto_fixer.require_approval', true),
            'backup_retention_days' => config('najm-hoda.auto_fixer.backup_retention_days', 30),
            'total_fixes' => count($this->getLogs(9999)),
        ];
    }
}
