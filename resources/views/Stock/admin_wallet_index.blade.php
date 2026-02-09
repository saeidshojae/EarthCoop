@extends('layouts.admin')

@section('title', 'مدیریت کیف پول‌ها - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت کیف پول‌ها')
@section('page-description', 'مشاهده و مدیریت کیف پول کاربران')
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@push('styles')
<style>
    .wallet-management-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .wallet-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .wallet-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    
    .wallet-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .wallet-stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .wallet-stat-card.success {
        background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    }
    
    .wallet-stat-card.info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }
    
    .wallet-stat-card.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .wallet-stat-label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    
    .wallet-stat-value {
        font-size: 1.75rem;
        font-weight: 800;
    }
    
    .wallet-filters {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .wallet-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
    }
    
    .wallet-table thead {
        background: #f9fafb;
    }
    
    .wallet-table th {
        padding: 1rem;
        text-align: right;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .wallet-table td {
        padding: 1rem;
        text-align: right;
        color: #4b5563;
        font-size: 0.875rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .wallet-table tbody tr:hover {
        background: #f9fafb;
    }
    
    .wallet-action-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }
    
    .wallet-action-btn.primary {
        background: #3b82f6;
        color: white;
    }
    
    .wallet-action-btn.primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }
    
    .wallet-action-btn.success {
        background: #10b981;
        color: white;
    }
    
    .wallet-action-btn.success:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .wallet-action-btn.danger {
        background: #ef4444;
        color: white;
    }
    
    .wallet-action-btn.danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    
    @media (prefers-color-scheme: dark) {
        .wallet-management-card {
            background: #1e293b !important;
            color: #f1f5f9 !important;
        }
        
        .wallet-header {
            border-bottom-color: #475569 !important;
        }
        
        .wallet-header h3 {
            color: #f1f5f9 !important;
        }
        
        .wallet-filters {
            background: #334155 !important;
        }
        
        .wallet-table thead {
            background: #334155 !important;
        }
        
        .wallet-table th {
            color: #f1f5f9 !important;
            border-bottom-color: #475569 !important;
        }
        
        .wallet-table td {
            color: #cbd5e1 !important;
            border-bottom-color: #475569 !important;
        }
        
        .wallet-table tbody tr:hover {
            background: #334155 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="wallet-management-card">
        <div class="wallet-header">
            <h3>
                <i class="fas fa-wallet ml-2"></i>
                مدیریت کیف پول‌ها
            </h3>
            <a href="{{ route('admin.dashboard') }}" class="wallet-action-btn" style="background: #6b7280; color: white;">
                <i class="fas fa-arrow-right ml-2"></i>
                بازگشت
            </a>
        </div>
        
        <!-- آمار کلی -->
        @if(isset($stats))
        <div class="wallet-stats-grid">
            <div class="wallet-stat-card success">
                <div class="wallet-stat-label">تعداد کل کیف پول‌ها</div>
                <div class="wallet-stat-value">{{ number_format($stats['total_wallets']) }}</div>
            </div>
            
            <div class="wallet-stat-card info">
                <div class="wallet-stat-label">کل موجودی (ریال)</div>
                <div class="wallet-stat-value">{{ number_format($stats['total_balance']) }}</div>
            </div>
            
            <div class="wallet-stat-card warning">
                <div class="wallet-stat-label">کل مبلغ بلوکه شده</div>
                <div class="wallet-stat-value">{{ number_format($stats['total_held']) }}</div>
            </div>
            
            <div class="wallet-stat-card">
                <div class="wallet-stat-label">موجودی قابل استفاده</div>
                <div class="wallet-stat-value">{{ number_format($stats['total_available']) }}</div>
            </div>
            
            <div class="wallet-stat-card info">
                <div class="wallet-stat-label">کل تراکنش‌ها</div>
                <div class="wallet-stat-value">{{ number_format($stats['total_transactions']) }}</div>
            </div>
            
            <div class="wallet-stat-card success">
                <div class="wallet-stat-label">تراکنش‌های امروز</div>
                <div class="wallet-stat-value">{{ number_format($stats['today_transactions']) }}</div>
            </div>
        </div>
        @endif
        
        <!-- فیلترها -->
        <div class="wallet-filters">
            <form method="GET" action="{{ route('admin.wallet.index') }}" class="space-y-4">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">جستجو</label>
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}" 
                               placeholder="نام، ایمیل..."
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">کاربر</label>
                        <input type="number" 
                               name="user_id" 
                               value="{{ request('user_id') }}" 
                               placeholder="ID کاربر"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">حداقل موجودی</label>
                        <input type="number" 
                               name="balance_min" 
                               value="{{ request('balance_min') }}" 
                               placeholder="0"
                               step="0.01"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">حداکثر موجودی</label>
                        <input type="number" 
                               name="balance_max" 
                               value="{{ request('balance_max') }}" 
                               placeholder="0"
                               step="0.01"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="wallet-action-btn primary">
                        <i class="fas fa-search ml-2"></i>
                        جستجو
                    </button>
                    <a href="{{ route('admin.wallet.index') }}" class="wallet-action-btn" style="background: #6b7280; color: white;">
                        <i class="fas fa-times ml-2"></i>
                        پاک کردن
                    </a>
                </div>
            </form>
        </div>
        
        <!-- جدول کیف پول‌ها -->
        @if($wallets->count() > 0)
        <div style="overflow-x: auto;">
            <table class="wallet-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>کاربر</th>
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>موجودی (ریال)</th>
                        <th>بلوکه شده (ریال)</th>
                        <th>قابل استفاده (ریال)</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($wallets as $index => $wallet)
                    <tr>
                        <td><strong>{{ ($wallets->currentPage() - 1) * $wallets->perPage() + $index + 1 }}</strong></td>
                        <td>
                            <a href="{{ route('admin.users.show', $wallet->user_id) }}" style="color: #3b82f6; text-decoration: none;">
                                #{{ $wallet->user_id }}
                            </a>
                        </td>
                        <td><strong>{{ ($wallet->user->first_name ?? '') . ' ' . ($wallet->user->last_name ?? '') }}</strong></td>
                        <td>{{ $wallet->user->email ?? '—' }}</td>
                        <td><strong style="color: #10b981;">{{ number_format($wallet->balance) }}</strong></td>
                        <td><strong style="color: #f59e0b;">{{ number_format($wallet->held_amount) }}</strong></td>
                        <td><strong style="color: #3b82f6;">{{ number_format($wallet->balance - $wallet->held_amount) }}</strong></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <a href="{{ route('admin.wallet.show', $wallet->id) }}" class="wallet-action-btn primary">
                                    <i class="fas fa-eye"></i>
                                    مشاهده
                                </a>
                                <button onclick="openCreditModal({{ $wallet->id }}, {{ $wallet->user_id }})" class="wallet-action-btn success">
                                    <i class="fas fa-plus"></i>
                                    شارژ
                                </button>
                                <button onclick="openDebitModal({{ $wallet->id }}, {{ $wallet->user_id }})" class="wallet-action-btn danger">
                                    <i class="fas fa-minus"></i>
                                    کسر
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 2rem;">
            {{ $wallets->links('pagination::bootstrap-5') }}
        </div>
        @else
        <div style="text-align: center; padding: 3rem 1rem; background: #f9fafb; border-radius: 12px; border: 2px dashed #e5e7eb;">
            <i class="fas fa-inbox" style="font-size: 4rem; color: #9ca3af; margin-bottom: 1rem;"></i>
            <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">کیف پولی یافت نشد</div>
            <p style="color: #6b7280; font-size: 0.875rem;">هیچ کیف پولی با فیلترهای انتخاب شده یافت نشد.</p>
        </div>
        @endif
    </div>
</div>

<!-- Modal برای شارژ -->
<div id="creditModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">شارژ کیف پول</h3>
        <form method="POST" action="{{ route('admin.wallet.credit') }}">
            @csrf
            <input type="hidden" id="credit_user_id" name="user_id">
            <div style="margin-bottom: 1rem;">
                <label class="block text-sm font-semibold text-slate-600 mb-2">مبلغ (تومان)</label>
                <input type="number" name="amount" step="0.01" min="0" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label class="block text-sm font-semibold text-slate-600 mb-2">توضیحات (اختیاری)</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeCreditModal()" class="wallet-action-btn" style="background: #6b7280; color: white;">انصراف</button>
                <button type="submit" class="wallet-action-btn success">شارژ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal برای کسر -->
<div id="debitModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">کسر از کیف پول</h3>
        <form method="POST" action="{{ route('admin.wallet.debit') }}">
            @csrf
            <input type="hidden" id="debit_wallet_id" name="wallet_id">
            <div style="margin-bottom: 1rem;">
                <label class="block text-sm font-semibold text-slate-600 mb-2">مبلغ (تومان)</label>
                <input type="number" name="amount" step="0.01" min="0" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label class="block text-sm font-semibold text-slate-600 mb-2">توضیحات (اختیاری)</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeDebitModal()" class="wallet-action-btn" style="background: #6b7280; color: white;">انصراف</button>
                <button type="submit" class="wallet-action-btn danger">کسر</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openCreditModal(walletId, userId) {
        document.getElementById('credit_user_id').value = userId;
        document.getElementById('creditModal').style.display = 'flex';
    }
    
    function closeCreditModal() {
        document.getElementById('creditModal').style.display = 'none';
    }
    
    function openDebitModal(walletId, userId) {
        document.getElementById('debit_wallet_id').value = walletId;
        document.getElementById('debitModal').style.display = 'flex';
    }
    
    function closeDebitModal() {
        document.getElementById('debitModal').style.display = 'none';
    }
    
    // بستن modal با کلیک روی backdrop
    document.getElementById('creditModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeCreditModal();
    });
    
    document.getElementById('debitModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeDebitModal();
    });
</script>
@endpush
@endsection

