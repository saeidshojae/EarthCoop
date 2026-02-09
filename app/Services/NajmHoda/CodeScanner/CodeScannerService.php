<?php

namespace App\Services\NajmHoda\CodeScanner;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * سرویس اسکن و تحلیل کدهای پروژه
 */
class CodeScannerService
{
    /**
     * دایرکتوری‌های قابل اسکن
     */
    protected array $scannableDirectories = [
        'app/Http/Controllers',
        'app/Models',
        'app/Services',
        'routes',
        'database/migrations',
    ];

    /**
     * دایرکتوری‌های ممنوع
     */
    protected array $forbiddenDirectories = [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
    ];

    /**
     * فایل‌های ممنوع
     */
    protected array $forbiddenFiles = [
        '.env',
        '.env.example',
        'composer.lock',
        'package-lock.json',
    ];

    /**
     * اسکن کامل پروژه
     */
    public function scanProject(array $options = []): array
    {
        $results = [
            'scanned_files' => 0,
            'total_lines' => 0,
            'issues_found' => 0,
            'issues' => [],
            'scanned_at' => now(),
        ];

        foreach ($this->scannableDirectories as $directory) {
            $path = base_path($directory);
            
            if (!File::exists($path)) {
                continue;
            }

            $files = $this->getPhpFiles($path);
            
            foreach ($files as $file) {
                $fileResults = $this->scanFile($file);
                
                $results['scanned_files']++;
                $results['total_lines'] += $fileResults['lines'];
                $results['issues_found'] += count($fileResults['issues']);
                
                if (!empty($fileResults['issues'])) {
                    $results['issues'][$file] = $fileResults['issues'];
                }
            }
        }

        return $results;
    }

    /**
     * اسکن یک فایل
     */
    public function scanFile(string $filePath): array
    {
        if (!File::exists($filePath)) {
            return ['lines' => 0, 'issues' => []];
        }

        $content = File::get($filePath);
        $lines = explode("\n", $content);
        
        $issues = [];

        // بررسی N+1 Query
        $issues = array_merge($issues, $this->detectNPlusOneQuery($content, $filePath));

        // بررسی Security Issues
        $issues = array_merge($issues, $this->detectSecurityIssues($content, $filePath));

        // بررسی Performance Issues
        $issues = array_merge($issues, $this->detectPerformanceIssues($content, $filePath));

        // بررسی Code Quality
        $issues = array_merge($issues, $this->detectCodeQualityIssues($content, $filePath));

        return [
            'lines' => count($lines),
            'issues' => $issues,
        ];
    }

