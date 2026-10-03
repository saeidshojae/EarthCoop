<?php

$docsBaseUrl = 'https://docs.earthcoop.ir';

$documentUrl = static fn (string $id): string => "{$docsBaseUrl}/documents/{$id}/";

return [
    'base_url' => $docsBaseUrl,

    'center' => [
        'id' => 'center',
        'label_key' => 'langWelcome.docs_footer_center',
        'href' => "{$docsBaseUrl}/",
    ],

    'publication_policy' => [
        'id' => 'publication-policy',
        'label_key' => 'langWelcome.docs_footer_governance',
        'href' => $documentUrl('publication-policy'),
    ],

    'github' => [
        'id' => 'github',
        'label_key' => 'langWelcome.docs_footer_github',
        'href' => 'https://github.com/saeidshojae/EarthCoop-docs',
    ],

    'foundational_index' => [
        'id' => 'foundational-index',
        'code' => '',
        'title_key' => 'langWelcome.docs_foundational_index',
        'href' => "{$docsBaseUrl}/documents/",
        'icon' => 'fa-list-ul',
    ],

    'references' => [
        [
            'id' => 'econ-ref-01',
            'code' => 'ECON-REF-01',
            'title' => 'سند مرجع اقتصاد و معماری نجم‌بهار',
            'href' => $documentUrl('econ-ref-01'),
            'icon' => 'fa-book-open',
        ],
    ],

    'foundational' => [
        [
            'id' => 'fc',
            'code' => 'FC',
            'title_key' => 'langWelcome.docs_foundational_fc',
            'href' => $documentUrl('fc'),
            'icon' => 'fa-file-alt',
        ],
        [
            'id' => 'ch',
            'code' => 'CH',
            'title_key' => 'langWelcome.docs_foundational_ch',
            'href' => $documentUrl('ch'),
            'icon' => 'fa-scroll',
        ],
        [
            'id' => 'co',
            'code' => 'CO',
            'title_key' => 'langWelcome.docs_foundational_co',
            'href' => $documentUrl('co'),
            'icon' => 'fa-balance-scale',
        ],
        [
            'id' => 'ex',
            'code' => 'EX',
            'title_key' => 'langWelcome.docs_foundational_ex',
            'href' => $documentUrl('ex'),
            'icon' => 'fa-clipboard-list',
        ],
        [
            'id' => 'econ',
            'code' => 'ECON',
            'title_key' => 'langWelcome.docs_foundational_econ',
            'href' => $documentUrl('econ'),
            'icon' => 'fa-coins',
        ],
        [
            'id' => 'dg',
            'code' => 'DG',
            'title_key' => 'langWelcome.docs_foundational_dg',
            'href' => $documentUrl('dg'),
            'icon' => 'fa-network-wired',
        ],
        [
            'id' => 'jud',
            'code' => 'JUD',
            'title_key' => 'langWelcome.docs_foundational_jud',
            'href' => $documentUrl('jud'),
            'icon' => 'fa-gavel',
        ],
        [
            'id' => 'loc',
            'code' => 'LOC',
            'title_key' => 'langWelcome.docs_foundational_loc',
            'href' => $documentUrl('loc'),
            'icon' => 'fa-users',
        ],
        [
            'id' => 'eth',
            'code' => 'ETH',
            'title_key' => 'langWelcome.docs_foundational_eth',
            'href' => $documentUrl('eth'),
            'icon' => 'fa-shield-alt',
        ],
        [
            'id' => 'std',
            'code' => 'STD',
            'title_key' => 'docs.foundational_std',
            'href' => $documentUrl('std'),
            'icon' => 'fa-screwdriver-wrench',
        ],
    ],
];
