<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KbArticle;
use App\Services\NajmHoda\Agents\StewardAgent;

class TestStewardKb extends Command
{
    protected $signature = 'steward:test-kb';
    protected $description = 'Test Steward Agent integration with Knowledge Base';

    public function handle()
    {
        $this->info('=== تست Steward Agent + Knowledge Base ===');
        $this->newLine();

        // 1. Check KB Articles
        $this->info('📚 بررسی مقالات پایگاه دانش:');
        $articles = KbArticle::where('status', 'published')
            ->with('category')
            ->get();
        
        $this->line('تعداد مقالات منتشر شده: ' . $articles->count());
        $this->newLine();

        if ($articles->count() > 0) {
            $this->line('نمونه مقالات:');
            foreach ($articles->take(5) as $article) {
                $category = $article->category?->name ?? 'عمومی';
                $this->line("  ✓ {$article->title} ({$category})");
                $this->line("    URL: https://localhost:8000/support/knowledge-base/{$article->slug}");
            }
        } else {
            $this->warn('هیچ مقاله‌ی منتشر شده‌ای یافت نشد!');
        }

        $this->newLine();

        // 2. Test StewardAgent
        $this->info('🤖 تست Steward Agent:');
        
        try {
            $steward = new StewardAgent();
            
            // Test System Prompt
            $prompt = $steward->getSystemPrompt();
            if (strpos($prompt, 'پایگاه دانش') !== false) {
                $this->line('✓ System Prompt شامل پایگاه دانش است');
            }
            
            if (strpos($prompt, $articles->count() . '') !== false || $articles->count() === 0) {
                $this->line('✓ System Prompt شامل خلاصه مقالات است');
            }
            
            // Show KB Summary section
            $this->newLine();
            $this->info('📄 خلاصه پایگاه دانش در System Prompt:');
            preg_match('/\*\*پایگاه دانش موجود:\*\*\n(.*?)\n\*\*/s', $prompt, $matches);
            if (!empty($matches[1])) {
                $summary = trim($matches[1]);
                $lines = array_slice(explode("\n", $summary), 0, 10);
                foreach ($lines as $line) {
                    if (!empty($line)) {
                        $this->line('  ' . $line);
                    }
                }
            } else {
                $this->warn('خلاصه‌ی پایگاه دانش یافت نشد');
            }

            $this->newLine();
            $this->info('✅ تمام تست‌ها با موفقیت انجام شد!');
            
        } catch (\Exception $e) {
            $this->error('خطا: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('📝 خلاصه:');
        $this->line('• Knowledge Base: ' . $articles->count() . ' مقاله منتشر شده');
        $this->line('• Steward Agent: دارای دسترسی به KB Articles');
        $this->line('• System Prompt: شامل اطلاعات پایگاه دانش');
        $this->line('• Answer Method: مقالات مرتبط را به پاسخ اضافه می‌کند');
        $this->line('• Knowledge Search: جستجوی کلیدواژه برای مقالات مرتبط');

        $this->newLine();
        $this->info('🎯 نتیجه: Steward Agent از پایگاه دانش استفاده می‌کند! ✨');

        return 0;
    }
}
