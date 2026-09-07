@php
    $mobileAccountUser = auth()->user();
@endphp

<div class="mobile-account-root relative" x-data="{ accountOpen: false }" @click.outside="accountOpen = false">
    <button type="button"
            @click.stop="accountOpen = !accountOpen"
            class="mobile-account-trigger inline-flex h-9 w-9 items-center justify-center overflow-hidden rounded-full shadow-sm ring-1 ring-black/5"
            aria-label="منوی حساب کاربری"
            :aria-expanded="accountOpen">
        @if($mobileAccountUser?->avatar)
            <img src="{{ asset('images/users/avatars/' . $mobileAccountUser->avatar) }}"
                 alt="{{ $mobileAccountUser->fullName() }}"
                 class="h-full w-full object-cover">
        @else
            <span class="flex h-full w-full items-center justify-center bg-earth-green text-white">
                <i class="fas fa-user text-sm" aria-hidden="true"></i>
            </span>
        @endif
    </button>

    <div x-show="accountOpen" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="mobile-account-dropdown w-56 overflow-hidden rounded-2xl border border-gray-200 bg-white py-2 text-right shadow-2xl"
         role="menu">
        <div class="border-b border-gray-100 px-4 py-3">
            <div class="truncate text-sm font-bold text-gentle-black">{{ $mobileAccountUser?->fullName() }}</div>
            <div class="mt-0.5 truncate text-xs text-gray-500">{{ $mobileAccountUser?->email }}</div>
        </div>

        <a href="{{ route('profile.show') }}" class="flex items-center gap-3 px-4 py-3 text-sm text-gentle-black hover:bg-light-gray">
            <i class="fas fa-user-circle w-5 text-earth-green" aria-hidden="true"></i>
            <span>پروفایل من</span>
        </a>
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-3 text-sm text-gentle-black hover:bg-light-gray">
            <i class="fas fa-user-pen w-5 text-ocean-blue" aria-hidden="true"></i>
            <span>ویرایش حساب</span>
        </a>

        @if($mobileAccountUser?->is_admin == 1)
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 text-sm text-gentle-black hover:bg-light-gray">
                <i class="fas fa-user-shield w-5 text-purple-600" aria-hidden="true"></i>
                <span>{{ __('navigation.admin_dashboard') }}</span>
            </a>
        @endif

        <div class="mt-1 border-t border-gray-100 pt-1">
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('logout-form-mobile-account').submit();"
               class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 hover:bg-red-50">
                <i class="fas fa-sign-out-alt w-5" aria-hidden="true"></i>
                <span>{{ __('navigation.logout') }}</span>
            </a>
            <form id="logout-form-mobile-account" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    </div>
</div>
