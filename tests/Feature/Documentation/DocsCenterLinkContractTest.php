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
        $this->assertStringNotContainsString('/governance/translation-policy', $serialized);
    }

    public function test_footer_uses_the_canonical_center_once_and_publication_policy_from_config(): void
    {
        $source = file_get_contents(resource_path('views/components/footer-docs-links.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("\$docsLinks['center']['href']", $source);
        $this->assertStringContainsString("\$docsLinks['publication_policy']['href']", $source);
        $this->assertStringNotContainsString("\$docsLinks['base_url'] }}/fa/introduction", $source);
        $this->assertStringNotContainsString("@foreach(\$docsLinks['main'] as \$link)", $source);
    }

    public function test_welcome_and_authenticated_navigation_consume_docs_config_while_internal_rules_stay_internal(): void
    {
        $welcome = file_get_contents(resource_path('views/partials/docs-section.blade.php'));
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        $this->assertIsString($welcome);
        $this->assertIsString($sidebar);
        $this->assertIsString($drawer);

        $this->assertStringContainsString("\$docsLinks['center']['href']", $welcome);
        $this->assertStringNotContainsString("\$docsLinks['base_url'] }}/fa/introduction", $welcome);

        foreach ([$sidebar, $drawer] as $source) {
            $this->assertStringContainsString("['center']['href']", $source);
            $this->assertStringContainsString("['foundational_index']['href']", $source);
            $this->assertStringContainsString("route('terms')", $source);
            $this->assertStringContainsString("route('najm-bahar.agreement')", $source);
            $this->assertStringContainsString("route('elections.guideline')", $source);
            $this->assertStringContainsString("route('participation.credit-regulation')", $source);
        }
    }
}
