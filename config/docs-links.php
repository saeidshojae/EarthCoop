<?php

$docsBaseUrl = 'https://docs.earthcoop.ir';

return [
    'base_url' => $docsBaseUrl,

    'main' => [
        [
            'id' => 'center',
            'label_key' => 'langWelcome.docs_footer_center',
            'href' => "{$docsBaseUrl}/fa/introduction",
        ],
        [
            'id' => 'api',
            'label_key' => 'langWelcome.docs_footer_api',
            'href' => "{$docsBaseUrl}/fa/api/overview",
        ],
        [
            'id' => 'governance',
            'label_key' => 'langWelcome.docs_footer_governance',
            'href' => "{$docsBaseUrl}/governance/translation-policy",
        ],
        [
            'id' => 'github',
            'label_key' => 'langWelcome.docs_footer_github',
            'href' => 'https://github.com/saeidshojae/EarthCoop-docs',
        ],
    ],

    'foundational_index' => [
        'id' => 'foundational-index',
        'code' => '',
        'title_key' => 'langWelcome.docs_foundational_index',
        'href' => "{$docsBaseUrl}/fa/foundational",
        'icon' => 'fa-list-ul',
    ],

    'foundational' => [
        [
            'id' => 'fc',
            'code' => 'FC',
            'title_key' => 'langWelcome.docs_foundational_fc',
            'href' => "{$docsBaseUrl}/fa/foundational/fc/00-overview",
            'icon' => 'fa-file-alt',
        ],
        [
            'id' => 'ch',
            'code' => 'CH',
            'title_key' => 'langWelcome.docs_foundational_ch',
            'href' => "{$docsBaseUrl}/fa/foundational/ch/00-overview",
            'icon' => 'fa-scroll',
        ],
        [
            'id' => 'co',
            'code' => 'CO',
            'title_key' => 'langWelcome.docs_foundational_co',
            'href' => "{$docsBaseUrl}/fa/foundational/co/00-overview",
            'icon' => 'fa-balance-scale',
        ],
        [
            'id' => 'ex',
            'code' => 'EX',
            'title_key' => 'langWelcome.docs_foundational_ex',
            'href' => "{$docsBaseUrl}/fa/foundational/ex/00-overview",
            'icon' => 'fa-clipboard-list',
        ],
        [
            'id' => 'econ',
            'code' => 'ECON',
            'title_key' => 'langWelcome.docs_foundational_econ',
            'href' => "{$docsBaseUrl}/fa/foundational/econ/00-overview",
            'icon' => 'fa-coins',
        ],
        [
            'id' => 'dg',
            'code' => 'DG',
            'title_key' => 'langWelcome.docs_foundational_dg',
            'href' => "{$docsBaseUrl}/fa/foundational/dg/00-overview",
            'icon' => 'fa-network-wired',
        ],
        [
            'id' => 'jud',
            'code' => 'JUD',
            'title_key' => 'langWelcome.docs_foundational_jud',
            'href' => "{$docsBaseUrl}/fa/foundational/jud/00-overview",
            'icon' => 'fa-gavel',
        ],
        [
            'id' => 'loc',
            'code' => 'LOC',
            'title_key' => 'langWelcome.docs_foundational_loc',
            'href' => "{$docsBaseUrl}/fa/foundational/loc/00-overview",
            'icon' => 'fa-users',
        ],
        [
            'id' => 'eth',
            'code' => 'ETH',
            'title_key' => 'langWelcome.docs_foundational_eth',
            'href' => "{$docsBaseUrl}/fa/foundational/eth/00-overview",
            'icon' => 'fa-shield-alt',
        ],
    ],
];
