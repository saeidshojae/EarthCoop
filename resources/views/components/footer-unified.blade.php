@php
    $footerContext = $footerContext ?? (request()->routeIs('welcome') ? 'welcome' : 'default');
    $isWelcomeFooter = $footerContext === 'welcome';
    $footerHomeUrl = auth()->check() ? route('home') : route('welcome');
    $contactEmail = config('app.contact_email', 'contact@earthcoop.ir');
    $contactPhone = config('app.contact_phone', '+98 9394765289');

    $quickLinks = $isWelcomeFooter
        ? [
            ['label' => __('langWelcome.footer_home'), 'url' => route('welcome')],
            ['label' => __('langWelcome.footer_about'), 'url' => '#about'],
            ['label' => __('langWelcome.footer_network'), 'url' => '#network'],
            ['label' => __('langWelcome.footer_projects'), 'url' => '#projects'],
            ['label' => __('langWelcome.footer_bahar'), 'url' => '#bahar'],
        ]
        : [
            ['label' => __('navigation.footer_home'), 'url' => $footerHomeUrl],
            ['label' => __('navigation.blog'), 'url' => route('blog.index')],
            ...(auth()->check() ? [['label' => __('navigation.footer_my_groups'), 'url' => route('groups.index')]] : []),
            ['label' => __('navigation.charter'), 'url' => route('terms')],
            ['label' => __('navigation.footer_contact'), 'url' => route('pages.show', 'contact')],
        ];

    $supportLinks = [
        ['label' => $isWelcomeFooter ? __('langWelcome.footer_faq') : __('navigation.footer_faq'), 'url' => route('pages.show', 'faq')],
        ['label' => $isWelcomeFooter ? __('langWelcome.footer_terms') : __('navigation.charter'), 'url' => route('terms')],
        ['label' => $isWelcomeFooter ? __('langWelcome.footer_contact') : __('navigation.footer_contact'), 'url' => route('pages.show', 'contact')],
    ];

    if (!$isWelcomeFooter) {
        array_unshift($supportLinks, [
            'label' => __('navigation.footer_guide'),
            'url' => route('pages.show', 'rahnmay-astfadh'),
        ]);
    }

    $socialLinks = [
        ['url' => 'https://instagram.com/earthcoop', 'icon' => 'fa-instagram', 'label' => 'Instagram'],
        ['url' => 'https://t.me/earthcoop', 'icon' => 'fa-telegram', 'label' => 'Telegram'],
        ['url' => 'https://twitter.com/earthcoop', 'icon' => 'fa-twitter', 'label' => 'Twitter'],
    ];
@endphp

@once
    <style>
        @keyframes earthcoop-logo-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .brand-logo-animated {
            display: inline-block;
            animation: earthcoop-logo-float 3s ease-in-out infinite !important;
            transform-origin: center;
            will-change: transform;
        }

        @media (prefers-reduced-motion: reduce) {
            .brand-logo-animated { animation: none !important; }
        }
        html[dir="rtl"] .site-footer-unified { direction: rtl; text-align: right; }
        html[dir="ltr"] .site-footer-unified { direction: ltr; text-align: left; }
        html[dir="ltr"] .site-footer-unified .footer-social-links { justify-content: flex-start; }
        html[dir="rtl"] .site-footer-unified .footer-social-links { justify-content: flex-start; }
        .site-footer-unified .footer-phone-row { direction: inherit; }
        .site-footer-unified .footer-phone-number { direction: ltr; unicode-bidi: isolate; }
        .site-footer-unified .footer-contact-row { display: flex; align-items: center; gap: .625rem; }
        .site-footer-unified .footer-contact-row i { flex: 0 0 auto; margin: 0 !important; }
    </style>
@endonce

<footer class="site-footer-unified bg-gentle-black text-white mt-auto py-10 px-4" style="background-color: var(--color-gentle-black);" data-footer-context="{{ $footerContext }}">
    <div class="container mx-auto">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-8">
            <div>
                <a href="{{ $footerHomeUrl }}" class="inline-flex items-center gap-2 mb-4 text-white" aria-label="EarthCoop">
                    <img src="{{ asset('icons/icon.svg') }}" alt="" class="brand-logo-animated w-9 h-9" width="36" height="36">
                    <span class="text-2xl font-bold">EarthCoop</span>
                </a>
                <p class="text-gray-300 text-sm leading-relaxed">
                    {{ __('langWelcome.footer_about_text') }}
                </p>
                <div class="footer-social-links flex items-center gap-4 mt-5">
                    @foreach($socialLinks as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="text-gray-300 hover:text-earth-green transition duration-300 text-xl"
                           aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}">
                            <i class="fab {{ $link['icon'] }}" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-lg font-bold mb-4 text-earth-green">
                    {{ $isWelcomeFooter ? __('langWelcome.footer_quick_links') : __('navigation.footer_quick_links') }}
                </h3>
                <ul class="space-y-2">
                    @foreach($quickLinks as $link)
                        <li><a href="{{ $link['url'] }}" class="text-gray-300 hover:text-earth-green transition duration-300">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="text-lg font-bold mb-4 text-ocean-blue">
                    {{ $isWelcomeFooter ? __('langWelcome.footer_support') : __('navigation.footer_support') }}
                </h3>
                <ul class="space-y-2">
                    @foreach($supportLinks as $link)
                        <li><a href="{{ $link['url'] }}" class="text-gray-300 hover:text-earth-green transition duration-300">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            @include('components.footer-docs-links', [
                'headingClass' => 'text-lg font-bold mb-4 text-earth-green',
                'linkClass' => 'text-gray-300 hover:text-earth-green transition duration-300',
                'footerDocsId' => 'footer-foundational-docs-' . $footerContext,
            ])

            <div>
                <h3 class="text-lg font-bold mb-4 text-digital-gold">
                    {{ $isWelcomeFooter ? __('langWelcome.footer_stay_connected') : __('navigation.footer_contact') }}
                </h3>
                @if($isWelcomeFooter)
                    <p class="footer-contact-row text-gray-300 mb-3 text-sm"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>{{ __('langWelcome.footer_global_office') }}</span></p>
                @endif
                <p class="footer-contact-row text-gray-300 mb-3 text-sm break-all">
                    <i class="fas fa-envelope" aria-hidden="true"></i><a href="mailto:{{ $contactEmail }}" class="hover:text-earth-green transition">{{ $contactEmail }}</a>
                </p>
                <p class="footer-contact-row footer-phone-row text-gray-300 text-sm">
                    <i class="fas fa-phone-alt flex-shrink-0" aria-hidden="true"></i><a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}" class="footer-phone-number hover:text-earth-green transition">{{ $contactPhone }}</a>
                </p>
            </div>
        </div>

        <div class="border-t border-gray-700 mt-8 pt-6 text-center text-gray-400 text-sm">
            &copy; {{ date('Y') }} EarthCoop.
            {{ $isWelcomeFooter ? __('langWelcome.footer_rights') : __('navigation.footer_copyright') }}
        </div>
    </div>
</footer>
