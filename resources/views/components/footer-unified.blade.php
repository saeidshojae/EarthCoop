{{-- Unified Footer Component - بر اساس طراحی Home --}}
<footer class="bg-gentle-black text-white mt-auto py-8" style="background-color: var(--color-gentle-black);">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            {{-- About Section --}}
            <div>
                <h3 class="text-xl font-bold mb-4 text-earth-green">
                    {{ __('navigation.footer_about_title') }}
                </h3>
                <p class="text-gray-300 text-sm leading-relaxed">
                    {{ __('navigation.footer_about_text') }}
                </p>
            </div>

            {{-- Quick Links --}}
            <div>
                <h3 class="text-xl font-bold mb-4 text-earth-green">
                    {{ __('navigation.footer_quick_links') }}
                </h3>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('home') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                            {{ __('navigation.footer_home') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('blog.index') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                            {{ __('navigation.blog') }}
                        </a>
                    </li>
                    @auth
                        <li>
                            <a href="{{ route('groups.index') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                                {{ __('navigation.footer_my_groups') }}
                            </a>
                        </li>
                    @endauth
                    <li>
                        <a href="{{ route('terms') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                            {{ __('navigation.charter') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pages.show', 'contact') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                            {{ __('navigation.footer_contact') }}
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Support --}}
            <div>
                <h3 class="text-xl font-bold mb-4 text-earth-green">
                    {{ __('navigation.footer_support') }}
                </h3>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('pages.show', 'rahnmay-astfadh') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                            {{ __('navigation.footer_guide') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pages.show', 'faq') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                            {{ __('navigation.footer_faq') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('pages.show', 'contact') }}" class="text-gray-300 hover:text-earth-green transition duration-300">
                            {{ __('navigation.footer_contact') }}
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Social Media --}}
            <div>
                <h3 class="text-xl font-bold mb-4 text-earth-green">
                    {{ __('navigation.footer_social_media') }}
                </h3>
                <div class="flex space-x-4 rtl:space-x-reverse">
                    @php
                        $socialLinks = [
                            'telegram' => ['url' => 'https://t.me/earthcoop', 'icon' => 'fa-telegram', 'label' => 'Telegram'],
                            'instagram' => ['url' => 'https://instagram.com/earthcoop', 'icon' => 'fa-instagram', 'label' => 'Instagram'],
                            'twitter' => ['url' => 'https://twitter.com/earthcoop', 'icon' => 'fa-twitter', 'label' => 'Twitter'],
                        ];
                    @endphp
                    @foreach($socialLinks as $platform => $link)
                        <a href="{{ $link['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="text-gray-300 hover:text-earth-green transition duration-300 text-2xl"
                           aria-label="{{ $link['label'] }}"
                           title="{{ $link['label'] }}">
                            <i class="fab {{ $link['icon'] }}"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Copyright --}}
        <div class="border-t border-gray-700 mt-8 pt-6 text-center">
            <p class="text-gray-400 text-sm">
                &copy; {{ date('Y') }} EarthCoop. {{ __('navigation.footer_copyright') }}
            </p>
        </div>
    </div>
</footer>