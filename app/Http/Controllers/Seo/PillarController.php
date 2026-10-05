<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Support\Seo\CanonicalUrl;
use App\Support\Seo\PillarRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PillarController extends Controller
{
    public function __invoke(Request $request, CanonicalUrl $canonicalUrl): View
    {
        $key = (string) $request->route('pillar');
        $pillar = PillarRegistry::get($key);
        $canonical = $canonicalUrl->to($pillar['path']);

        $breadcrumbItems = array_map(
            function (array $item, int $index) use ($canonicalUrl): array {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $canonicalUrl->to($item['path']),
                ];
            },
            $pillar['breadcrumbs'],
            array_keys($pillar['breadcrumbs']),
        );

        $seoJsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => $pillar['meta_title'],
                'description' => $pillar['description'],
                'inLanguage' => 'fa',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    '@id' => $canonicalUrl->to('/').'#website',
                    'url' => $canonicalUrl->to('/'),
                    'name' => 'EarthCoop',
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbItems,
            ],
        ];

        return view('seo.pillars.show', [
            'pillar' => $pillar,
            'seoTitle' => $pillar['meta_title'],
            'seoDescription' => $pillar['description'],
            'seoCanonical' => $canonical,
            'seoRobots' => 'index,follow',
            'seoType' => 'website',
            'seoJsonLd' => $seoJsonLd,
        ]);
    }
}
