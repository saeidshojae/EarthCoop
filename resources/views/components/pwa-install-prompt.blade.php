@php
    $pwaLocale = app()->getLocale();
    $pwaText = match ($pwaLocale) {
        'en' => [
            'title' => 'Install EarthCoop',
            'body' => 'Add EarthCoop to your home screen for faster, app-like access.',
            'install' => 'Install',
            'later' => 'Later',
            'ios' => 'On iPhone: tap Share, then “Add to Home Screen”.',
        ],
        'ar' => [
            'title' => 'تثبيت EarthCoop',
            'body' => 'أضف EarthCoop إلى الشاشة الرئيسية للوصول السريع كتطبيق.',
            'install' => 'تثبيت',
            'later' => 'لاحقًا',
            'ios' => 'على iPhone: اضغط مشاركة ثم «إضافة إلى الشاشة الرئيسية».',
        ],
        default => [
            'title' => 'نصب اپ EarthCoop',
            'body' => 'برای دسترسی سریع‌تر، EarthCoop را به صفحه اصلی موبایل اضافه کنید.',
            'install' => 'نصب اپ',
            'later' => 'بعداً',
            'ios' => 'در آیفون: دکمه Share را بزنید و «Add to Home Screen» را انتخاب کنید.',
        ],
    };
@endphp

<div id="pwa-install-banner"
     class="fixed inset-x-3 bottom-4 z-[100] hidden md:max-w-md md:mx-auto"
     data-pwa-install-banner
     data-ios-message="{{ $pwaText['ios'] }}"
     role="dialog"
     aria-live="polite"
     aria-label="{{ $pwaText['title'] }}">
    <div class="rounded-2xl border border-emerald-200 bg-white/95 p-4 shadow-2xl backdrop-blur dark:border-emerald-800 dark:bg-slate-900/95">
        <div class="flex items-start gap-3">
            <img src="{{ asset('icons/icon-192.png') }}" width="48" height="48" alt="" class="h-12 w-12 flex-none rounded-xl">
            <div class="min-w-0 flex-1 font-vazirmatn">
                <strong class="block text-base text-slate-900 dark:text-white">{{ $pwaText['title'] }}</strong>
                <p id="pwa-install-description" class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $pwaText['body'] }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" data-pwa-install-button class="rounded-full bg-earth-green px-5 py-2 text-sm font-bold text-white shadow hover:bg-dark-green focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
                        <i class="fas fa-download ml-1" aria-hidden="true"></i>{{ $pwaText['install'] }}
                    </button>
                    <button type="button" data-pwa-dismiss-button class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        {{ $pwaText['later'] }}
                    </button>
                </div>
            </div>
            <button type="button" data-pwa-close-button aria-label="بستن" class="flex-none rounded-full p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>
