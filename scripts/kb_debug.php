<?php

/**
 * Quick KB diagnostics (local/dev).
 *
 * Usage:
 *   php scripts/kb_debug.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\KbArticle;
use App\Models\KbCategory;

function subtreeIds(\Illuminate\Support\Collection $allCategories, int $rootId): array {
    $childrenByParent = $allCategories->groupBy('parent_id');
    $ids = [];
    $stack = [$rootId];
    while ($stack) {
        $pid = array_pop($stack);
        if (in_array($pid, $ids, true)) continue;
        $ids[] = $pid;
        foreach (($childrenByParent[$pid] ?? collect()) as $child) {
            $stack[] = $child->id;
        }
    }
    return $ids;
}

$allCats = KbCategory::select(['id','parent_id','name','is_active'])->get();
$topCats = KbCategory::whereNull('parent_id')->orderBy('id')->get(['id','name']);

echo "KB Diagnostics\n";
echo "--------------\n";
echo "articles_total=" . KbArticle::count() . PHP_EOL;
echo "articles_published=" . KbArticle::where('status','published')->count() . PHP_EOL;
echo "categories_total=" . KbCategory::count() . PHP_EOL;
echo "categories_top_level=" . $topCats->count() . PHP_EOL;
echo PHP_EOL;

echo "Top-level category coverage (published articles in subtree):\n";
foreach ($topCats as $cat) {
    $ids = subtreeIds($allCats, (int)$cat->id);
    $count = KbArticle::where('status','published')->whereIn('category_id', $ids)->count();
    echo "- {$cat->id}\t{$cat->name}\t=> {$count}\n";
}
echo PHP_EOL;

$targets = [
    'گروه',
    'همکاری',
    'حاکمیت',
    'انتخابات',
    'امنیت',
    'حریم',
    'پروژه',
];

echo "Potentially relevant categories by keyword:\n";
foreach ($targets as $kw) {
    $matches = KbCategory::where('name', 'like', "%{$kw}%")->orderBy('id')->get(['id','parent_id','name']);
    if ($matches->isNotEmpty()) {
        echo "Keyword: {$kw}\n";
        foreach ($matches as $m) {
            echo "  - {$m->id}\tparent={$m->parent_id}\t{$m->name}\n";
        }
    }
}
echo PHP_EOL;

echo "Sample published articles (id, category_id, title):\n";
$sample = KbArticle::where('status','published')->orderBy('id')->take(20)->get(['id','category_id','title']);
foreach ($sample as $a) {
    echo "- {$a->id}\tcat={$a->category_id}\t{$a->title}\n";
}
echo PHP_EOL;


