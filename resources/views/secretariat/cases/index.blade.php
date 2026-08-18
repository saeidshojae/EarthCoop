@extends('layouts.unified')

@section('title', 'پرونده‌های دبیرخانه - ' . $office->name)

@section('content')
<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <a href="{{ route('secretariat.index', $office) }}" class="text-sm text-emerald-700 hover:underline">بازگشت به دفتر</a>
            <h1 class="text-2xl font-bold mt-2">پرونده‌های {{ $office->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">تجمیع کنترل‌شده رکوردهای رسمی حول یک موضوع، بدون کپی حقیقت اسناد</p>
        </div>
        @can('inspect', $office)
            <a href="{{ route('secretariat.cases.create', $office) }}" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 inline-flex items-center gap-2">
                <i class="fa-solid fa-folder-plus"></i> پرونده جدید
            </a>
        @endcan
    </div>

    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
        @forelse($cases as $case)
            <a href="{{ route('secretariat.cases.show', [$office, $case]) }}" class="block p-4 border-b last:border-0 border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong>{{ $case->title }}</strong>
                            <span class="font-mono text-xs rounded-lg bg-emerald-50 text-emerald-700 px-2 py-1">{{ $case->case_number }}</span>
                            @if(in_array($case->confidentiality, ['restricted', 'confidential'], true))
                                <span class="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-1"><i class="fa-solid fa-lock ml-1"></i>{{ $case->confidentiality }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 mt-2 truncate">{{ $case->summary ?: 'بدون خلاصه' }}</p>
                    </div>
                    <span class="text-sm rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 shrink-0">{{ $case->status }}</span>
                </div>
            </a>
        @empty
            <div class="p-10 text-center text-gray-500">هنوز پرونده‌ای که اجازه مشاهده آن را داشته باشید وجود ندارد.</div>
        @endforelse
    </div>
</div>
@endsection
