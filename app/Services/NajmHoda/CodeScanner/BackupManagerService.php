<?php

namespace App\Services\NajmHoda\CodeScanner;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * سرویس مدیریت Backup و Rollback
 */
class BackupManagerService
{
    protected string $backupPath;

    public function __construct()
    {
        $this->backupPath = storage_path('najm-hoda/backups');
        
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    /**
     * ساخت Backup از یک فایل
     */
    public function createBackup(string $filePath): string
    {
        if (!File::exists($filePath)) {
            throw new \Exception('فایل یافت نشد: ' . $filePath);
        }

        $backupId = Str::uuid()->toString();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $relativePath = str_replace(base_path(), '', $filePath);
        $safeFileName = str_replace(['/', '\\'], '_', trim($relativePath, '/\\'));
        
        $backupFileName = "{$backupId}_{$timestamp}_{$safeFileName}";
        $backupFullPath = $this->backupPath . '/' . $backupFileName;

        // کپی فایل
        File::copy($filePath, $backupFullPath);

        // ذخیره metadata
        $metadata = [
            'backup_id' => $backupId,
            'original_file' => $filePath,
            'relative_path' => $relativePath,
            'backup_file' => $backupFullPath,
            'created_at' => now()->toIso8601String(),
            'file_size' => File::size($filePath),
            'file_hash' => md5_file($filePath),
        ];

        File::put(
            $backupFullPath . '.meta.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $backupId;
    }

    /**
     * بازیابی از Backup
     */
    public function restore(string $backupId): bool
    {
        $backup = $this->getBackup($backupId);

        if (!$backup) {
            throw new \Exception('Backup یافت نشد: ' . $backupId);
        }

        if (!File::exists($backup['backup_file'])) {
            throw new \Exception('فایل Backup وجود ندارد');
        }

        // بازیابی فایل
        File::copy($backup['backup_file'], $backup['original_file']);

        // لاگ بازیابی
        $this->logRestore($backupId);

        return true;
    }

    /**
     * دریافت اطلاعات یک Backup
     */
    public function getBackup(string $backupId): ?array
    {
        $files = File::glob($this->backupPath . '/' . $backupId . '_*.meta.json');

        if (empty($files)) {
            return null;
        }

        $metaFile = $files[0];
        $metadata = json_decode(File::get($metaFile), true);

        return $metadata;
    }

    /**
     * دریافت لیست تمام Backup‌ها
     */
    public function listBackups(int $limit = 100): array
    {
        $metaFiles = File::glob($this->backupPath . '/*.meta.json');
        $backups = [];

        foreach ($metaFiles as $metaFile) {
            $metadata = json_decode(File::get($metaFile), true);
            $backups[] = $metadata;
        }

        // مرتب‌سازی بر اساس تاریخ
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($backups, 0, $limit);
    }

    /**
     * حذف Backup‌های قدیمی
     */
    public function cleanOldBackups(int $days = 30): int
    {
        $threshold = now()->subDays($days);
        $deleted = 0;

        $backups = $this->listBackups(9999);

        foreach ($backups as $backup) {
            $createdAt = \Carbon\Carbon::parse($backup['created_at']);

            if ($createdAt->lt($threshold)) {
                $this->deleteBackup($backup['backup_id']);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * حذف یک Backup
     */
    public function deleteBackup(string $backupId): bool
    {
        $backup = $this->getBackup($backupId);

        if (!$backup) {
            return false;
        }

        // حذف فایل backup
        if (File::exists($backup['backup_file'])) {
            File::delete($backup['backup_file']);
        }

        // حذف metadata
        $metaFile = $backup['backup_file'] . '.meta.json';
        if (File::exists($metaFile)) {
            File::delete($metaFile);
        }

        return true;
    }

    /**
     * لاگ بازیابی
     */
    protected function logRestore(string $backupId): void
    {
        $logFile = storage_path('najm-hoda/restore-log.json');
        
        if (!File::exists(dirname($logFile))) {
            File::makeDirectory(dirname($logFile), 0755, true);
        }

        $logs = [];
        if (File::exists($logFile)) {
            $logs = json_decode(File::get($logFile), true) ?? [];
        }

        $logs[] = [
            'backup_id' => $backupId,
            'restored_at' => now()->toIso8601String(),
        ];

        // نگه‌داشتن فقط 500 لاگ آخر
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }

        File::put($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    }

    /**
     * دریافت آمار Backup
     */
    public function getStatistics(): array
    {
        $backups = $this->listBackups(9999);

        $totalSize = 0;
        foreach ($backups as $backup) {
            if (File::exists($backup['backup_file'])) {
                $totalSize += File::size($backup['backup_file']);
            }
        }

        return [
            'total_backups' => count($backups),
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'oldest_backup' => $backups[count($backups) - 1]['created_at'] ?? null,
            'newest_backup' => $backups[0]['created_at'] ?? null,
        ];
    }

    /**
     * مقایسه فایل فعلی با Backup
     */
    public function compareWithBackup(string $backupId): array
    {
        $backup = $this->getBackup($backupId);

        if (!$backup) {
            throw new \Exception('Backup یافت نشد');
        }

        $currentContent = File::exists($backup['original_file']) 
            ? File::get($backup['original_file']) 
            : '';
        
        $backupContent = File::get($backup['backup_file']);

        return [
            'has_changes' => $currentContent !== $backupContent,
            'current_hash' => md5($currentContent),
            'backup_hash' => $backup['file_hash'],
            'current_size' => strlen($currentContent),
            'backup_size' => $backup['file_size'],
        ];
    }
}
