{{--
    Universal Footer Component
    فوتر یکپارچه برای تمام صفحات
--}}

<style>
    .footer-universal {
        background: linear-gradient(135deg, var(--color-gentle-black) 0%, #2d3748 100%);
        color: var(--color-pure-white);
        padding: 3rem 0 1rem;
        margin-top: auto;
    }

    body.dark-mode .footer-universal {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
    }

    .footer-universal a {
        color: rgba(255, 255, 255, 0.8);
        transition: color 0.3s ease;
    }

    .footer-universal a:hover {
        color: var(--color-earth-green);
        text-decoration: none;
    }

    .footer-social-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .footer-social-icon:hover {
        background: var(--color-earth-green);
        transform: translateY(-3px);
    }

    .footer-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 2rem 0 1rem;
    }
</style>

@php
    // ===== داده‌های پویا =====
    $year = date('Y');
    $socialLinks = [
        'telegram'   => ['url' => 'https://t.me/earthcoop', 'icon' => 'fa-telegram', 'label' => 'Telegram'],
        'instagram'  => ['url' => 'https://instagram.com/earthcoop', 'icon' => 'fa-instagram', 'label' => 'Instagram'],
        'twitter'    => ['url' => 'https://twitter.com/earthcoop', 'icon' => 'fa-twitter', 'label' => 'Twitter'],
    ];

    // اطلاعات تماس (می‌تواند از config یا env خوانده شود)
    $contactEmail = config('app.contact_email', 'info@earthcoop.org');
    $contactPhone = config('app.contact_phone', '+98 21 1234 5678');
    $contactAddress = __('footer.address') ?? 'تهران، ایران';

    // دریافت صفحات منتشر شده برای بخش منابع (حداکثر ۵ عدد)
    $resourcePages = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('pages')) {
        $resourcePages = \App\Models\Page::where('is_published', 1)->limit(5)->get();
    }
@endphp

