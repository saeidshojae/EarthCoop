<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Chronicle\ChronicleMilestone;
use App\Models\Page;
use App\Modules\Blog\Models\Post;
use App\Support\Seo\CanonicalUrl;
use App\Support\Seo\PillarRegistry;
use App\Support\Seo\SitemapEntry;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

final class SitemapController extends Controller
{
    public function __invoke(CanonicalUrl $canonicalUrl): Response
    {
        $entries = [
            new SitemapEntry($canonicalUrl->to('/')),
            new SitemapEntry($canonicalUrl->to('/terms')),
            new SitemapEntry($canonicalUrl->to('/privacy')),
            new SitemapEntry($canonicalUrl->to('/blog')),
            new SitemapEntry(
                $canonicalUrl->to('/chronicle'),
                $this->optionalTableExists('chronicle_milestones')
                    ? ChronicleMilestone::query()->published()->latest('updated_at')->first()?->updated_at
                    : null,
            ),
        ];

        foreach (PillarRegistry::paths() as $path) {
            $entries[] = new SitemapEntry($canonicalUrl->to($path));
        }

        if ($this->optionalTableExists('pages')) {
            Page::query()
                ->where('is_published', true)
                ->orderBy('slug')
                ->get()
                ->each(function (Page $page) use (&$entries, $canonicalUrl): void {
                    $entries[] = new SitemapEntry(
                        $canonicalUrl->to('/pages/'.$page->slug),
                        $page->updated_at,
                    );
                });
        }

        if ($this->optionalTableExists('blog_posts')) {
            Post::query()
                ->published()
                ->orderBy('slug')
                ->get()
                ->each(function (Post $post) use (&$entries, $canonicalUrl): void {
                    $entries[] = new SitemapEntry(
                        $canonicalUrl->to('/blog/'.$post->slug),
                        $post->updated_at ?? $post->published_at,
                    );
                });
        }

        usort(
            $entries,
            static fn (SitemapEntry $left, SitemapEntry $right): int => $left->location <=> $right->location,
        );

        return response()
            ->view('seo.sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function optionalTableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
