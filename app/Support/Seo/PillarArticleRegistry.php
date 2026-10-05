<?php

namespace App\Support\Seo;

final class PillarArticleRegistry
{
    /**
     * Curated published supporting articles for stable Persian Pillars.
     *
     * @return array<int, array{label: string, path: string}>
     */
    public static function for(string $pillar): array
    {
        return self::all()[$pillar] ?? [];
    }

    /**
     * @return array<string, array<int, array{label: string, path: string}>>
     */
    public static function all(): array
    {
        return [
            'economy' => [
                [
                    'label' => 'اقتصاد مردمی چیست؟ از مشارکت اقتصادی تا اقتصاد آزاد مردمی',
                    'path' => '/blog/people-economy-explained',
                ],
                [
                    'label' => 'بازار آزاد بدون انحصار چگونه ممکن است؟',
                    'path' => '/blog/free-market-without-monopoly',
                ],
            ],
            'economy-glass' => [
                [
                    'label' => 'شفافیت مالی و حریم خصوصی؛ آیا می‌توان هر دو را داشت؟',
                    'path' => '/blog/financial-transparency-and-privacy',
                ],
            ],
            'governance' => [
                [
                    'label' => 'حکمرانی مشارکتی فراتر از رأی‌دادن دوره‌ای',
                    'path' => '/blog/participatory-governance-beyond-voting',
                ],
            ],
            'governance-elections' => [
                [
                    'label' => 'انتخابات مستمر چیست و چه تفاوتی با انتخابات دوره‌ای دارد؟',
                    'path' => '/blog/continuous-elections-explained',
                ],
            ],
            'cooperative' => [
                [
                    'label' => 'تعاونی پلتفرمی چیست و چه نسبتی با EarthCoop دارد؟',
                    'path' => '/blog/platform-cooperative-and-earthcoop',
                ],
            ],
            'justice' => [
                [
                    'label' => 'زمین در EarthCoop چه جایگاهی در تعریف عدالت دارد؟',
                    'path' => '/blog/earth-in-earthcoop-justice',
                ],
            ],
            'economy-ownership' => [
                [
                    'label' => 'مالکیت خصوصی و منابع مشترک چگونه کنار هم قرار می‌گیرند؟',
                    'path' => '/blog/private-property-and-common-resources',
                ],
            ],
        ];
    }
}