<footer class="footer-universal">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
            {{-- ستون ۱: درباره --}}
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <img src="{{ asset('images/logo.png') }}" alt="لوگو" class="h-10" style="filter: brightness(0) invert(1);">
                    <span class="text-xl font-bold">EarthCoop</span>
                </div>
                <p class="text-sm text-gray-300 mb-4">
                    {{ __('footer.about_text') ?? 'پلتفرم تعاونی جهانی برای ساخت آینده‌ای پایدار و عادلانه' }}
                </p>
                <div class="flex gap-3">
                    @foreach($socialLinks as $platform => $link)
                        <a href="{{ $link['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="footer-social-icon"
                           aria-label="{{ $link['label'] }}"
                           title="{{ $link['label'] }}">
                            <i class="fab {{ $link['icon'] }}"></i>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ستون ۲: دسترسی سریع --}}
            <div>
                <h3 class="text-lg font-bold mb-4 text-white">{{ __('footer.quick_links') ?? 'دسترسی سریع' }}</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('welcome') }}" class="text-sm hover:text-earth-green transition">
                            <i class="fas fa-chevron-left me-2 text-xs"></i>{{ __('footer.home') ?? 'صفحه اصلی' }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('blog.index') }}" class="text-sm hover:text-earth-green transition">
                            <i class="fas fa-chevron-left me-2 text-xs"></i>{{ __('footer.blog') ?? 'بلاگ' }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('terms') }}" class="text-sm hover:text-earth-green transition">
                            <i class="fas fa-chevron-left me-2 text-xs"></i>{{ __('footer.charter') ?? 'اساسنامه' }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pages.show', 'faq') }}" class="text-sm hover:text-earth-green transition">
                            <i class="fas fa-chevron-left me-2 text-xs"></i>{{ __('footer.faq') ?? 'سوالات متداول' }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pages.show', 'contact') }}" class="text-sm hover:text-earth-green transition">
                            <i class="fas fa-chevron-left me-2 text-xs"></i>{{ __('navigation.footer_contact') ?? 'تماس با ما' }}
                        </a>
                    </li>
                    @auth
                        @if(Route::has('auction.index'))
                            <li>
                                <a href="{{ route('auction.index') }}" class="text-sm hover:text-earth-green transition">
                                    <i class="fas fa-chevron-left me-2 text-xs"></i>{{ __('footer.stock_office') ?? 'دفتر سهام' }}
                                </a>
                            </li>
                        @endif
                    @endauth
                </ul>
            </div>

            {{-- ستون ۳: منابع --}}
            <div>
                <h3 class="text-lg font-bold mb-4 text-white">{{ __('footer.resources') ?? 'منابع' }}</h3>
                <ul class="space-y-2">
                    @forelse($resourcePages as $page)
                        <li>
                            <a href="{{ url('/pages/' . $page->slug) }}" class="text-sm hover:text-earth-green transition">
                                <i class="fas fa-chevron-left me-2 text-xs"></i>{{ $page->title ?? $page->translated_title ?? 'صفحه' }}
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">{{ __('footer.no_pages') ?? 'هیچ صفحه‌ای یافت نشد' }}</li>
                    @endforelse

                    @auth
                        @if(auth()->user()->is_admin == 1 && Route::has('admin.dashboard'))
                            <li>
                                <a href="{{ route('admin.dashboard') }}" class="text-sm hover:text-ocean-blue transition">
                                    <i class="fas fa-shield-alt me-2 text-xs"></i>{{ __('footer.admin_panel') ?? 'پنل مدیریت' }}
                                </a>
                            </li>
                        @endif
                    @endauth
                </ul>
            </div>

            {{-- ستون ۴: تماس --}}
            <div>
                <h3 class="text-lg font-bold mb-4 text-white">{{ __('footer.contact') ?? 'تماس با ما' }}</h3>
                <ul class="space-y-3">
                    <li class="text-sm flex items-start gap-2">
                        <i class="fas fa-envelope text-earth-green mt-1"></i>
                        <a href="mailto:{{ $contactEmail }}" class="hover:text-earth-green transition">
                            {{ $contactEmail }}
                        </a>
                    </li>
                    <li class="text-sm flex items-start gap-2">
                        <i class="fas fa-phone text-earth-green mt-1"></i>
                        <span>{{ $contactPhone }}</span>
                    </li>
                    <li class="text-sm flex items-start gap-2">
                        <i class="fas fa-map-marker-alt text-earth-green mt-1"></i>
                        <span>{{ $contactAddress }}</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- خط جداکننده --}}
        <div class="footer-divider"></div>

        {{-- نوار پایین --}}
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-gray-400">
            <div>
                <p>
                    &copy; {{ $year }} <span class="text-earth-green font-medium">EarthCoop</span>.
                    {{ __('footer.rights_reserved') ?? 'تمامی حقوق محفوظ است' }}.
                </p>
            </div>
            <div class="flex gap-4">
                <a href="{{ route('terms') }}" class="hover:text-earth-green transition">
                    {{ __('footer.terms') ?? 'شرایط و قوانین' }}
                </a>
                <span>|</span>
                <a href="#" class="hover:text-earth-green transition">
                    {{ __('footer.privacy') ?? 'حریم خصوصی' }}
                </a>
                <span>|</span>
                <a href="{{ route('pages.show', 'contact') }}" class="hover:text-earth-green transition">
                    {{ __('navigation.footer_contact') ?? 'تماس با ما' }}
                </a>
                <span>|</span>
                <a href="{{ route('pages.show', 'faq') }}" class="hover:text-earth-green transition">
                    {{ __('footer.faq') ?? 'سوالات متداول' }}
                </a>
            </div>
        </div>

        {{-- اطلاعات نسخه (فقط در محیط توسعه) --}}
        @if(config('app.debug'))
            <div class="mt-4 text-center text-xs text-gray-500">
                Version: {{ config('app.version', '1.0.0') }} | Environment: {{ config('app.env') }}
            </div>
        @endif
    </div>
</footer>