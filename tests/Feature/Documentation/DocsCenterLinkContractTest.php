<?php

namespace Tests\Feature\Documentation;

use Tests\TestCase;

class DocsCenterLinkContractTest extends TestCase
{
    public function test_docs_links_use_the_self_hosted_center_and_all_ten_foundational_documents(): void
    {
        $links = require config_path('docs-links.php');

        $this->assertSame('https://docs.earthcoop.ir', $links['base_url']);
        $this->assertSame('https://docs.earthcoop.ir/', $links['center']['href']);
        $this->assertSame('https://docs.earthcoop.ir/', $links['foundational_index']['href']);
        $this->assertSame('https://docs.earthcoop.ir/#/documents/publication-policy', $links['publication_policy']['href']);
        $this->assertSame('https://github.com/saeidshojae/EarthCoop-docs', $links['github']['href']);
        $this->assertArrayNotHasKey('main', $links);

        $expected = [
            'fc' => 'FC',
            'ch' => 'CH',
            'co' => 'CO',
            'ex' => 'EX',
            'econ' => 'ECON',
            'dg' => 'DG',
            'jud' => 'JUD',
            'loc' => 'LOC',
            'eth' => 'ETH',
            'std' => 'STD',
        ];

        $this->assertCount(10, $links['foundational']);

        foreach ($expected as $id => $code) {
            $document = collect($links['foundational'])->firstWhere('id', $id);

            $this->assertNotNull($document, "Missing foundational document {$id}");
            $this->assertSame($code, $document['code']);
            $this->assertSame("https://docs.earthcoop.ir/#/documents/{$id}", $document['href']);
        }

        $serialized = json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('/fa/introduction', $serialized);
        $this->assertStringNotContainsString('/fa/foundational', $serialized);
        $this->assertStringNotContainsString('/00-overview', $serialized);
        $this->assertStringNotContainsString('/fa/api/overview', $serialized);
        $this->assertStringNotContainsString('/governance/translation-policy', $serialized);
    }

    public function test_footer_uses_the_canonical_center_once_and_publication_policy_from_config(): void
    {
        $source = file_get_contents(resource_path('views/components/footer-docs-links.blade.php'));

        $this->assertIsString($source);
        $this->assertSame(1, substr_count($source, "\$docsLinks['center']['href']"));
        $this->assertStringContainsString("\$docsLinks['publication_policy']['href']", $source);
        $this->assertStringContainsString("\$docsLinks['github']['href']", $source);
        $this->assertStringNotContainsString("\$docsLinks['base_url'] }}/fa/introduction", $source);
        $this->assertStringNotContainsString("@foreach(\$docsLinks['main'] as \$link)", $source);
    }

    public function test_welcome_uses_the_canonical_center_and_foundational_collection(): void
    {
        $source = file_get_contents(resource_path('views/partials/docs-section.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("\$docsLinks['center']['href']", $source);
        $this->assertStringContainsString("\$docsLinks['foundational']", $source);
        $this->assertStringNotContainsString("\$docsLinks['base_url'] }}/fa/introduction", $source);
    }

    public function test_legacy_center_anchors_in_large_navigation_templates_are_normalized_without_changing_internal_rules(): void
    {
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));
        $runtime = file_get_contents(resource_path('js/docs-center-link-normalizer.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($sidebar);
        $this->assertIsString($drawer);
        $this->assertIsString($runtime);
        $this->assertIsString($app);

        $this->assertStringContainsString('docs-center-link-normalizer.js', $app);
        $this->assertStringContainsString('https://docs.earthcoop.ir/fa/introduction', $runtime);
        $this->assertStringContainsString('https://docs.earthcoop.ir/', $runtime);
        $this->assertStringContainsString('querySelectorAll', $runtime);

        foreach ([$sidebar, $drawer] as $source) {
            $this->assertStringContainsString("['foundational_index']['href']", $source);
            $this->assertStringContainsString("route('terms')", $source);
            $this->assertStringContainsString("route('najm-bahar.agreement')", $source);
            $this->assertStringContainsString("route('elections.guideline')", $source);
            $this->assertStringContainsString("route('participation.credit-regulation')", $source);
        }
    }
}
