@extends('layouts.admin')

@section('title', 'مدیریت داستان‌های جامعه')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">داستان‌های جامعه EarthCoop</h1>
            <p class="text-muted mb-0">فقط داستان‌هایی که عضو صریحاً اجازه انتشار عمومی داده است می‌توانند تأیید و برای صفحه خوش‌آمد انتخاب شوند.</p>
        </div>
        <span class="badge bg-light text-dark border px-3 py-2">{{ $stories->total() }} داستان</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        @forelse($stories as $story)
            <div class="col-12">
                <article class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                            <div>
                                <div class="fw-bold">{{ $story->display_name ?: ($story->user?->fullName() ?: 'عضو EarthCoop') }}</div>
                                <div class="small text-muted">
                                    {{ $story->role ?: 'بدون عنوان' }}
                                    @if($story->location) · {{ $story->location }} @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <span class="badge bg-secondary">{{ $story->status }}</span>
                                @if($story->consent_publication_at && ! $story->withdrawn_at)
                                    <span class="badge bg-success">رضایت انتشار فعال</span>
                                @else
                                    <span class="badge bg-warning text-dark">بدون رضایت فعال</span>
                                @endif
                                @if($story->is_featured)
                                    <span class="badge bg-primary">منتخب صفحه خوش‌آمد</span>
                                @endif
                            </div>
                        </div>

                        <p class="mb-3" style="white-space: pre-line;">{{ $story->body }}</p>

                        @if($story->review_note)
                            <div class="alert alert-light border py-2 mb-3"><strong>یادداشت بررسی:</strong> {{ $story->review_note }}</div>
                        @endif

                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.community-stories.approve', $story) }}">
                                @csrf
                                <button class="btn btn-success btn-sm" type="submit">تأیید و انتشار</button>
                            </form>

                            @if($story->is_featured)
                                <form method="POST" action="{{ route('admin.community-stories.unfeature', $story) }}">
                                    @csrf
                                    <button class="btn btn-outline-primary btn-sm" type="submit">حذف از منتخب‌ها</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.community-stories.feature', $story) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-sm" type="submit">نمایش در صفحه خوش‌آمد</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.community-stories.reject', $story) }}" class="d-flex flex-wrap gap-2">
                                @csrf
                                <input type="text" name="review_note" maxlength="1000" class="form-control form-control-sm" style="min-width: 220px;" placeholder="دلیل رد (اختیاری)">
                                <button class="btn btn-outline-danger btn-sm" type="submit">رد داستان</button>
                            </form>
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm"><div class="card-body text-center py-5 text-muted">هنوز داستانی برای بررسی ثبت نشده است.</div></div>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $stories->links() }}</div>
</div>
@endsection
