<?php

namespace Tests\Unit\Seo;

use App\Support\Seo\PillarArticleRegistry;
use PHPUnit\Framework\TestCase;

class PillarArticleRegistryTest extends TestCase
{
    /**
     * @dataProvider ownershipProvider
     */
    public function test_each_approved_article_resolves_to_exactly_one_owning_pillar(string $slug, string $pillar): void
    {
        $this->assertSame($pillar, PillarArticleRegistry::ownerForSlug($slug));
    }

    public function test_unknown_or_legacy_article_has_no_pillar_ownership(): void
    {
        $this->assertNull(PillarArticleRegistry::ownerForSlug('existing-production-article'));
    }

    public function test_registry_does_not_assign_one_article_to_multiple_pillars(): void
    {
        $paths = [];

        foreach (PillarArticleRegistry::all() as $pillar => $articles) {
            foreach ($articles as $article) {
                $paths[$article['path']][] = $pillar;
            }
        }

        foreach ($paths as $path => $owners) {
            $this->assertCount(1, $owners, $path.' must have exactly one owner.');
        }
    }

    public static function ownershipProvider(): array
    {
        return [
            'people economy' => ['people-economy-explained', 'economy'],
            'anti monopoly' => ['free-market-without-monopoly', 'economy'],
            'financial transparency' => ['financial-transparency-and-privacy', 'economy-glass'],
            'participatory governance' => ['participatory-governance-beyond-voting', 'governance'],
            'continuous elections' => ['continuous-elections-explained', 'governance-elections'],
            'platform cooperative' => ['platform-cooperative-and-earthcoop', 'cooperative'],
            'earth justice' => ['earth-in-earthcoop-justice', 'justice'],
            'private property and commons' => ['private-property-and-common-resources', 'economy-ownership'],
        ];
    }
}
