@extends('layouts.unified')
@section('title', 'ترجیحات ارتباطی - ' . config('app.name', 'EarthCoop'))
@section('content')
<div class="container mx-auto max-w-4xl px-4 py-6 space-y-5">
    <div>
        <h1 class="text-xl font-semibold">ترجیحات ارتباطی</h1>
        <p class="mt-1 text-sm text-gray-500">ایمیل‌های ضروری برای امنیت، دسترسی و تعهدات لازم همیشه فعال می‌مانند. سایر موضوع‌ها را می‌توانید تنظیم کنید.</p>
    </div>

    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('profile.communication-preferences.update') }}" class="space-y-3">
        @csrf
        @method('PUT')
        @forelse($templates as $template)
            @php
                $isRequired = $template->classification === \App\Enums\Communication\CommunicationClassification::Required;
                $stored = $preferences->get($template->key)?->preference;
                $effective = $isRequired ? 'on' : ($stored ?? ($template->classification === \App\Enums\Communication\CommunicationClassification::Operational ? 'on' : 'off'));
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium">{{ $template->name }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $template->key }} · {{ $template->classification->value }}</div>
                        @if($cadences->has($template->key))
                            <div class="mt-1 text-xs text-gray-500">تناوب ثبت‌شده: {{ $cadences->get($template->key) }}</div>
                        @endif
                    </div>
                    @if($isRequired)
                        <div class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-200">همیشه فعال — غیرقابل غیرفعال‌سازی</div>
                    @else
                        <select name="preferences[{{ $template->key }}]" class="rounded-lg border-gray-300 dark:bg-gray-900">
                            <option value="on" @selected($effective === 'on')>روشن</option>
                            <option value="off" @selected($effective === 'off')>خاموش</option>
                        </select>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">هنوز موضوع ارتباطی فعالی تعریف نشده است.</div>
        @endforelse

        @if($templates->isNotEmpty())
            <button class="rounded-lg bg-gray-900 px-4 py-2 text-white">ذخیره ترجیحات</button>
        @endif
    </form>
</div>
@endsection
