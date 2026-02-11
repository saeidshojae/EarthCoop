<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KbArticle;
use App\Models\KbCategory;

class ShowKnowledgeBase extends Command
{
    protected $signature = 'kb:show {--category= : فیلتر بر اساس دسته}';
    protected $description = 'نمایش جزئیات پایگاه دانش';

    public function handle()
    {
        $this->info('=== پایگاه دانش EarthCoop ===');
        $this->newLine();

        $query = KbArticle::where('status', 'published')
            ->with('category', 'tags');

        if ($this->option('category')) {
            $query->whereHas('category', function($q) {
                $q->where('name', 'like', '%' . $this->option('category') . '%');
            });
        }

        $articles = $query->orderBy('published_at', 'desc')->get();

        // Group by category
        $grouped = $articles->groupBy(function($article) {
            return $article->category?->name ?? 'سایر';
        });

        foreach ($grouped as $category => $items) {
            $this->line('');
            $this->info("📁 {$category} ({$items->count()})");
            $this->line(str_repeat('─', 50));
            
            foreach ($items as $article) {
                $this->line("▸ {$article->title}");
                if ($article->excerpt) {
                    $excerpt = substr($article->excerpt, 0, 60) . '...';
                    $this->line("  📝 {$excerpt}");
                }
                $this->line("  🔗 /support/knowledge-base/{$article->slug}");
                if ($article->tags->count() > 0) {
                    $tags = $article->tags->pluck('name')->join(', ');
                    $this->line("  🏷️  {$tags}");
                }
                $this->line("  👁️  {$article->view_count} بازدید");
                $this->newLine();
            }
        }

        $this->info('📊 آمار:');
        $this->line("• کل مقالات: {$articles->count()}");
        $this->line("• دسته‌ها: {$grouped->count()}");
        
        $totalViews = $articles->sum('view_count');
        $this->line("• کل بازدیدها: {$totalViews}");

        return 0;
    }
}
