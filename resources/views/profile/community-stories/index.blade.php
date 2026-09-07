@extends('layouts.unified')

@section('title', 'داستان من - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container mx-auto px-4 md:px-6 py-8 md:py-12" dir="rtl">
    <div class="max-w-5xl mx-auto space-y-8">
        <header class="rounded-2xl border border-emerald-100 bg-white p-5 md:p-8 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-feather-alt text-xl" aria-hidden="true"></i>
                </div>
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 mb-2">داستان من در EarthCoop</h1>
                    <p class="text-sm md:text-base text-slate-600 leading-7 max-w-3xl">تجربه‌ای را که مایلید به‌صورت عمومی با جامعه به اشتراک بگذارید بنویسید. ارسال شما ابتدا بررسی می‌شود و تنها پس از تأیید می‌تواند منتشر یا برای بخش «صدای جامعه» انتخاب شود.</p>
                </div>
            </div>
        </header>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 md:p-8 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900 mb-5">ارسال یک داستان واقعی</h2>

            <form method="POST" action="{{ route('community-stories.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="story-body" class="block text-sm font-bold text-slate-700 mb-2">داستان یا تجربه شما</label>
                    <textarea id="story-body" name="body" rows="7" required minlength="40" maxlength="3000" class="w-full rounded-xl border border-slate-300 px-4 py-3 leading-7 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100" placeholder="چه تجربه، همکاری یا دستاورد واقعی‌ای در EarthCoop داشته‌اید؟">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="story-role" class="block text-sm font-bold text-slate-700 mb-2">نقش یا معرفی کوتاه <span class="font-normal text-slate-400">(اختیاری)</span></label>
                        <input id="story-role" type="text" name="role" maxlength="120" value="{{ old('role') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <div>
                        <label for="story-location" class="block text-sm font-bold text-slate-700 mb-2">موقعیت <span class="font-normal text-slate-400">(اختیاری)</span></label>
                        <input id="story-location" type="text" name="location" maxlength="120" value="{{ old('location') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>
                </div>

                <fieldset class="rounded-xl bg-slate-50 p-4 space-y-3">
                    <legend class="px-1 text-sm font-bold text-slate-700">اطلاعاتی که در صورت انتشار قابل نمایش‌اند</legend>
                    <label class="flex items-start gap-3 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="show_name" value="1" @checked(old('show_name', true)) class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>نام من نمایش داده شود.</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="show_avatar" value="1" @checked(old('show_avatar')) class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>اگر تصویر پروفایل دارم، تصویرم همراه داستان نمایش داده شود.</span>
                    </label>
                </fieldset>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <label class="flex items-start gap-3 text-sm leading-7 text-amber-950 cursor-pointer">
                        <input type="checkbox" name="consent_publication" value="1" required @checked(old('consent_publication')) class="mt-1 rounded border-amber-400 text-emerald-600 focus:ring-emerald-500">
                        <span><strong>رضایت انتشار:</strong> آگاهانه موافقم این داستان، در صورت تأیید، مطابق انتخاب‌های بالا به‌صورت عمومی در EarthCoop منتشر شود. می‌دانم بعداً می‌توانم این رضایت را پس بگیرم.</span>
                    </label>
                    @error('consent_publication')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-600 px-6 py-3 font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i>
                        <span>ارسال برای بررسی</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 md:p-8 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900 mb-5">داستان‌های ارسال‌شده من</h2>
            @forelse($stories as $story)
                @php
                    $statusLabel = match($story->status) {
                        \App\Models\CommunityStory::STATUS_APPROVED => 'تأییدشده',
                        \App\Models\CommunityStory::STATUS_REJECTED => 'ردشده',
                        \App\Models\CommunityStory::STATUS_WITHDRAWN => 'رضایت پس‌گرفته‌شده',
                        default => 'در انتظار بررسی',
                    };
                @endphp
                <article class="border-t border-slate-100 first:border-t-0 py-5 first:pt-0 last:pb-0">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="min-w-0">
                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 mb-3">{{ $statusLabel }}</span>
                            <p class="text-slate-700 leading-7 whitespace-pre-line">{{ $story->body }}</p>
                        </div>
                        @if($story->status !== \App\Models\CommunityStory::STATUS_WITHDRAWN && $story->consent_publication_at)
                            <form method="POST" action="{{ route('community-stories.withdraw', $story) }}" class="shrink-0">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-red-200 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-50 transition" onclick="return confirm('رضایت انتشار این داستان پس گرفته شود؟')">
                                    <i class="fas fa-eye-slash" aria-hidden="true"></i>
                                    <span>پس‌گرفتن رضایت</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl bg-slate-50 px-5 py-8 text-center text-slate-500">هنوز داستانی ارسال نکرده‌اید.</div>
            @endforelse
        </section>
    </div>
</div>
@endsection
