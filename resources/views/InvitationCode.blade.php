@extends('layouts.unified')

@section('title', 'مدیریت کدهای دعوت - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container py-4 py-md-5">
    <div class="mx-auto" style="max-width: 960px;">
        <div class="bg-white rounded-3 shadow-sm border p-4 p-md-5 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-2">مدیریت کدهای دعوت</h1>
                    <p class="text-muted mb-0">ایجاد و مشاهده کدهای دعوت ثبت‌شده در سامانه.</p>
                </div>
            </div>

            <form action="{{ route('admin.invitation_codes.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="code" class="form-label fw-semibold">کد دعوت</label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        value="{{ old('code') }}"
                        class="form-control @error('code') is-invalid @enderror"
                        autocomplete="off"
                        required
                    >
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary px-4">ایجاد کد دعوت</button>
            </form>
        </div>

        <div class="bg-white rounded-3 shadow-sm border p-4 p-md-5">
            <h2 class="h5 fw-bold mb-4">فهرست کدهای دعوت</h2>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">کد</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col">تاریخ ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($codes as $code)
                            <tr>
                                <td class="fw-semibold" dir="ltr">{{ $code->code }}</td>
                                <td>
                                    <span class="badge {{ $code->used ? 'bg-secondary' : 'bg-success' }}">
                                        {{ $code->used ? 'استفاده شده' : 'استفاده نشده' }}
                                    </span>
                                </td>
                                <td>{{ $code->created_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">هنوز کد دعوتی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
