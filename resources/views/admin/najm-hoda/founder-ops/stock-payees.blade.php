@extends('layouts.admin')

@section('title','حساب مقصد سرمایه پروژه‌ها - نجم هدا')
@section('page-title','Stock Payee Mapping')
@section('page-description','پیکربندی کنترل‌شده حساب مقصد سرمایه سهام پروژه‌ها')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">حساب مقصد سرمایه Stockهای پروژه‌ای</h4>
            <div class="text-muted small">هر تغییر فقط پس از Founder Approval اجرا می‌شود. حساب شخصی کاربر قابل انتخاب نیست.</div>
        </div>
        <a href="{{ route('admin.najm-hoda.founder-ops.index') }}" class="btn btn-outline-secondary">بازگشت به Founder Ops</a>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Stockهای پروژه‌ای</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Stock</th><th>Issuer ID</th><th>حساب فعلی</th><th>انتخاب حساب مقصد</th><th>اقدام</th></tr></thead>
                    <tbody>
                    @forelse($stocks as $stock)
                        @php($mapping=$mappings->get($stock->id))
                        <tr>
                            <td>#{{ $stock->id }}<div class="small text-muted">{{ $stock->info ?? '-' }}</div></td>
                            <td>{{ $stock->issuer_id ?? '-' }}</td>
                            <td>
                                @if($mapping?->account)
                                    <strong>{{ $mapping->account->name }}</strong>
                                    <div class="small text-muted">{{ $mapping->account->account_number }} · {{ $mapping->account->type }}</div>
                                    <span class="badge bg-{{ $mapping->is_active?'success':'secondary' }}">{{ $mapping->is_active?'فعال':'غیرفعال' }}</span>
                                @else
                                    <span class="badge bg-danger">بدون mapping</span>
                                @endif
                            </td>
                            <td colspan="2">
                                <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.stock-payees.request',$stock) }}" class="d-flex gap-2 flex-wrap">
                                    @csrf
                                    <select name="account_id" class="form-select form-select-sm" style="max-width:420px" required>
                                        <option value="">انتخاب حساب legal_entity/central</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" @selected($mapping?->account_id===$account->id)>{{ $account->name }} — {{ $account->account_number }} ({{ $account->type }})</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary">درخواست تغییر mapping</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Stock پروژه‌ای ثبت نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($approvalItems->isNotEmpty())
        <div class="card border-warning">
            <div class="card-header"><strong>Mappingهای منتظر تصمیم Founder</strong></div>
            <div class="card-body">
                @foreach($approvalItems as $approval)
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 py-2 border-bottom">
                        <div>
                            <strong>Stock #{{ data_get($approval,'context.entity_id') }}</strong>
                            <div class="small text-muted">Account #{{ data_get($approval,'context.account_id') }} · {{ data_get($approval,'sla_status') }}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.stock-payees.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">تأیید mapping</button></form>
                            <form method="POST" action="{{ route('admin.najm-hoda.founder-ops.stock-payees.decision',data_get($approval,'id')) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger">رد</button></form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
