@extends('layouts.unified')

@section('title', 'شفافيت حساب مديران و بازرسان - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .reports-container {
        direction: rtl;
    }

    .reports-hero {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(59, 130, 246, 0.08));
        border: 1px solid #e2e8f0;
        border-radius: 1.5rem;
        padding: 1.75rem;
    }

    .hero-title {
        font-size: 2.1rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .hero-subtitle {
        font-size: 0.95rem;
        color: #64748b;
    }

    .hero-meta {
        font-size: 0.85rem;
        color: #94a3b8;
    }

    .filters-card {
        background: var(--color-pure-white);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }

    .leader-card {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        background: #ffffff;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }

    .leader-card:hover {
        border-color: #34d399;
        box-shadow: 0 12px 20px rgba(15, 23, 42, 0.08);
        transform: translateY(-2px);
    }

    .action-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem;
        border-radius: 0.9rem;
        font-weight: 600;
        font-size: 0.9rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .action-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .back-button {
        background: #0f172a;
        color: #ffffff;
    }

    .ghost-button {
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
    }
</style>
@endpush

@php
$groupLeaders = collect($groupLeaders ?? []);
$groupId = $groupId ?? null;
@endphp

@section('content')
<div class="bg-light-gray/60 py-8 md:py-10" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-4 md:px-6 max-w-7xl">
        <div class="reports-container">
            <div class="reports-hero mb-6">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="hero-title text-gentle-black mb-2">
                            <i class="fas fa-user-shield ml-3" style="color: var(--color-earth-green);"></i>
                            شفافيت حساب مديران و بازرسان
                        </div>
                        <p class="hero-subtitle">اعضاي گروه مي توانند گزارش حساب شخصي مديران و بازرسان منتخب را مشاهده کنند.</p>
                        @if(!empty($reportOwnerName))
                            <p class="hero-meta mt-3">{{ $reportOwnerName }}</p>
                        @endif
                        <p class="hero-meta mt-1">بازه پيش فرض گزارش: 3 ماه اخير</p>
                        <p class="hero-meta mt-1">محدوده دسترسي: از سه ماه قبل از شروع مسئوليت تا سه ماه بعد از پايان مسئوليت</p>
                        <p class="hero-meta mt-2">با پذيرش نقش مديريت يا بازرسي، گزارش گيري و مشاهده شفاف حساب شخصي براي اعضاي گروه مجاز است.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if($groupId)
                            <a href="{{ route('groups.show', ['group' => $groupId]) }}" class="action-button back-button">
                                <i class="fas fa-arrow-right"></i>
                                بازگشت به گروه
                            </a>
                            <a href="{{ route('groups.najm-bahar.reports', ['group' => $groupId]) }}" class="action-button ghost-button">
                                <i class="fas fa-chart-bar"></i>
                                گزارش هاي مالي
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="filters-card">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h3 class="text-lg font-bold text-gentle-black mb-1">
                            فهرست مديران و بازرسان منتخب
                        </h3>
                        <p class="text-sm text-slate-500">براي مشاهده گزارش، روي نام هر فرد کليک کنيد.</p>
                    </div>
                </div>
                @if($groupLeaders->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                        @foreach($groupLeaders as $leader)
                            <a href="{{ route('groups.najm-bahar.leader-reports', ['group' => $groupId, 'leader' => $leader['id']]) }}"
                               class="leader-card flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-800">{{ $leader['name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $leader['role_label'] }} | {{ $leader['account_number'] }}</div>
                                </div>
                                <i class="fas fa-chevron-left text-slate-400"></i>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500">مدير يا بازرس فعالي براي نمايش وجود ندارد.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
