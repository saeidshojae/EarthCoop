@extends('layouts.unified')

@section('title', 'داشبورد نجم بهار')

@push('styles')
<style>
    .nb-dashboard {
        position: relative;
        overflow: hidden;
    }

    .nb-dashboard::before {
        content: '';
        position: absolute;
        inset: -20% -10% auto auto;
        width: 520px;
        height: 520px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.15), transparent 60%);
        z-index: 0;
        pointer-events: none;
    }

    .nb-hero {
        position: relative;
        background: linear-gradient(135deg, #0f766e 0%, #10b981 55%, #60a5fa 100%);
        color: #ffffff;
        border-radius: 28px;
        padding: 28px 28px 26px;
        box-shadow: 0 18px 40px rgba(15, 118, 110, 0.25);
        overflow: hidden;
    }

    .nb-hero::after {
        content: '';
        position: absolute;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        top: -80px;
        right: -80px;
    }

    .nb-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.18);
        color: #ffffff;
        backdrop-filter: blur(6px);
    }

    .nb-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        animation: nb-fade-up 0.6s ease both;
    }

    .nb-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
    }

    .nb-stat {
        background: linear-gradient(135deg, rgba(241, 245, 249, 0.9), rgba(255, 255, 255, 0.8));
        border-radius: 18px;
        padding: 18px;
        border: 1px solid rgba(226, 232, 240, 0.7);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .nb-stat:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
    }

    .nb-metric {
        font-size: 1.6rem;
        font-weight: 800;
        color: #0f172a;
    }

    .nb-metric-accent {
        color: #0f766e;
    }

    .nb-action {
        background: linear-gradient(135deg, #10b981, #0ea5e9);
        color: #ffffff;
        border-radius: 999px;
        padding: 10px 18px;
        font-weight: 600;
        box-shadow: 0 10px 24px rgba(14, 165, 233, 0.25);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .nb-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(14, 165, 233, 0.35);
    }

    @keyframes nb-fade-up {
        0% {
            opacity: 0;
            transform: translateY(12px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .nb-sidebar {
        position: relative;
    }
</style>
@endpush

@section('content')
<div class="bg-light-gray/60 py-10 md:py-12 nb-dashboard" style="background-color: var(--color-light-gray);">
    <div class="container mx-auto px-5 md:px-10 max-w-6xl relative z-10 space-y-8">
        <section class="nb-hero">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="nb-chip">
                        <i class="fas fa-sparkles"></i>
                        نجم بهار
                    </div>
                    <h1 class="text-3xl md:text-4xl font-black mt-4">داشبورد مالی شما</h1>
                    <p class="text-sm md:text-base text-emerald-50 mt-2">گزارش کلی سامانه و نمای حساب شما</p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="nb-chip">
                        <i class="fas fa-shield-check"></i>
                        {{ $isThresholdMet ? 'تراکنش‌ها فعال' : 'تراکنش‌ها قفل' }}
                    </span>
                    <a href="{{ route('najm-bahar.wallet') }}" class="nb-action">
                        ورود به کیف پول
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">
            <div class="lg:order-1 nb-sidebar">
                @include('najm-bahar.partials.sidebar')
            </div>

            <main class="space-y-8 lg:order-2">

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="nb-card p-6">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">گزارش کلی سامانه</h2>
                            <p class="text-sm text-gray-500">تصویر کلی از وضعیت سیستم نجم بهار</p>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold {{ $isThresholdMet ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            <i class="fas fa-signal"></i>
                            {{ $isThresholdMet ? 'تراکنش‌ها فعال' : 'تراکنش‌ها قفل' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">تعداد کل اعضای جامعه</p>
                            <p class="nb-metric">{{ number_format($userCount) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">کل پول خلق شده</p>
                            <p class="nb-metric">{{ \App\Helpers\BaharMoney::formatDecimalHtml($totalMinted) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">کاربران باقی‌مانده تا حدنصاب</p>
                            <p class="nb-metric">{{ number_format($remainingUsers) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">حدنصاب فعال‌سازی تراکنش</p>
                            <p class="nb-metric">{{ number_format($userThreshold) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">موجودی حساب حق عضویت</p>
                            <p class="nb-metric">{{ \App\Helpers\BaharMoney::formatDecimalHtml($membershipBalance) }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $membershipAccountCode }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-gray-500">وضعیت تراکنش‌های کاربری</p>
                            <p class="nb-metric nb-metric-accent">{{ $isThresholdMet ? 'فعال' : 'قفل' }}</p>
                            <p class="text-xs text-gray-400 mt-1">بر اساس حدنصاب سامانه</p>
                        </div>
                    </div>
                </div>

                <div class="nb-card p-6">
                    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">نمای کلی حساب شما</h2>
                            <p class="text-sm text-gray-500">خلاصه موجودی و فعالیت‌های مالی</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" id="membershipFeeBtn" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-br from-purple-600 to-blue-600 text-white rounded-full font-semibold text-sm shadow-lg hover:shadow-xl transition-all hover:scale-105">
                                <i class="fas fa-id-card"></i>
                                پرداخت حق عضویت سالانه
                            </button>
                            <a href="{{ route('najm-bahar.wallet') }}" class="nb-action">
                                مشاهده کیف پول
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">موجودی حساب اصلی</p>
                            <p class="nb-metric nb-metric-accent">{{ \App\Helpers\BaharMoney::formatDecimalHtml($account->balance) }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">شماره حساب</p>
                            <p class="text-base font-mono text-emerald-800">{{ $account->account_number }}</p>
                        </div>
                        <div class="nb-stat">
                            <p class="text-sm text-emerald-700">تعداد تراکنش‌های شما</p>
                            <p class="nb-metric nb-metric-accent">{{ number_format($userTransactionsCount) }}</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- مدال پرداخت حق عضویت -->
<div id="membershipFeeModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
        <div class="bg-gradient-to-br from-purple-600 to-blue-600 p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-id-card text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">پرداخت حق عضویت سالانه</h3>
                        <p class="text-sm text-purple-100">تأیید پرداخت</p>
                    </div>
                </div>
                <button type="button" class="text-white/80 hover:text-white transition-colors" onclick="closeMembershipModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <div id="membershipModalContent" class="p-6 space-y-4">
            <!-- محتوا از API بارگذاری می‌شود -->
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-10 w-10 border-4 border-purple-200 border-t-purple-600"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openMembershipModal() {
    const modal = document.getElementById('membershipFeeModal');
    const content = document.getElementById('membershipModalContent');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // بارگذاری اطلاعات از API
    fetch('{{ route("najm-bahar.membership-fee.info") }}')
        .then(response => response.json())
        .then(data => {
            if (data.has_paid) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-3xl text-green-600"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">برای سال جاری پرداخت شده</h4>
                        <p class="text-gray-600 mb-4">شما حق عضویت سالانه خود را برای سال جاری پرداخت کرده‌اید.</p>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">تاریخ عضویت:</span>
                                <span class="font-bold text-green-700">${data.membership_date_formatted}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">سالگرد بعدی:</span>
                                <span class="font-bold text-purple-700">${data.next_anniversary_formatted}</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">
                            <i class="fas fa-calendar-alt ml-1"></i>
                            تا تاریخ سالگرد بعدی نیازی به پرداخت مجدد ندارید
                        </p>
                    </div>
                    <button type="button" onclick="closeMembershipModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                        بستن
                    </button>
                `;
                return;
            }

            if (data.requires_sub_account) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-wallet text-3xl text-amber-600"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">ساخت حساب فرعی ضروری است</h4>
                        <p class="text-gray-600 mb-4">برای پرداخت حق عضویت، ابتدا یک حساب فرعی بسازید و موجودی فعال را به آن منتقل کنید.</p>
                        <div class="space-y-4 text-right">
                            <div class="bg-white border border-amber-200 rounded-lg p-4">
                                <p class="text-sm text-gray-700 mb-3">ایجاد حساب فرعی در همین‌جا:</p>
                                <form action="${data.create_subaccount_store_url}" method="POST" class="space-y-3">
                                    @csrf
                                    <input type="text" name="name" class="w-full px-3 py-2 border border-amber-200 rounded-lg" placeholder="نام حساب فرعی (اختیاری)">
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-br from-amber-500 to-orange-500 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all">
                                        <i class="fas fa-plus"></i>
                                        ایجاد حساب فرعی
                                    </button>
                                </form>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                                <p class="text-xs text-emerald-800">
                                    بعد از ساخت حساب فرعی، موجودی فعال خود را به حساب فرعی منتقل کنید.
                                </p>
                                <a href="${data.transfer_url}" class="inline-flex items-center justify-center gap-2 w-full mt-3 px-4 py-2 bg-gradient-to-br from-emerald-500 to-teal-500 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all">
                                    <i class="fas fa-exchange-alt"></i>
                                    انتقال موجودی به حساب فرعی
                                </a>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="closeMembershipModal()" class="w-full mt-4 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                        بستن
                    </button>
                `;
                return;
            }

            if (!data.has_enough_balance) {
                content.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800 mb-2">موجودی ناکافی</h4>
                        <p class="text-gray-600 mb-4">موجودی فعال شما برای پرداخت کافی نیست.</p>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">حساب فرعی مبدا:</span>
                                <span class="font-bold text-amber-700">${data.sub_account ? data.sub_account.code : '-'}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">موجودی فعال شما:</span>
                                <span class="font-bold text-amber-600">${data.balance_active_formatted} بهار</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">مبلغ مورد نیاز:</span>
                                <span class="font-bold text-red-600">${data.total_fee_formatted} بهار</span>
                            </div>
                        </div>
                        <div class="bg-white border border-emerald-200 rounded-lg p-4 mt-4 text-right">
                            <p class="text-sm text-gray-700 mb-2">انتقال موجودی فعال به حساب فرعی (همین‌جا):</p>
                            <form action="${data.transfer_to_url || data.transfer_url}" method="POST" class="space-y-3">
                                @csrf
                                <input type="number" name="amount" min="1" step="0.01" class="w-full px-3 py-2 border border-emerald-200 rounded-lg" placeholder="مبلغ بهار">
                                <input type="text" name="description" class="w-full px-3 py-2 border border-emerald-200 rounded-lg" placeholder="توضیحات (اختیاری)">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-br from-emerald-500 to-teal-500 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all">
                                    <i class="fas fa-exchange-alt"></i>
                                    انتقال به حساب فرعی
                                </button>
                            </form>
                            <p class="text-xs text-emerald-700 mt-2">موجودی فعال حساب اصلی: ${data.main_active_formatted} بهار</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeMembershipModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                        بستن
                    </button>
                `;
                return;
            }

            // نمایش تقسیم‌بندی و دکمه پرداخت
            let breakdownHtml = '';
            data.breakdown.forEach(item => {
                breakdownHtml += `
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <div>
                            <p class="font-semibold text-gray-800">${item.name}</p>
                            <p class="text-xs text-gray-500 font-mono">${item.account}</p>
                        </div>
                        <span class="font-bold text-purple-600">${item.amount_formatted} بهار</span>
                    </div>
                `;
            });

            content.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-700">حساب فرعی مبدا:</span>
                            <span class="font-bold text-purple-700">${data.sub_account ? data.sub_account.code : '-'}</span>
                        </div>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm text-gray-700">موجودی فعال شما:</span>
                            <span class="font-bold text-green-600">${data.balance_active_formatted} بهار</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-gray-900">مجموع حق عضویت:</span>
                            <span class="text-xl font-black text-purple-600">${data.total_fee_formatted} بهار</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">جزئیات تقسیم‌بندی:</h4>
                        ${breakdownHtml}
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <i class="fas fa-info-circle ml-1"></i>
                            پرداخت حق عضویت تنها از موجودی فعال شما کسر می‌شود.
                        </p>
                    </div>

                    <form action="{{ route('najm-bahar.membership-fee.pay') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" name="sub_account_id" value="${data.sub_account ? data.sub_account.id : ''}">
                        <button type="submit" class="w-full py-3 bg-gradient-to-br from-purple-600 to-blue-600 text-white rounded-lg font-bold text-lg shadow-lg hover:shadow-xl transition-all hover:scale-105">
                            <i class="fas fa-check-circle ml-2"></i>
                            تأیید و پرداخت
                        </button>
                        <button type="button" onclick="closeMembershipModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                            انصراف
                        </button>
                    </form>
                </div>
            `;
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times-circle text-3xl text-red-600"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-800 mb-2">خطا در بارگذاری</h4>
                    <p class="text-gray-600">لطفاً دوباره تلاش کنید.</p>
                </div>
                <button type="button" onclick="closeMembershipModal()" class="w-full py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    بستن
                </button>
            `;
        });
}

function closeMembershipModal() {
    const modal = document.getElementById('membershipFeeModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// اتصال دکمه به تابع
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('membershipFeeBtn');
    if (btn) {
        btn.addEventListener('click', openMembershipModal);
    }

    // بستن مدال با کلیک روی پس‌زمینه
    const modal = document.getElementById('membershipFeeModal');
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeMembershipModal();
        }
    });
});
</script>
@endpush
@endsection
