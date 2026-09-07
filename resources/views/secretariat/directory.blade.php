@extends('layouts.unified')

@section('title', 'دبیرخانه‌های من')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-6xl">
    <div class="mb-6">
        <p class="text-sm text-gray-500 mb-1">EarthCoop Secretariat</p>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">دبیرخانه‌های من</h1>
        <p class="text-sm text-gray-500 mt-2">فقط دفترهایی نمایش داده می‌شوند که Policy دبیرخانه اجازه مشاهده آن‌ها را به شما می‌دهد.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($offices as $office)
            <a href="{{ route('secretariat.index', $office) }}"
               class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500 font-mono mb-2">{{ $office->code }}</div>
                        <h2 class="font-bold text-lg truncate">{{ $office->name }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $office->office_type }}</span>
                            <span class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-1">{{ $office->default_confidentiality }}</span>
                        </div>
                    </div>
                    <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-box-archive"></i>
                    </span>
                </div>
            </a>
        @empty
            <div class="md:col-span-2 xl:col-span-3 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-10 text-center text-gray-500">
                <i class="fa-regular fa-folder-open text-3xl mb-3"></i>
                <p>در حال حاضر دفتر دبیرخانه‌ای در محدوده دسترسی شما وجود ندارد.</p>
            </div>
        @endforelse
    </div>

    <p class="mt-4 text-xs text-gray-500">فهرست S2 به‌صورت محافظه‌کارانه حداکثر ۵۰۰ دفتر فعال را ارزیابی می‌کند؛ بهینه‌سازی query-level مجوزها در hardening مقیاس انجام می‌شود.</p>
</div>
@endsection
