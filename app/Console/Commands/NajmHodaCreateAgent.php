<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NajmHoda\Agents\ArchitectAgent;
use Illuminate\Support\Facades\File;

/**
 * دستور CLI برای ساخت عامل جدید توسط Architect Agent
 */
class NajmHodaCreateAgent extends Command
{
    protected $signature = 'najm-hoda:create-agent {description}';
    
    protected $description = 'ساخت عامل جدید توسط معمار نجم‌هدا';

    public function handle()
    {
        $description = $this->argument('description');
        
        $this->info("🏗️ معمار نجم‌هدا در حال تحلیل درخواست شما...\n");
        
        $architect = app(ArchitectAgent::class);
        
        // مرحله 1: تشخیص نیاز
        $this->info("📋 مرحله 1: تشخیص نیاز به عامل جدید\n");
        $needAnalysis = $architect->detectNeedForNewAgent($description);
        
        if (!empty($needAnalysis['raw_response'])) {
            $this->warn("⚠️ پاسخ به فرمت متنی:\n");
            $this->line($needAnalysis['raw_response']);
            
            if (!$this->confirm("\nآیا می‌خواهید ادامه دهیم؟", true)) {
                return 0;
            }
        } else {
            $this->table(
                ['کلید', 'مقدار'],
                [
                    ['نیاز به عامل جدید', $needAnalysis['needs_new_agent'] ?? 'نامشخص'],
                    ['دلیل', $needAnalysis['reason'] ?? 'نامشخص'],
                    ['عامل پیشنهادی', $needAnalysis['suggested_agent']['name'] ?? 'نامشخص'],
                ]
            );
            
            if (isset($needAnalysis['needs_new_agent']) && !$needAnalysis['needs_new_agent']) {
                $this->warn("\n⚠️ معمار تشخیص داد که نیازی به عامل جدید نیست!");
                $this->info("💡 " . ($needAnalysis['can_existing_handle'] ?? ''));
                return 0;
            }
        }
        
        // مرحله 2: طراحی عامل
        $this->info("\n🎨 مرحله 2: طراحی معماری عامل جدید\n");
        $design = $architect->designNewAgent($description);
        
        if (!empty($design['raw_response'])) {
            $this->warn("⚠️ پاسخ طراحی:\n");
            $this->line($design['raw_response']);
            
            if (!$this->confirm("\nآیا می‌خواهید ادامه دهیم؟", true)) {
                return 0;
            }
        } else {
            $this->info("✅ طراحی کامل شد:");
            $this->info("📦 نام کلاس: " . ($design['agent_info']['class_name'] ?? 'نامشخص'));
            $this->info("🎭 نقش: " . ($design['agent_info']['role'] ?? 'نامشخص'));
            $this->info("🇮🇷 نام فارسی: " . ($design['agent_info']['persian_name'] ?? 'نامشخص'));
            
            if (isset($design['expertise'])) {
                $this->info("\n💼 تخصص‌ها:");
                foreach ($design['expertise'] as $exp) {
                    $this->line("   - {$exp}");
                }
            }
            
            if (isset($design['methods'])) {
                $this->info("\n🛠️ متدهای کلیدی:");
                foreach ($design['methods'] as $method) {
                    if (is_array($method)) {
                        $this->line("   - " . ($method['name'] ?? 'نامشخص') . ": " . ($method['description'] ?? ''));
                    } else {
                        $this->line("   - {$method}");
                    }
                }
            }
        }
        
        // تایید ادامه
        if (!$this->confirm("\nآیا می‌خواهید این عامل را بسازیم؟", true)) {
            $this->warn("لغو شد!");
            return 0;
        }
        
        // مرحله 3: تولید کد
        $this->info("\n💻 مرحله 3: تولید کد عامل\n");
        
        try {
            $code = $architect->generateAgentCode($design);
            
            // نمایش پیش‌نمایش کد
            if ($this->option('verbose')) {
                $this->info("پیش‌نمایش کد:\n");
                $this->line($code);
            }
            
            // ذخیره فایل
            $className = $design['agent_info']['class_name'] ?? 'UnknownAgent';
            $saved = $architect->saveNewAgent($code, $className);
            
            if ($saved) {
                $this->info("✅ عامل {$className} با موفقیت ساخته شد!\n");
                
                // نمایش راهنمای یکپارچه‌سازی
                $role = $design['agent_info']['role'] ?? 'unknown';
                $guide = $architect->generateIntegrationGuide($className, $role);
                
                $this->warn("📚 راهنمای یکپارچه‌سازی:\n");
                $this->line($guide);
                
                $this->info("\n🎉 موفقیت! عامل جدید آماده استفاده است.");
                $this->info("📁 مسیر فایل: app/Services/NajmHoda/Agents/{$className}.php");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ خطا در ساخت عامل:");
            $this->error($e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
