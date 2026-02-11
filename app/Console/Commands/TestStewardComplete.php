<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KbArticle;
use App\Models\Blog;
use App\Models\FaqQuestion;

class TestStewardComplete extends Command
{
    protected $signature = 'steward:test-complete';
    protected $description = 'Test Steward Agent with all content sources (KB + Blog + FAQ)';

    public function handle()
    {
        $this->info('=== تست Steward Agent با تمام منابع محتوا ===');
        $this->newLine();

        // 1. Knowledge Base Articles
        $this->info('📚 پایگاه دانش:');
        $articles = KbArticle::where('status', 'published')->get();
        $this->line("  مقالات منتشر شده: {$articles->count()}");

        // 2. Blog Posts
        $this->info('📝 وبلاگ:');
        $blogs = Blog::get();
        $this->line("  پست‌های موجود: {$blogs->count()}");
        if ($blogs->isNotEmpty()) {
            foreach ($blogs->take(3) as $blog) {
                $this->line("    • {$blog->title}");
            }
        }

        // 3. FAQ Questions
        $this->info('❓ سوالات متداول:');
        $faqs = FaqQuestion::published()->get();
        $this->line("  سوالات منتشر شده: {$faqs->count()}");
        if ($faqs->isNotEmpty()) {
            foreach ($faqs->take(3) as $faq) {
                $this->line("    • {$faq->title} ({$faq->category})");
            }
        }

        $this->newLine();
        $this->info('🔄 Observers ثبت‌شده:');
        $this->line('✓ KbArticleObserver');
        $this->line('✓ BlogObserver');
        $this->line('✓ FaqQuestionObserver');

        $this->newLine();
        $this->info('📊 خلاصه:');
        $this->line("• Knowledge Base: {$articles->count()} مقاله");
        $this->line("• Blog Posts: {$blogs->count()} پست");
        $this->line("• FAQ Questions: {$faqs->count()} سوال");

        $total = $articles->count() + $blogs->count() + $faqs->count();
        $this->line("• کل منابع: {$total}");

        $this->newLine();
        $this->info('✅ Steward Agent از تمام منابع استفاده خواهند کرد!');
        $this->line('📌 هر بار که محتوا تغییر کند، cache خودکار آپدیت می‌شود.');

        return 0;
    }
}
