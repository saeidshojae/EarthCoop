@extends('admin.layout')

@section('content')
    <h2>تراکنش‌های امتیاز — {{ $user->fullName() }}</h2>
    <p>امتیاز فعلی: <strong>{{ $currentPoints }}</strong></p>

    <form method="get" class="form-inline mb-3">
        <input type="text" name="action" placeholder="Action key" value="{{ request('action') }}" class="form-control mr-2">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control mr-2">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control mr-2">
        <button class="btn btn-primary">فیلتر</button>
    </form>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>تاریخ</th>
                <th>عمل</th>
                <th>تغییر</th>
                <th>موجودی بعد</th>
                <th>منبع</th>
                <th>متادیتا</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
                <tr>
                    <td>{{ $tx->created_at }}</td>
                    <td>{{ $tx->action }}</td>
                    <td>{{ $tx->delta }}</td>
                    <td>{{ $tx->balance_after }}</td>
                    <td>{{ $tx->source }}</td>
                    <td><pre style="white-space:pre-wrap">{{ json_encode($tx->metadata) }}</pre></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $transactions->links() }}
@endsection
