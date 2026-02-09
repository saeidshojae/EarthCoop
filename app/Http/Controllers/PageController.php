<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Page;

class PageController extends Controller
{
    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        // Set page title for SEO
        $pageTitle = $page->translated_title . ' - ' . config('app.name', 'EarthCoop');

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

        $data = compact('page', 'pageTitle');

        if ($template === 'faq') {
            $data['faqQuestions'] = \App\Models\FaqQuestion::published()->latest()->get();
        }

        return view($view, $data);
    }
}
