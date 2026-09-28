<?php

namespace App\Http\Controllers;

use App\Support\Seo\CanonicalUrl;
use App\Models\Page;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function show(string $slug, CanonicalUrl $canonicalUrl)
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $seoTitle = $page->translated_meta_title ?: $page->translated_title;
        $seoDescription = $page->translated_meta_description
            ?: $this->plainTextDescription((string) $page->translated_content);
        $seoCanonical = $canonicalUrl->to('/pages/'.$page->slug);

        // Determine which template to use
        $template = $page->template ?? 'default';

        $templateViews = [
            'about' => 'pages.templates.about',
            'help' => 'pages.templates.help',
            'cooperation' => 'pages.templates.cooperation',
            'contact' => 'pages.templates.contact',
            'faq' => 'pages.templates.faq',
            'default' => 'pages.show'
        ];

        $view = $templateViews[$template] ?? $templateViews['default'];

        if (!view()->exists($view)) {
            $view = $templateViews['default'];
            $template = 'default';
        }

        $data = compact('page', 'seoTitle', 'seoDescription', 'seoCanonical');

        if ($template === 'faq') {
            $data['faqQuestions'] = \App\Models\FaqQuestion::published()->latest()->get();
        }

        return view($view, $data);
    }

    private function plainTextDescription(string $html): string
    {
        $withBlockSpacing = preg_replace(
            '/<\s*\/?\s*(?:p|div|br|li|h[1-6]|blockquote|section|article)\b[^>]*>/iu',
            ' ',
            $html,
        ) ?? $html;

        $plainText = html_entity_decode(
            strip_tags($withBlockSpacing),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $plainText = preg_replace('/\s+/u', ' ', trim($plainText)) ?? trim($plainText);

        return Str::limit($plainText, 160);
    }
}
