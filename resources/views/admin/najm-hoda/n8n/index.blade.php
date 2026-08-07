@extends('layouts.admin')

@section('title', 'مدیریت n8n نجم هُدی - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت اتصال n8n نجم هُدی')
@section('page-description', 'وضعیت اتصال، آمادگی، کنترل‌های اجرایی و رسیدهای callback')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">آمادگی Staging</div>
                    <div class="fs-4 fw-bold {{ $report['status'] === 'ready' ? 'text-success' : 'text-warning' }}">
                        {{ $report['status'] === 'ready' ? 'آماده' : 'هنوز آماده نیست' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Cache</div>
                    <div class="fs-5 fw-bold">{{ $report['cache_driver'] }}</div>
                    <div class="small {{ $report['checks']['persistent_cache'] ? 'text-success' : 'text-danger' }}">
                        {{ $report['checks']['persistent_cache'] ? 'اشتراکی/پایدار' : 'برای callback واقعی کافی نیست' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Secret</div>
                    <div class="fs-5 fw-bold">{{ $report['secret_configured'] ? 'تنظیم شده' : 'تنظیم نشده' }}</div>
                    <div class="small text-muted">مقدار secret هرگز در پنل نمایش داده نمی‌شود.</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Callbackها</div>
                    <div class="fs-4 fw-bold">{{ number_format($counts['total']) }}</div>
                    <div class="small text-muted">موفق: {{ $counts['completed'] }} | خطا: {{ $counts['failed'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>چک‌لیست آمادگی</strong>
                    <form method="POST" action="{{ route('admin.najm-hoda.n8n.health') }}">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" type="submit">Health Check دستی</button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                            @foreach($report['checks'] as $name => $ok)
                                <tr>
                                    <td class="px-3">{{ str_replace('_', ' ', $name) }}</td>
                                    <td class="px-3 text-end">
                                        <span class="badge {{ $ok ? 'bg-success' : 'bg-secondary' }}">{{ $ok ? 'OK' : 'NO' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>کنترل اجرایی Runtime</strong></div>
                <div class="card-body">
                    <p class="small text-muted">این کلیدها فقط runtime را pause/resume می‌کنند و نمی‌توانند config سرور یا secret را دور بزنند.</p>
                    @can('najm-hoda.manage-settings')
                        <form method="POST" action="{{ route('admin.najm-hoda.n8n.controls.update') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Outbound به n8n</label>
                                <select class="form-select" name="outbound_enabled">
                                    <option value="1" @selected($report['runtime']['outbound_enabled'])>فعال در runtime</option>
                                    <option value="0" @selected(!$report['runtime']['outbound_enabled'])>Pause</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Callback ingress</label>
                                <select class="form-select" name="callback_ingress_enabled">
                                    <option value="1" @selected($report['runtime']['callback_ingress_enabled'])>فعال در runtime</option>
                                    <option value="0" @selected(!$report['runtime']['callback_ingress_enabled'])>Pause</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">دلیل تغییر</label>
                                <textarea class="form-control" name="reason" rows="2" maxlength="500" placeholder="برای audit یک دلیل کوتاه ثبت کنید"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">ثبت کنترل‌ها</button>
                        </form>
                    @else
                        <div class="alert alert-info mb-0">برای تغییر runtime controls مجوز مدیریت تنظیمات نجم هُدی لازم است.</div>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Workflow Allow-list</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Workflow</th><th>Mode</th><th>سطح</th></tr></thead>
                    <tbody>
                    @forelse($report['workflows'] as $workflow => $mode)
                        <tr>
                            <td><code>{{ $workflow }}</code></td>
                            <td>{{ $mode }}</td>
                            <td><span class="badge {{ $mode === 'read_only' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $mode === 'read_only' ? 'فقط خواندن' : 'پیشنهاد' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">هیچ workflow مجازی تنظیم نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>آخرین Callback Receiptها</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th>زمان</th><th>Workflow</th><th>Mode</th><th>Status</th><th>Request ID</th><th>Correlation</th><th>Run ID</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($receipts as $receipt)
                        <tr>
                            <td class="text-nowrap">{{ $receipt->received_at }}</td>
                            <td><code>{{ $receipt->workflow }}</code></td>
                            <td>{{ $receipt->mode }}</td>
                            <td><span class="badge {{ $receipt->status === 'completed' ? 'bg-success' : ($receipt->status === 'failed' ? 'bg-danger' : 'bg-info text-dark') }}">{{ $receipt->status }}</span></td>
                            <td><small>{{ $receipt->request_id }}</small></td>
                            <td><small>{{ $receipt->correlation_id }}</small></td>
                            <td><small>{{ $receipt->remote_run_id }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">هنوز callback ثبت نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($receipts->hasPages())
            <div class="card-footer">{{ $receipts->links() }}</div>
        @endif
    </div>
</div>
@endsection