    /**
     * تشخیص N+1 Query مشکلات
     */
    protected function detectNPlusOneQuery(string $content, string $filePath): array
    {
        $issues = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            // الگوی مشکوک: Model::all() + foreach + relationship access
            if (preg_match('/::all\(\)/', $line)) {
                $nextLines = array_slice($lines, $lineNumber, 10);
                $suspiciousCode = implode("\n", $nextLines);

                if (preg_match('/foreach.*?\$\w+\s+as.*?\$(\w+)/', $suspiciousCode, $matches)) {
                    $varName = $matches[1];
                    if (preg_match('/\$' . $varName . '->\w+/', $suspiciousCode)) {
                        $issues[] = [
                            'type' => 'N+1 Query',
                            'severity' => 'high',
                            'line' => $lineNumber + 1,
                            'code' => trim($line),
                            'message' => 'احتمال N+1 Query - استفاده از with() یا load() پیشنهاد می‌شود',
                            'file' => $filePath,
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * تشخیص مشکلات امنیتی
     */
    protected function detectSecurityIssues(string $content, string $filePath): array
    {
        $issues = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            // SQL Injection
            if (preg_match('/DB::raw\(.+\$\w+/', $line)) {
                $issues[] = [
                    'type' => 'SQL Injection',
                    'severity' => 'critical',
                    'line' => $lineNumber + 1,
                    'code' => trim($line),
                    'message' => 'استفاده از متغیر در DB::raw() خطرناک است - از Parameter Binding استفاده کنید',
                    'file' => $filePath,
                ];
            }

            // XSS
            if (preg_match('/\{!!\s*\$\w+\s*!!\}/', $line)) {
                $issues[] = [
                    'type' => 'XSS',
                    'severity' => 'high',
                    'line' => $lineNumber + 1,
                    'code' => trim($line),
                    'message' => 'استفاده از {!! !!} بدون escape خطرناک است - از {{ }} استفاده کنید',
                    'file' => $filePath,
                ];
            }

            // Mass Assignment
            if (preg_match('/::create\(\s*\$request->all\(\)/', $line)) {
                $issues[] = [
                    'type' => 'Mass Assignment',
                    'severity' => 'medium',
                    'line' => $lineNumber + 1,
                    'code' => trim($line),
                    'message' => 'استفاده از $request->all() خطرناک است - داده‌ها را validate کنید',
                    'file' => $filePath,
                ];
            }
        }

        return $issues;
    }

    /**
     * تشخیص مشکلات Performance
     */
    protected function detectPerformanceIssues(string $content, string $filePath): array
    {
        $issues = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            // Query در حلقه
            if (preg_match('/foreach.*?{/', $line)) {
                $loopContent = $this->extractLoopContent($lines, $lineNumber);
                if (preg_match('/::(find|where|first|get)\(/', $loopContent)) {
                    $issues[] = [
                        'type' => 'Query in Loop',
                        'severity' => 'high',
                        'line' => $lineNumber + 1,
                        'code' => trim($line),
                        'message' => 'Query در حلقه باعث کندی می‌شود - از Eager Loading استفاده کنید',
                        'file' => $filePath,
                    ];
                }
            }

            // استفاده بی‌دلیل از all()
            if (preg_match('/::all\(\)->count\(\)/', $line)) {
                $issues[] = [
                    'type' => 'Inefficient Count',
                    'severity' => 'medium',
                    'line' => $lineNumber + 1,
                    'code' => trim($line),
                    'message' => 'استفاده از all()->count() ناکارآمد است - از count() مستقیم استفاده کنید',
                    'file' => $filePath,
                ];
            }
        }

        return $issues;
    }

    /**
     * تشخیص مشکلات کیفیت کد
     */
    protected function detectCodeQualityIssues(string $content, string $filePath): array
    {
        $issues = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            // کدهای کامنت شده
            if (preg_match('/^\s*\/\/\s*\$/', $line)) {
                $issues[] = [
                    'type' => 'Commented Code',
                    'severity' => 'low',
                    'line' => $lineNumber + 1,
                    'code' => trim($line),
                    'message' => 'کد کامنت شده - حذف یا فعال کنید',
                    'file' => $filePath,
                ];
            }

            // خطوط خیلی بلند
            if (strlen($line) > 120) {
                $issues[] = [
                    'type' => 'Long Line',
                    'severity' => 'low',
                    'line' => $lineNumber + 1,
                    'code' => substr(trim($line), 0, 50) . '...',
                    'message' => 'خط بیش از 120 کاراکتر - تقسیم کنید',
                    'file' => $filePath,
                ];
            }

            // استفاده از dd() یا dump()
            if (preg_match('/\b(dd|dump)\s*\(/', $line)) {
                $issues[] = [
                    'type' => 'Debug Code',
                    'severity' => 'medium',
                    'line' => $lineNumber + 1,
                    'code' => trim($line),
                    'message' => 'کد debug یافت شد - قبل از production حذف کنید',
                    'file' => $filePath,
                ];
            }
        }

        return $issues;
    }

    /**
     * استخراج محتوای حلقه
     */
    protected function extractLoopContent(array $lines, int $startLine): string
    {
        $braceCount = 0;
        $content = [];
        
        for ($i = $startLine; $i < count($lines); $i++) {
            $line = $lines[$i];
            $content[] = $line;
            
            $braceCount += substr_count($line, '{') - substr_count($line, '}');
            
            if ($braceCount === 0 && strpos($line, '{') !== false) {
                break;
            }
        }
        
        return implode("\n", $content);
    }

    /**
     * دریافت فایل‌های PHP
     */
    protected function getPhpFiles(string $directory): array
    {
        $files = [];
        
        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getPathname();
                
                // چک کردن فایل‌های ممنوع
                if ($this->isAllowedFile($filePath)) {
                    $files[] = $filePath;
                }
            }
        }

        return $files;
    }

    /**
     * چک کردن مجاز بودن فایل
     */
    protected function isAllowedFile(string $filePath): bool
    {
        // چک دایرکتوری‌های ممنوع
        foreach ($this->forbiddenDirectories as $forbidden) {
            if (str_contains($filePath, $forbidden)) {
                return false;
            }
        }

        // چک فایل‌های ممنوع
        foreach ($this->forbiddenFiles as $forbidden) {
            if (str_ends_with($filePath, $forbidden)) {
                return false;
            }
        }

        return true;
    }

    /**
     * دریافت خلاصه نتایج
     */
    public function getSummary(array $results): array
    {
        $summary = [
            'total_issues' => $results['issues_found'],
            'by_severity' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'by_type' => [],
        ];

        foreach ($results['issues'] as $file => $issues) {
            foreach ($issues as $issue) {
                $summary['by_severity'][$issue['severity']]++;
                
                if (!isset($summary['by_type'][$issue['type']])) {
                    $summary['by_type'][$issue['type']] = 0;
                }
                $summary['by_type'][$issue['type']]++;
            }
        }

        return $summary;
    }
}
