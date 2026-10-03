@extends('layouts.unified')

@section('title', __('chronicle.title') . ' - ' . config('app.name', 'EarthCoop'))
@section('meta_description', __('chronicle.subtitle'))

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-10 px-4">
    <div class="max-w-5xl mx-auto space-y-8">
        <header class="text-center space-y-3">
            <p class="text-sm font-semibold tracking-wide text-emerald-600 dark:text-emerald-400">EarthCoop Chronicle</p>
            <h1 class="text-3xl md:text-5xl font-black text-slate-900 dark:text-white">{{ __('chronicle.title') }}</h1>
            <p class="text-slate-600 dark:text-slate-300 text-base md:text-lg">{{ __('chronicle.subtitle') }}</p>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-4" aria-labelledby="chronicle-today-heading">
            <h2 id="chronicle-today-heading" class="sr-only">{{ __('chronicle.today') }}</h2>

            <article class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('chronicle.jalali_date') }}</div>
                <div class="text-xl font-bold text-slate-900 dark:text-white">{{ $jalaliToday }}</div>
            </article>

            <article class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                <div class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('chronicle.gregorian_date') }}</div>
                <div class="text-xl font-bold text-slate-900 dark:text-white" dir="ltr">{{ $gregorianToday }}</div>
            </article>

            <article class="rounded-2xl bg-emerald-600 text-white p-6 shadow-sm">
                <div class="text-xs uppercase tracking-wider text-emerald-100 mb-2">{{ __('chronicle.today') }}</div>
                <div class="text-2xl font-black">
                    {{ $earthCoopYear !== null ? __('chronicle.earthcoop_year', ['year' => $earthCoopYear]) : '' }}
                </div>
            </article>
        </section>

        <section class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="space-y-3 max-w-2xl">
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white">{{ __('chronicle.epoch_title') }}</h2>
                    <p class="text-slate-600 dark:text-slate-300 leading-8">{{ __('chronicle.epoch_description') }}</p>
                    <p class="font-semibold text-emerald-700 dark:text-emerald-400">{{ __('chronicle.year_one') }}</p>
                </div>

                <div class="shrink-0 rounded-2xl bg-slate-100 dark:bg-slate-800 p-5 min-w-64 space-y-2 text-center">
                    <div class="font-bold text-slate-900 dark:text-white">{{ $epochJalali }}</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400" dir="ltr">{{ $epochGregorian }}</div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
