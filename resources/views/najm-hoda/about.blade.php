@extends('layouts.unified')

@section('title', 'آشنایی با نجم هدا - ' . config('app.name', 'EarthCoop'))

@push('styles')
<style>
    .hoda-about-shell { padding: 1rem 0 2.5rem; }
    .hoda-about-layout { display: flex; flex-direction: column; gap: 1.5rem; }
    .hoda-about-main { min-width: 0; flex: 1 1 auto; }
    .hoda-about-hero,
    .hoda-about-card {
        border: 1px solid rgba(148, 163, 184, .24);
        border-radius: 18px;
        background: rgba(255, 255, 255, .82);
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
    }
    .hoda-about-hero { padding: 1.35rem; }
    .hoda-about-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin-bottom: .75rem;
        padding: .35rem .7rem;
        border-radius: 999px;
        color: var(--color-dark-green);
        background: rgba(16, 185, 129, .1);
        font-size: .78rem;
        font-weight: 700;
    }
    .hoda-about-title { margin: 0; color: var(--color-gentle-black); font-size: clamp(1.5rem, 4vw, 2.2rem); font-weight: 800; }
    .hoda-about-lead { margin: .75rem 0 0; max-width: 48rem; color: #64748b; line-height: 1.95; }
    .hoda-about-actions { display: flex; flex-wrap: wrap; gap: .65rem; margin-top: 1rem; }
    .hoda-about-primary,
    .hoda-about-secondary {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        padding: .65rem 1rem;
        border-radius: 999px;
        font-weight: 700;
        text-decoration: none;
    }
    .hoda-about-primary { border: 0; color: #fff; background: linear-gradient(135deg, var(--color-earth-green), var(--color-dark-green)); }
    .hoda-about-secondary { border: 1px solid rgba(59, 130, 246, .25); color: var(--color-dark-blue); background: #fff; }
    .hoda-about-grid { display: grid; gap: .85rem; margin-top: 1rem; }
    .hoda-about-card { padding: 1rem; }
    .hoda-about-card h2 { margin: 0 0 .45rem; color: var(--color-gentle-black); font-size: 1rem; font-weight: 800; }
    .hoda-about-card p { margin: 0; color: #64748b; font-size: .88rem; line-height: 1.9; }
    body.dark-mode .hoda-about-hero,
    body.dark-mode .hoda-about-card { background: var(--card-dark, #252525); border-color: rgba(148, 163, 184, .18); }
    body.dark-mode .hoda-about-title,
    body.dark-mode .hoda-about-card h2 { color: var(--text-dark); }
    @media (min-width: 1024px) {
        .hoda-about-layout { flex-direction: row; gap: 1.5rem; }
        .hoda-about-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
@endpush

@section('content')
<div class="hoda-about-shell">
    <div class="container mx-auto px-3 sm:px-5 md:px-7 hoda-about-layout">
        @include('partials.sidebar-unified')

        <main class="hoda-about-main">
            <section class="hoda-about-hero">
                <span class="hoda-about-eyebrow"><i class="fas fa-sparkles" aria-hidden="true"></i>همراه هوشمند ارث‌کوپ</span>
                <h1 class="hoda-about-title">آشنایی با نجم هدا</h1>
                <p class="hoda-about-lead">نجم هدا همراه، راهنما و مشاور هوشمند شما در ارث‌کوپ است. می‌توانید در هر بخش از او توضیح، راهنمایی و کمک برای آماده‌کردن کارهای خود بخواهید؛ اما تصمیم و اختیار نهایی همواره با شماست.</p>

                <div class="hoda-about-actions">
                    <button type="button" class="hoda-about-primary" onclick="document.getElementById('najm-hoda-toggle')?.click()">
                        <i class="fas fa-comments" aria-hidden="true"></i>گفتگو با نجم هدا
                    </button>
                    <a href="{{ route('home') }}" class="hoda-about-secondary"><i class="fas fa-house" aria-hidden="true"></i>بازگشت به خانه</a>
                </div>
            </section>

            <div class="hoda-about-grid">
                <section class="hoda-about-card">
                    <h2>همراه شما در سراسر ارث‌کوپ</h2>
                    <p>نجم هدا با توجه به صفحه و بخشی که در آن هستید، می‌تواند همان موضوع را برایتان توضیح دهد و مسیر بعدی را روشن‌تر کند.</p>
                </section>
                <section class="hoda-about-card">
                    <h2>مشاور، نه صاحب اختیار</h2>
                    <p>نجم هدا می‌تواند پیشنهاد بدهد، متن آماده کند و شما را راهنمایی کند؛ اما کارهای حساس و انتشار از حساب شما بدون اجازه و تأیید شما انجام نمی‌شوند.</p>
                </section>
                <section class="hoda-about-card">
                    <h2>حریم خصوصی و مرز دسترسی</h2>
                    <p>شناخت نجم هدا باید در محدوده دسترسی مجاز هر کاربر باقی بماند. اطلاعات خصوصی دیگران نباید صرفاً به دلیل استفاده از دستیار در اختیار شما قرار گیرد.</p>
                </section>
                <section class="hoda-about-card">
                    <h2>چطور از او کمک بگیرم؟</h2>
                    <p>دکمه شناور نجم هدا در محیط برنامه در دسترس است. آن را باز کنید و درباره همان صفحه، گروه، ایده یا کاری که در ذهن دارید با او گفتگو کنید.</p>
                </section>
            </div>
        </main>
    </div>
</div>
@endsection
