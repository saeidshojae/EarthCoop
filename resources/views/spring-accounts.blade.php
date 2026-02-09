@extends('layouts.unified')

@section('title', 'حساب نجم بهار - ' . config('app.name', 'EarthCoop'))
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    /* Finance Section Styles */
    .finance-section { 
        width: 100%;
        background-color: var(--color-pure-white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 2.5rem;
        margin-top: 0;
        border: 1px solid #e2e8f0;
        animation: fadeIn 0.8s ease-out;
    }

    .finance-title { 
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--color-dark-green);
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid var(--color-earth-green);
        position: relative;
        text-align: right;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        font-family: var(--font-vazirmatn);
    }

    .finance-title::after { 
        content: '';
        position: absolute;
        bottom: -3px;
        right: 0;
        width: 80px;
        height: 3px;
        background: linear-gradient(90deg, var(--color-digital-gold), var(--color-accent-peach));
        border-radius: 2px;
    }

    .info-label-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 20px;
        justify-content: flex-end;
    }

    .info-card {
        background-color: var(--color-light-gray);
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 20px;
        flex: 1 1 calc(50% - 20px);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 120px;
        text-align: right;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .info-card h4 {
        font-size: 1.1rem;
        color: var(--color-gentle-black);
        margin-bottom: 8px;
        font-family: var(--font-vazirmatn);
    }

    .info-card .value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--color-earth-green);
        margin-bottom: 5px;
        font-family: var(--font-vazirmatn);
    }

    .info-card .description {
        font-size: 0.85rem;
        color: var(--color-gentle-black);
        font-family: var(--font-vazirmatn);
    }

    @media (max-width: 768px) {
        .info-card {
            flex: 1 1 100%;
        }
    }

    .account-section, .transactions-section {
        background-color: var(--color-pure-white);
        border-radius: 0.75rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        padding: 25px;
        margin-top: 30px;
        border: 1px solid #e2e8f0;
    }

    .account-header, .transactions-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .account-header h3, .transactions-header h3 {
        font-size: 1.4rem;
        color: var(--color-dark-green);
        text-align: right;
        margin-left: auto;
        margin-right: 0;
        font-family: var(--font-vazirmatn);
    }

    .create-account-button {
        background: linear-gradient(45deg, var(--color-earth-green) 0%, var(--color-dark-green) 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 0.75rem;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: 0;
        margin-right: auto;
        font-family: var(--font-vazirmatn);
    }

    .create-account-button:hover:not(:disabled) {
        background: linear-gradient(45deg, var(--color-dark-green) 0%, #03664d 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .create-account-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 20px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border-radius: 0.75rem;
    }

    .data-table th, .data-table td {
        padding: 15px 20px;
        text-align: center;
        border-bottom: 1px solid #e2e8f0;
        border-left: 1px solid #e2e8f0;
        font-family: var(--font-vazirmatn);
    }

    .data-table th {
        background: linear-gradient(180deg, var(--color-light-gray) 0%, #f1f5f9 100%);
        color: var(--color-dark-green);
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table th:first-child {
        border-top-right-radius: 0.75rem;
    }

    .data-table th:last-child {
        border-top-left-radius: 0.75rem;
        border-left: none;
    }

    .data-table tbody tr:nth-child(even) {
        background-color: var(--color-light-gray);
    }

    .data-table tbody tr:hover {
        background-color: var(--color-earth-green);
        color: var(--color-pure-white);
        transform: scale(1.01);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .data-table td {
        color: var(--color-gentle-black);
        font-size: 0.9rem;
        transition: all 0.15s ease-out;
    }
    
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .data-table tbody tr:last-child td:first-child {
        border-bottom-right-radius: 0.75rem;
    }
    .data-table tbody tr:last-child td:last-child {
        border-bottom-left-radius: 0.75rem;
        border-left: none;
    }

    .action-button {
        background: linear-gradient(45deg, var(--color-ocean-blue) 0%, var(--color-dark-blue) 100%);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 0.75rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        font-family: var(--font-vazirmatn);
    }

    .action-button:hover {
        background: linear-gradient(45deg, var(--color-dark-blue) 0%, #0c3b9b 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .action-button:active {
        transform: translateY(1px);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .warning-banner {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-right: 4px solid var(--color-digital-gold);
        border-radius: 0.75rem;
        padding: 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .warning-banner i {
        font-size: 1.5rem;
        color: var(--color-digital-gold);
        flex-shrink: 0;
    }

    .warning-banner p {
        margin: 0;
        color: var(--color-gentle-black);
        font-weight: 600;
        font-family: var(--font-vazirmatn);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--color-gentle-black);
    }

    .empty-state i {
        font-size: 3rem;
        color: #cbd5e1;
        margin-bottom: 15px;
    }

    .empty-state p {
        font-size: 1rem;
        margin-bottom: 15px;
        font-family: var(--font-vazirmatn);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1024px) {
        .finance-title {
            font-size: 1.8rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        .finance-title::after {
            width: 60px;
            height: 2px;
        }
        .info-card {
            flex: 1 1 calc(100% - 20px);
        }
        .account-header h3, .transactions-header h3 {
            font-size: 1.2rem;
        }
        .create-account-button {
            padding: 8px 16px;
            font-size: 0.8rem;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            font-size: 0.85rem;
        }
        .action-button {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
    }

    @media (max-width: 768px) {
        .finance-title {
            font-size: 1.6rem;
        }
        .info-card {
            flex: 1 1 100%;
        }
        .account-header h3, .transactions-header h3 {
            font-size: 1.1rem;
        }
        .create-account-button {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
        .data-table {
            overflow-x: auto;
            display: block;
            width: 100%;
        }
        .data-table thead {
            display: table-header-group;
        }
        .data-table tbody {
            display: table-row-group;
        }
        .data-table th, .data-table td {
            white-space: nowrap;
            padding: 10px 12px;
            font-size: 0.8rem;
            text-align: center;
        }
        .data-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table tbody tr:last-child {
            border-bottom: none;
        }
        .action-button {
            padding: 5px 10px;
            font-size: 0.7rem;
        }
    }
</style>
@endpush

@section('content')
@php
    $userCount = \App\Models\User::where('id', '!=', 1)->count();
    $totalAmount = \App\Models\Spring::where('status', 1)->sum('amount');
    $userAccounts = \App\Models\Spring::where('user_id', auth()->user()->id)->get();
    $userSpring = \App\Models\Spring::where('user_id', auth()->user()->id)->first();
    
    // Get transactions
    $transactions = collect();
    if ($userSpring) {
        $transactions = \App\Models\Transaction::where(function($query) use ($userSpring) {
            $query->where('from_account_id', $userSpring->id)
                  ->orWhere('to_account_id', $userSpring->id);
        })->orderBy('created_at', 'desc')->get();
    }
@endphp

<div class="container mx-auto flex flex-col lg:flex-row gap-8 p-6 md:p-8">
    @include('partials.sidebar-unified')

    <main class="flex-grow fade-in-section">
        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="bg-green-50 border-r-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6 fade-in-section" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle ml-3"></i>
                    <div>{{ session('success') }}</div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border-r-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6 fade-in-section" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle ml-3"></i>
                    <div>{{ session('error') }}</div>
                </div>
            </div>
        @endif

        <!-- Warning Banner -->
        <div class="warning-banner fade-in-section">
            <i class="fas fa-exclamation-triangle"></i>
            <p>تراکنش‌ها تا زمان رسیدن کاربران به 11,111,111 نفر غیرفعال هستند</p>
        </div>

        <!-- Finance Section -->
        <section class="finance-section fade-in-section">
            <h2 class="finance-title">حساب مالی نجم بهار</h2>
            <p style="text-align: center; margin-bottom: 25px; font-size: 1.1rem; color: var(--color-gentle-black); font-family: var(--font-vazirmatn);">
                تراکنش‌ها تا زمان رسیدن کاربران به 11,111,111 نفر
            </p>

            <!-- Info Cards -->
            <div class="info-label-container">
                <div class="info-card">
                    <h4>گردش مالی کل</h4>
                    <div class="value">{{ number_format($totalAmount) }} بهار</div>
                    <div class="description">مجموع کل گردش مالی موفق در سیستم</div>
                </div>
                <div class="info-card">
                    <h4>کل بهار خلق شده</h4>
                    <div class="value">{{ number_format($userCount * 10000) }}</div>
                    <div class="description">مجموع کل بهار خلق شده در سیستم مالی ({{ number_format($userCount) }} کاربر × 10000 بهار)</div>
                </div>
                <div class="info-card">
                    <h4 style="color: var(--color-red-tomato);">وضعیت تراکنش‌ها</h4>
                    <div class="value" style="color: var(--color-red-tomato);">غیرفعال</div>
                    <div class="description">تراکنش‌ها تا رسیدن تعداد کاربران ارث‌کوپ به 11,111,111 کاربر غیرفعال است</div>
                </div>
                <div class="info-card">
                    <h4>تعداد کاربران سیستم</h4>
                    <div class="value">{{ number_format($userCount) }} کاربر</div>
                    <div class="description">تا رسیدن به حد نصاب: {{ number_format(11111111 - $userCount) }}</div>
                </div>
            </div>

            <!-- Accounts Section -->
            <div class="account-section">
                <div class="account-header">
                    <h3>حساب‌های من</h3>
                    <button class="create-account-button" disabled>
                        <i class="fas fa-plus"></i>
                        ایجاد حساب جدید
                    </button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>شماره حساب</th>
                            <th>نوع حساب</th>
                            <th>موجودی</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($userAccounts as $account)
                            <tr>
                                <td>{{ $account->cart_number }}</td>
                                <td>شخص حقیقی</td>
                                <td>{{ number_format($account->amount) }} بهار</td>
                                <td>
                                    <button class="action-button" disabled>
                                        جزئیات
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-wallet"></i>
                                        <p>هنوز هیچ حسابی ایجاد نکرده‌اید</p>
                                        <button class="create-account-button" disabled>
                                            <i class="fas fa-plus"></i>
                                            ایجاد اولین حساب
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Transactions Section -->
            <div class="transactions-section">
                <div class="transactions-header">
                    <h3>آخرین تراکنش‌ها</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>نوع</th>
                            <th>مبلغ (بهار)</th>
                            <th>از حساب</th>
                            <th>به حساب</th>
                            <th>توضیحات</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            @php
                                $fromAccount = $transaction->from_account_id ? \App\Models\Spring::find($transaction->from_account_id) : null;
                                $toAccount = $transaction->to_account_id ? \App\Models\Spring::find($transaction->to_account_id) : null;
                                $isWithdrawal = $userSpring && $transaction->from_account_id == $userSpring->id;
                            @endphp
                            <tr>
                                <td>{{ verta($transaction->created_at)->format('Y/m/d') }}</td>
                                <td>{{ $isWithdrawal ? 'برداشت' : 'واریز' }}</td>
                                <td>{{ number_format($transaction->amount) }} بهار</td>
                                <td>
                                    @if($transaction->from_account_id == null)
                                        0000000000<br>سامانه ارث‌کوپ
                                    @elseif($fromAccount)
                                        {{ $fromAccount->cart_number }}
                                    @else
                                        نامشخص
                                    @endif
                                </td>
                                <td>
                                    @if($toAccount && $toAccount->cart_number == '0000000000')
                                        0000000000<br>سامانه ارث‌کوپ
                                    @elseif($toAccount)
                                        {{ $toAccount->cart_number }}
                                    @else
                                        نامشخص
                                    @endif
                                </td>
                                <td>{{ $transaction->description ?? '-' }}</td>
                                <td>
                                    <span style="background: rgba(16, 185, 129, 0.1); color: var(--color-earth-green); padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem; font-weight: 600;">
                                        <i class="fas fa-check-circle ml-1"></i>
                                        انجام شده
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>هنوز هیچ تراکنشی انجام نشده است</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Fade-in animation for sections
        const sections = document.querySelectorAll('.fade-in-section');
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        sections.forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';
            section.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
            observer.observe(section);
        });
    });
</script>
@endpush