@extends('layouts.unified')

@section('title', __('navigation.footer_my_groups') . ' - ' . config('app.name', 'EarthCoop'))

<!-- Tailwind & Bootstrap CSS via Vite -->

@push('styles')
<style>
    /* ==================== Styles from my group.html ==================== */
    
    /* Main container layout */
    .main-container {
        display: flex;
        padding: 24px;
        gap: 32px;
        flex: 1;
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }

    /* Dashboard content (main content area) */
    .dashboard-content {
        flex-grow: 1;
        background-color: var(--color-pure-white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 2.5rem;
        min-height: calc(100vh - 170px);
        display: flex;
        flex-direction: column;
        border: 1px solid #e2e8f0;
        text-align: right;
        font-size: 1.1rem;
        color: var(--color-gentle-black);
    }

    body.dark-mode .dashboard-content {
        background-color: var(--card-dark);
        border-color: var(--border-dark);
    }

    /* Groups Section Styles */
    .groups-section h2 {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--color-dark-green);
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid var(--color-earth-green);
        position: relative;
        text-align: right;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .groups-section h2::after {
        content: '';
        position: absolute;
        bottom: -3px;
        right: 0;
        width: 80px;
        height: 3px;
        background: linear-gradient(90deg, var(--color-digital-gold), var(--color-accent-peach));
        border-radius: 2px;
    }

    /* Tab Buttons */
    .tab-buttons {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 30px;
        background-color: var(--color-light-gray);
        border-radius: 0.75rem;
        padding: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        flex-wrap: wrap;
        gap: 5px;
    }

    body.dark-mode .tab-buttons {
        background-color: var(--card-dark);
    }

    .tab-button {
        padding: 12px 20px;
        border: none;
        background-color: transparent;
        color: var(--color-gentle-black);
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        border-radius: 0.75rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        flex-grow: 1;
        text-align: center;
        min-width: 180px;
    }

    body.dark-mode .tab-button {
        color: var(--text-dark);
    }

    .tab-button:hover {
        background-color: #eef2f6;
        color: var(--color-earth-green);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
    }

    .tab-button.active {
        background: linear-gradient(45deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: var(--color-pure-white);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
    }

    /* Tab Content */
    .tab-content {
        background-color: var(--color-pure-white);
        padding: 25px;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        min-height: 300px;
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    body.dark-mode .tab-content {
        background-color: var(--card-dark);
        border-color: var(--border-dark);
    }

    /* Sub-tab Buttons */
    .sub-tab-buttons {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 20px;
        gap: 10px;
        flex-wrap: wrap;
    }

    .sub-tab-button {
        padding: 10px 18px;
        border: none;
        background-color: #f1f5f9;
        color: var(--color-gentle-black);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }

    body.dark-mode .sub-tab-button {
        background-color: #2a2a2a;
        color: var(--text-dark);
    }

    .sub-tab-button:hover {
        background-color: var(--color-earth-green);
        color: white;
        transform: translateY(-2px);
    }

    .sub-tab-button.active {
        background: linear-gradient(45deg, var(--color-ocean-blue), var(--color-dark-blue));
        color: white;
    }

    /* Filter Buttons */
    .filter-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 20px;
        justify-content: flex-end;
    }

    .filter-button {
        padding: 8px 16px;
        border: 1px solid #e5e7eb;
        background-color: white;
        color: var(--color-gentle-black);
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }

    body.dark-mode .filter-button {
        background-color: var(--card-dark);
        border-color: var(--border-dark);
        color: var(--text-dark);
    }

    .filter-button:hover {
        background-color: var(--color-digital-gold);
        color: white;
        border-color: var(--color-digital-gold);
    }

    .filter-button.active {
        background-color: var(--color-earth-green);
        color: white;
        border-color: var(--color-earth-green);
    }

    /* Data Table */
    .data-table-container {
        overflow-x: auto;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }

    .data-table thead {
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-dark-green));
        color: white;
    }

    body.dark-mode .data-table thead {
        background: linear-gradient(135deg, #047857, #065f46);
    }

    .data-table th {
        padding: 14px 16px;
        text-align: center;
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table td {
        padding: 12px 16px;
        text-align: center;
        border-bottom: 1px solid #e5e7eb;
    }

    body.dark-mode .data-table td {
        border-bottom-color: var(--border-dark);
    }

    .data-table tbody tr {
        transition: all 0.2s ease;
    }

    .data-table tbody tr:hover {
        background-color: #f9fafb;
        transform: scale(1.01);
    }

    body.dark-mode .data-table tbody tr:hover {
        background-color: var(--hover-dark);
    }

    /* Table Icons */
    .table-icon {
        color: var(--color-earth-green);
        font-size: 1.1rem;
    }

    /* Status Badges */
    .status-badge {
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }

    .status-badge.active-status {
        background-color: #dcfce7;
        color: #166534;
    }

    .status-badge.pending {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-badge.inactive {
        background-color: #fee2e2;
        color: #991b1b;
    }

    /* Filter hidden rows */
    .data-table tbody tr.filter-hidden,
    .data-table tbody tr[style*="display: none"] {
        display: none !important;
    }

    /* Sidebar Styles */
    .sidebar {
        width: 320px;
        background-color: var(--color-pure-white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
        flex-shrink: 0;
        position: sticky;
        top: 100px;
        height: fit-content;
        border: 1px solid #e2e8f0;
    }
    
    .sidebar-header {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        color: var(--color-gentle-black);
        font-size: 1.5rem;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
    }

    .sidebar-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .sidebar-chevron {
        display: none;
        transition: all 0.2s ease;
    }

    .sidebar.mobile-open .sidebar-chevron {
        transform: rotate(180deg);
    }

    .sidebar-menu-item {
        margin-bottom: 0.5rem;
    }

    .sidebar-menu-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        color: var(--color-gentle-black);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
    }

    body.dark-mode .sidebar-menu-link {
        color: var(--text-dark);
    }

    .sidebar-menu-link:hover {
        background-color: var(--color-light-gray);
        color: var(--color-earth-green);
        transform: translateX(-5px);
    }

    body.dark-mode .sidebar-menu-link:hover {
        background-color: var(--hover-dark);
    }

    .sidebar-menu-link.active {
        background-color: #dcfce7;
        color: var(--color-dark-green);
        font-weight: 600;
    }

    .sidebar-menu-link .badge {
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 9999px;
    }

    /* Accordion (mobile) */
    .accordion-tabs {
        display: none;
        flex-direction: column;
        gap: 0.875rem;
    }

    .accordion-item {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background-color: var(--color-pure-white);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        overflow: hidden;
        transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
    }

    .accordion-item.is-open {
        border-color: rgba(16, 185, 129, 0.45);
        box-shadow: 0 14px 32px rgba(16, 185, 129, 0.12);
    }

    .accordion-header {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        color: var(--color-gentle-black);
        border: none;
        padding: 0.9rem 1rem;
        border-radius: 1rem;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: color 0.25s ease, background 0.25s ease;
    }

    .accordion-item.is-open .accordion-header {
        color: var(--color-dark-green);
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.13), rgba(59, 130, 246, 0.06));
        border-radius: 1rem 1rem 0 0;
    }

    .accordion-label {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-align: right;
    }

    .accordion-label-icon {
        display: grid;
        place-items: center;
        width: 2.5rem;
        height: 2.5rem;
        flex: 0 0 auto;
        border-radius: 0.8rem;
        color: var(--color-earth-green);
        background: rgba(16, 185, 129, 0.12);
    }

    .accordion-header > .toggle-icon {
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        color: #64748b;
        background: #f1f5f9;
    }

    .accordion-content {
        display: none;
        padding: 1rem;
        border-top: 1px solid rgba(16, 185, 129, 0.16);
        background: #ffffff;
    }

    .accordion-content.active {
        display: block;
    }

    .toggle-icon {
        transition: transform 0.25s ease;
    }

    .toggle-icon.rotate {
        transform: rotate(180deg);
    }

    /* Inner toggle sections */
    .toggle-group {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .toggle-header {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background-color: #f8fafc;
        padding: 14px 18px;
        border: none;
        font-weight: 600;
        color: var(--color-gentle-black);
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .toggle-header:hover {
        background-color: #edf2fb;
    }

    .toggle-content {
        display: none;
        padding: 16px 18px;
        background-color: var(--color-pure-white);
    }

    .toggle-content.open {
        display: block;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .groups-page-shell {
            padding: 0.75rem 1.5rem 1.5rem;
        }

        .groups-page-layout {
            gap: 1.25rem;
        }

        .main-container {
            flex-direction: column;
        }

        .sidebar {
            width: 100%;
            position: relative;
            top: 0;
            padding: 0;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1);
        }

        .sidebar-header {
            min-height: 3.5rem;
            padding: 0.75rem 1rem;
            margin: 0;
            border-bottom: 0;
            font-size: 1.25rem;
            cursor: pointer;
        }

        .sidebar.mobile-open .sidebar-header {
            border-bottom: 1px solid #e2e8f0;
        }

        .sidebar-chevron {
            display: inline-block;
        }

        .sidebar-content {
            display: none;
            padding: 0.75rem;
        }

        .sidebar.mobile-open .sidebar-content {
            display: block;
        }

        .tab-buttons {
            flex-direction: column;
        }

        .tab-button {
            min-width: 100%;
        }

        .tab-buttons {
            display: none;
        }

        .desktop-tabs {
            display: none;
        }

        .accordion-tabs {
            display: flex;
        }
    }
</style>
@endpush

@section('content')
@php
    $generalTable = view('groups.partials.table-basic', [
        'groups' => $generalGroups ?? collect(),
        'icon' => 'fas fa-users',
        'emptyMessage' => 'هیچ گروهی یافت نشد',
        'type' => 'general'
    ])->render();

    $specialtyFilters = [
        'all' => 'همه گروه‌های شما',
        'global' => 'جهانی',
        'continent' => 'قاره',
        'country' => 'کشور',
        'province' => 'استان',
        'county' => 'شهرستان',
        'section' => 'بخش',
        'city' => 'شهر / دهستان',
        'region' => 'منطقه / روستا',
        'neighborhood' => 'محله',
    ];

    $specialtyJobTable = view('groups.partials.table-basic', [
        'groups' => $specialityGroups ?? collect(),
        'icon' => 'fas fa-briefcase',
        'emptyMessage' => 'هیچ گروه شغلی یافت نشد',
        'filters' => $specialtyFilters,
        'tableId' => 'specialty_job_table',
        'levelKey' => 'location_level',
        'type' => 'specialty'
    ])->render();

    $specialtyExperienceTable = view('groups.partials.table-basic', [
        'groups' => $experienceGroups ?? collect(),
        'icon' => 'fas fa-graduation-cap',
        'emptyMessage' => 'هیچ گروه تخصصی و تجربی یافت نشد',
        'filters' => $specialtyFilters,
        'tableId' => 'specialty_experience_table',
        'levelKey' => 'location_level',
        'type' => 'specialty'
    ])->render();

    $ageGroupsTable = view('groups.partials.table-basic', [
        'groups' => $ageGroups ?? collect(),
        'icon' => 'fas fa-user-clock',
        'emptyMessage' => 'هیچ گروه سنی یافت نشد',
        'type' => 'exclusive'
    ])->render();

    $genderGroupsTable = view('groups.partials.table-basic', [
        'groups' => $genderGroups ?? collect(),
        'icon' => 'fas fa-venus-mars',
        'emptyMessage' => 'هیچ گروه جنسیتی یافت نشد',
        'type' => 'exclusive'
    ])->render();

    $managedTable = '';
    if (isset($managedGroups) && $managedGroups->count() > 0) {
        $managedTable = view('groups.partials.table-managed', [
            'groups' => $managedGroups,
            'type' => 'managed'
        ])->render();
    }

    $totalGroups = ($generalGroups ?? collect())->count()
        + ($specialityGroups ?? collect())->count()
        + ($experienceGroups ?? collect())->count()
        + ($ageGroups ?? collect())->count()
        + ($genderGroups ?? collect())->count();
@endphp

<div class="groups-page-shell container mx-auto px-4 py-6">
    <div class="groups-page-layout flex flex-col lg:flex-row gap-8">
        {{-- Sidebar --}}
        <aside class="sidebar" id="groups-sidebar">
            <button id="sidebar-mobile-toggle" class="sidebar-header" type="button" aria-controls="groups-sidebar-content" aria-expanded="false" aria-label="باز و بسته کردن منو">
                <span class="sidebar-title"><i class="fas fa-bars" style="color: var(--color-earth-green);"></i><span>منو</span></span>
                <i class="sidebar-chevron fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="sidebar-content" id="groups-sidebar-content">
            <nav>
                <ul class="space-y-2">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('groups.index') }}" class="sidebar-menu-link active">
                            <i class="fas fa-users text-ocean-blue"></i>
                            <span class="flex-grow text-right mx-3">{{ __('navigation.footer_my_groups') }}</span>
                            @if($totalGroups > 0)
                                <span class="badge bg-digital-gold text-white">{{ $totalGroups }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('history.index') }}" class="sidebar-menu-link">
                            <i class="fas fa-handshake text-digital-gold"></i>
                            <span class="flex-grow text-right mx-3">مشارکت‌های من</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('history.election') }}" class="sidebar-menu-link">
                            <i class="fas fa-vote-yea text-earth-green"></i>
                            <span class="flex-grow text-right mx-3">انتخابات جاری</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('history.poll') }}" class="sidebar-menu-link">
                            <i class="fas fa-chart-pie text-ocean-blue"></i>
                            <span class="flex-grow text-right mx-3">نظرسنجی‌های جاری</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        @php
                            $accountService = app(\App\Modules\NajmBahar\Services\AccountService::class);
                            $needsNajmAgreement = !$accountService->hasMainAccount(auth()->id());
                        @endphp
                        <a href="{{ route('najm-bahar.agreement') }}" class="sidebar-menu-link {{ $needsNajmAgreement ? 'blinking-item' : '' }}">
                            <i class="fas fa-wallet text-digital-gold"></i>
                            <span class="flex-grow text-right mx-3">حساب مالی نجم بهار</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('my-invation-code') }}" class="sidebar-menu-link">
                            <i class="fas fa-user-plus text-earth-green"></i>
                            <span class="flex-grow text-right mx-3">دعوت از دوستان</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('profile.edit') }}" class="sidebar-menu-link">
                            <i class="fas fa-cog text-ocean-blue"></i>
                            <span class="flex-grow text-right mx-3">ویرایش حساب کاربری</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('user.tickets.index') }}" class="sidebar-menu-link">
                            <i class="fas fa-headset text-ocean-blue"></i>
                            <span class="flex-grow text-right mx-3">پشتیبانی</span>
                            @php
                                $openTicketsCount = \App\Models\Ticket::where(function($q) {
                                    $q->where('user_id', auth()->id())
                                      ->orWhere('email', auth()->user()->email);
                                })->whereIn('status', ['open', 'in-progress'])->count();
                            @endphp
                            @if($openTicketsCount > 0)
                            <span class="badge text-white text-xs px-2 py-1 rounded-full font-bold" style="background-color: var(--color-red-tomato);">{{ $openTicketsCount }}</span>
                            @endif
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 text-center text-sm text-gray-500">
                نسخه ۲.۱.۰ - EarthCoop
            </div>
            </div>
        </aside>

        {{-- Main Content --}}
        <main class="dashboard-content">
            <div class="groups-section">
                <h2>{{ __('navigation.footer_my_groups') }}</h2>

                {{-- Main Tabs (desktop) --}}
                <div class="tab-buttons">
                    <button class="tab-button active" data-target="public">
                        گروه‌های مجمع عمومی
                    </button>
                    <button class="tab-button" data-target="specialty">
                        گروه‌های تخصصی
                    </button>
                    <button class="tab-button" data-target="exclusive">
                        گروه‌های اختصاصی
                    </button>
                    @if(!empty($managedTable))
                        <button class="tab-button" data-target="managed">
                            گروه‌های مدیریتی
                        </button>
                    @endif
                </div>

                {{-- Accordion (mobile) --}}
                <div class="accordion-tabs">
                    <div class="accordion-item">
                        <button class="accordion-header" data-target="accordion-public" aria-expanded="false">
                            <span class="accordion-label"><span class="accordion-label-icon"><i class="fas fa-users"></i></span><span>گروه‌های مجمع عمومی</span></span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </button>
                        <div class="accordion-content" id="accordion-public">
                            {!! $generalTable !!}
                        </div>
                    </div>

                    <div class="accordion-item">
                        <button class="accordion-header" data-target="accordion-specialty" aria-expanded="false">
                            <span class="accordion-label"><span class="accordion-label-icon"><i class="fas fa-briefcase"></i></span><span>گروه‌های تخصصی</span></span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </button>
                        <div class="accordion-content" id="accordion-specialty">
                            <div class="toggle-group">
                                <button class="toggle-header" data-target="job-groups-mobile">
                                    گروه‌های شغلی و صنفی شما
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </button>
                                <div id="job-groups-mobile" class="toggle-content">
                                    {!! $specialtyJobTable !!}
                                </div>
                            </div>
                            <div class="toggle-group">
                                <button class="toggle-header" data-target="experience-groups-mobile">
                                    گروه‌های تخصصی و تجربی شما
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </button>
                                <div id="experience-groups-mobile" class="toggle-content">
                                    {!! $specialtyExperienceTable !!}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <button class="accordion-header" data-target="accordion-exclusive" aria-expanded="false">
                            <span class="accordion-label"><span class="accordion-label-icon"><i class="fas fa-star"></i></span><span>گروه‌های اختصاصی</span></span>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </button>
                        <div class="accordion-content" id="accordion-exclusive">
                            <div class="toggle-group">
                                <button class="toggle-header" data-target="age-groups-mobile">
                                    گروه‌های سنی شما
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </button>
                                <div id="age-groups-mobile" class="toggle-content">
                                    {!! $ageGroupsTable !!}
                                </div>
                            </div>
                            <div class="toggle-group">
                                <button class="toggle-header" data-target="gender-groups-mobile">
                                    گروه‌های جنسیتی شما
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                </button>
                                <div id="gender-groups-mobile" class="toggle-content">
                                    {!! $genderGroupsTable !!}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(!empty($managedTable))
                        <div class="accordion-item">
                            <button class="accordion-header" data-target="accordion-managed" aria-expanded="false">
                                <span class="accordion-label"><span class="accordion-label-icon"><i class="fas fa-user-shield"></i></span><span>گروه‌های مدیریتی</span></span>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div class="accordion-content" id="accordion-managed">
                                {!! $managedTable !!}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Desktop Tab Contents --}}
                <div class="desktop-tabs">
                    <div id="public" class="tab-content active">
                        <h3 class="text-xl font-bold mb-4 text-earth-green">گروه‌های مجمع عمومی</h3>
                        {!! $generalTable !!}
                    </div>

                    <div id="specialty" class="tab-content">
                        <h3 class="text-xl font-bold mb-4 text-earth-green">گروه‌های تخصصی</h3>
                        <div class="toggle-group">
                            <button class="toggle-header" data-target="job-groups-desktop">
                                گروه‌های شغلی و صنفی شما
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div id="job-groups-desktop" class="toggle-content">
                                {!! $specialtyJobTable !!}
                            </div>
                        </div>
                        <div class="toggle-group">
                            <button class="toggle-header" data-target="experience-groups-desktop">
                                گروه‌های تخصصی و تجربی شما
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div id="experience-groups-desktop" class="toggle-content">
                                {!! $specialtyExperienceTable !!}
                            </div>
                        </div>
                    </div>

                    <div id="exclusive" class="tab-content">
                        <h3 class="text-xl font-bold mb-4 text-earth-green">گروه‌های اختصاصی</h3>
                        <div class="toggle-group">
                            <button class="toggle-header" data-target="age-groups-desktop">
                                گروه‌های سنی شما
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div id="age-groups-desktop" class="toggle-content">
                                {!! $ageGroupsTable !!}
                            </div>
                        </div>
                        <div class="toggle-group">
                            <button class="toggle-header" data-target="gender-groups-desktop">
                                گروه‌های جنسیتی شما
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div id="gender-groups-desktop" class="toggle-content">
                                {!! $genderGroupsTable !!}
                            </div>
                        </div>
                    </div>

                    @if(!empty($managedTable))
                        <div id="managed" class="tab-content">
                            <h3 class="text-xl font-bold mb-4 text-earth-green">گروه‌های مدیریتی</h3>
                            {!! $managedTable !!}
                        </div>
                    @endif
                </div>
            </div>
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('groups-sidebar');
        const sidebarToggle = document.getElementById('sidebar-mobile-toggle');
        const sidebarContent = document.getElementById('groups-sidebar-content');
        const mobileSidebarQuery = window.matchMedia('(max-width: 1024px)');

        function setSidebarOpenState(isOpen) {
            if (!sidebar || !sidebarToggle || !sidebarContent) return;

            sidebar.classList.toggle('mobile-open', isOpen);
            sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            sidebarContent.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        }

        function syncSidebarForViewport() {
            if (!sidebar || !sidebarToggle || !sidebarContent) return;

            if (mobileSidebarQuery.matches) {
                setSidebarOpenState(false);
            } else {
                sidebar.classList.remove('mobile-open');
                sidebarToggle.setAttribute('aria-expanded', 'true');
                sidebarContent.setAttribute('aria-hidden', 'false');
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                if (!mobileSidebarQuery.matches || !sidebar) return;
                const isOpen = sidebar.classList.contains('mobile-open');
                setSidebarOpenState(!isOpen);
            });
        }

        if (sidebarContent) {
            sidebarContent.querySelectorAll('.sidebar-menu-link').forEach(link => {
                link.addEventListener('click', function () {
                    if (mobileSidebarQuery.matches) {
                        setSidebarOpenState(false);
                    }
                });
            });
        }

        if (typeof mobileSidebarQuery.addEventListener === 'function') {
            mobileSidebarQuery.addEventListener('change', syncSidebarForViewport);
        } else if (typeof mobileSidebarQuery.addListener === 'function') {
            mobileSidebarQuery.addListener(syncSidebarForViewport);
        }

        syncSidebarForViewport();

        // Desktop tabs
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.desktop-tabs .tab-content');

        if (tabButtons.length === 0) {
            console.warn('No tab buttons found');
        } else {
            console.log('Found', tabButtons.length, 'tab buttons');
        }

        tabButtons.forEach(button => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                const targetId = this.dataset.target;
                console.log('Tab clicked:', targetId);

                if (!targetId) {
                    console.warn('Tab button has no data-target attribute');
                    return;
                }

                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                this.classList.add('active');

                const targetContent = document.getElementById(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                    console.log('Tab content activated:', targetId);

                    // When tab changes, initialize filters for open toggles inside this tab
                    setTimeout(() => {
                        const openToggles = targetContent.querySelectorAll('.toggle-content.open');
                        openToggles.forEach(toggle => {
                            const filterContainer = toggle.querySelector('.filter-buttons');
                            if (filterContainer) {
                                const containerId = filterContainer.dataset.target || filterContainer.id || 'unknown';
                                if (!initializedFilters.has(containerId)) {
                                    initializeFilters(filterContainer);
                                    initializedFilters.add(containerId);
                                }
                            }
                        });
                    }, 100);
                } else {
                    console.warn('Tab content not found for ID:', targetId);
                }
            });
        });

        // Mobile accordion
        const accordionHeaders = document.querySelectorAll('.accordion-header');
        accordionHeaders.forEach(header => {
            header.addEventListener('click', function () {
                const targetId = this.dataset.target;
                const content = document.getElementById(targetId);
                const icon = this.querySelector('.toggle-icon');
                if (!content) return;
                const isActive = content.classList.contains('active');

                accordionHeaders.forEach(otherHeader => {
                    if (otherHeader === this) return;
                    const otherContent = document.getElementById(otherHeader.dataset.target);
                    otherContent?.classList.remove('active');
                    otherHeader.querySelector('.toggle-icon')?.classList.remove('rotate');
                    otherHeader.setAttribute('aria-expanded', 'false');
                    otherHeader.closest('.accordion-item')?.classList.remove('is-open');
                });

                content.classList.toggle('active', !isActive);
                icon?.classList.toggle('rotate', !isActive);
                this.setAttribute('aria-expanded', String(!isActive));
                this.closest('.accordion-item')?.classList.toggle('is-open', !isActive);

                // Initialize filters when accordion opens
                if (!isActive) {
                    setTimeout(() => {
                        const openToggles = content.querySelectorAll('.toggle-content.open');
                        openToggles.forEach(toggle => {
                            const filterContainer = toggle.querySelector('.filter-buttons');
                            if (filterContainer) {
                                const containerId = filterContainer.dataset.target || filterContainer.id || 'unknown';
                                if (!initializedFilters.has(containerId)) {
                                    initializeFilters(filterContainer);
                                    initializedFilters.add(containerId);
                                }
                            }
                        });
                        // Also initialize filters directly in accordion-content (not in toggle-content)
                        const directFilters = content.querySelectorAll('.filter-buttons');
                        directFilters.forEach(container => {
                            const containerId = container.dataset.target || container.id || 'unknown';
                            if (!initializedFilters.has(containerId)) {
                                initializeFilters(container);
                                initializedFilters.add(containerId);
                            }
                        });
                    }, 100);
                }
            });
        });

        // Open the group category requested by dashboard cards.
        const requestedTab = new URLSearchParams(window.location.search).get('tab');
        const allowedTabs = ['public', 'specialty', 'exclusive', 'managed'];
        if (requestedTab && allowedTabs.includes(requestedTab)) {
            const desktopButton = document.querySelector(`.tab-button[data-target="${requestedTab}"]`);
            desktopButton?.click();

            const mobileHeader = document.querySelector(`.accordion-header[data-target="accordion-${requestedTab}"]`);
            const mobileContent = document.getElementById(`accordion-${requestedTab}`);
            if (mobileHeader && mobileContent && !mobileContent.classList.contains('active')) {
                mobileHeader.click();
            }
        }

        // Filters for specialty tables
        function initializeFilters(container) {
            if (!container || !container.isConnected) {
                return;
            }

            const targetId = container.dataset.target;
            if (!targetId) {
                console.warn('Filter: No target ID found');
                return;
            }

            const table = document.getElementById(targetId);
            if (!table) {
                console.warn('Filter: Table not found with ID:', targetId);
                return;
            }

            const rows = table.querySelectorAll('tbody tr[data-filter-value]');
            const buttons = container.querySelectorAll('.filter-button');

            if (rows.length === 0) {
                console.warn('Filter: No rows with data-filter-value found in table:', targetId, 'Rows in table:', table.querySelectorAll('tbody tr').length);
                return;
            }
            if (buttons.length === 0) {
                console.warn('Filter: No filter buttons found for table:', targetId);
                return;
            }

            // Remove any existing listeners by cloning buttons
            buttons.forEach(button => {
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
            });

            const newButtons = container.querySelectorAll('.filter-button');

            newButtons.forEach(button => {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const filter = this.dataset.filter;
                    console.log('Filter button clicked:', filter, 'for table:', targetId);

                    newButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Re-query rows - always query, even if parent toggle-content might be closed
                    const currentRows = table.querySelectorAll('tbody tr[data-filter-value]');
                    console.log('Filtering', currentRows.length, 'rows with filter:', filter);

                    let visibleCount = 0;
                    let hiddenCount = 0;

                    currentRows.forEach((row, index) => {
                        const value = (row.getAttribute('data-filter-value') || row.dataset.filterValue || 'all').trim();
                        const shouldShow = (filter === 'all' || value === filter);

                        if (index < 5) {
                            console.log(`Row ${index}: data-filter-value="${value}", shouldShow=${shouldShow}, filter="${filter}", match=${value === filter}`);
                        }

                        if (shouldShow) {
                            row.style.display = '';
                            row.style.visibility = 'visible';
                            row.hidden = false;
                            row.classList.remove('filter-hidden');
                            visibleCount++;
                        } else {
                            row.style.setProperty('display', 'none', 'important');
                            row.style.visibility = 'hidden';
                            row.hidden = true;
                            row.classList.add('filter-hidden');
                            hiddenCount++;
                        }
                    });

                    console.log('Filter result - Visible:', visibleCount, 'Hidden:', hiddenCount, 'Total:', currentRows.length);

                    // Also check the empty message row
                    const emptyRow = table.querySelector('tbody tr:not([data-filter-value])');
                    if (emptyRow) {
                        emptyRow.style.display = visibleCount > 0 ? 'none' : '';
                    }
                });
            });

            // Activate default button
            const defaultButton = container.querySelector('.filter-button.active') || newButtons[0];
            if (defaultButton) {
                const filter = defaultButton.dataset.filter;
                const currentRows = table.querySelectorAll('tbody tr[data-filter-value]');
                currentRows.forEach(row => {
                    const value = (row.getAttribute('data-filter-value') || row.dataset.filterValue || 'all').trim();
                    const shouldShow = (filter === 'all' || value === filter);
                    if (shouldShow) {
                        row.style.setProperty('display', '', 'important');
                        row.classList.remove('filter-hidden');
                    } else {
                        row.style.setProperty('display', 'none', 'important');
                        row.classList.add('filter-hidden');
                    }
                });
            }

            console.log('Filters initialized for table:', targetId, 'with', rows.length, 'rows and', newButtons.length, 'buttons');
        }

        // Inner toggle sections
        const toggleHeaders = document.querySelectorAll('.toggle-header');
        toggleHeaders.forEach(header => {
            header.addEventListener('click', function (event) {
                event.stopPropagation();
                const targetId = this.dataset.target;
                const content = document.getElementById(targetId);
                const icon = this.querySelector('.toggle-icon');
                if (!content) return;
                const isOpen = content.classList.contains('open');
                content.classList.toggle('open', !isOpen);
                icon?.classList.toggle('rotate', !isOpen);

                // Re-initialize filters when toggle sections open
                if (!isOpen) { // Only when opening
                    setTimeout(() => {
                        // Find filter container inside this content
                        const filterContainer = content.querySelector('.filter-buttons');
                        if (filterContainer) {
                            const containerId = filterContainer.dataset.target || filterContainer.id || 'unknown';
                            // Get the currently active filter button before re-initializing
                            const activeButton = filterContainer.querySelector('.filter-button.active');
                            const activeFilter = activeButton ? activeButton.dataset.filter : 'all';

                            // Reset initialization flag so it can be re-initialized
                            initializedFilters.delete(containerId);
                            initializeFilters(filterContainer);
                            initializedFilters.add(containerId);

                            // Re-apply the active filter after initialization
                            if (activeFilter) {
                                setTimeout(() => {
                                    const button = filterContainer.querySelector(`.filter-button[data-filter="${activeFilter}"]`);
                                    if (button) {
                                        // Manually trigger filter instead of click to avoid event issues
                                        const table = document.getElementById(filterContainer.dataset.target);
                                        if (table) {
                                            const rows = table.querySelectorAll('tbody tr[data-filter-value]');
                                            rows.forEach(row => {
                                                const value = (row.getAttribute('data-filter-value') || row.dataset.filterValue || 'all').trim();
                                                const shouldShow = (activeFilter === 'all' || value === activeFilter);
                                                if (shouldShow) {
                                                    row.style.setProperty('display', '', 'important');
                                                    row.classList.remove('filter-hidden');
                                                } else {
                                                    row.style.setProperty('display', 'none', 'important');
                                                    row.classList.add('filter-hidden');
                                                }
                                            });
                                            // Update button states
                                            filterContainer.querySelectorAll('.filter-button').forEach(btn => {
                                                btn.classList.remove('active');
                                                if (btn.dataset.filter === activeFilter) {
                                                    btn.classList.add('active');
                                                }
                                            });
                                        }
                                    }
                                }, 100);
                            }
                        }
                    }, 200);
                }
            });
        });

        // Track which filter containers have been initialized
        const initializedFilters = new Set();

        // Initialize filters for visible containers only
        function initializeVisibleFilters() {
            const filterContainers = document.querySelectorAll('.filter-buttons');
            filterContainers.forEach(container => {
                const containerId = container.dataset.target || container.id || 'unknown';

                // Skip if already initialized
                if (initializedFilters.has(containerId)) {
                    return;
                }

                // Check if container is inside a visible element
                const toggleContent = container.closest('.toggle-content');
                const isInOpenToggle = toggleContent && toggleContent.classList.contains('open');
                const isInActiveAccordion = container.closest('.accordion-content.active');
                const isNotInToggle = !toggleContent;

                // For desktop: only initialize if toggle-content is open OR not in toggle-content at all
                // For mobile: initialize if accordion-content is active
                // Important: Don't initialize if toggle-content exists but is closed
                if ((isInOpenToggle || isNotInToggle) || isInActiveAccordion) {
                    initializeFilters(container);
                    initializedFilters.add(containerId);
                }
            });
        }

        // Don't initialize filters on page load - wait for toggles to open
        // initializeVisibleFilters(); // Commented out - filters will be initialized when toggles open
    });
</script>
@endpush
