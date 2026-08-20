@extends('layouts.unified')

@section('title', 'ثبت مکاتبه - دبیرخانه')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-5xl">
    <div class="mb-6">
        <a href="{{ route('secretariat.index', $office) }}" class="text-sm text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به دبیرخانه
        </a>
        <h1 class="text-2xl font-bold mt-3">ثبت مکاتبه</h1>
        <p class="text-sm text-gray-500 mt-1">دفتر {{ $office->name }} — طرف‌های مکاتبه به‌صورت snapshot رسمی نگهداری می‌شوند.</p>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-xl bg-red-50 text-red-800 p-4">
            <ul class="list-disc pr-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('secretariat.correspondence.store', $office) }}"
          class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6 space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2">جهت مکاتبه</label>
                <select name="direction" id="direction" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    <option value="incoming" @selected(old('direction', $direction) === 'incoming')>وارده</option>
                    <option value="outgoing" @selected(old('direction', $direction) === 'outgoing')>صادره</option>
                    <option value="internal" @selected(old('direction', $direction) === 'internal')>داخلی</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">کانال</label>
                <select name="channel" class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @foreach($channels as $channel)
                        <option value="{{ $channel }}" @selected(old('channel', $direction === 'internal' ? 'internal' : 'email') === $channel)>{{ $channel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">محرمانگی</label>
                <select name="confidentiality" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @foreach($confidentialities as $level)
                        <option value="{{ $level }}" @selected(old('confidentiality', $office->default_confidentiality) === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-2">عنوان</label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="500"
                       class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-2">موضوع</label>
                <input type="text" name="subject" value="{{ old('subject') }}" maxlength="500"
                       class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">شماره مرجع بیرونی</label>
                <input type="text" name="external_reference_number" value="{{ old('external_reference_number') }}" maxlength="255"
                       class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <div id="received-at-field">
                <label class="block text-sm font-medium mb-2">زمان دریافت</label>
                <input type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <div id="sent-at-field" class="hidden">
                <label class="block text-sm font-medium mb-2">زمان ارسال ثبت‌شده</label>
                <input type="datetime-local" name="sent_at" value="{{ old('sent_at') }}"
                       class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
        </div>

        <div id="external-party-fields" class="rounded-xl bg-gray-50 dark:bg-gray-900 p-4">
            <h2 class="font-bold mb-3">طرف بیرونی</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">نام شخص/نهاد</label>
                    <input type="text" name="external_party_name" value="{{ old('external_party_name') }}" maxlength="255"
                           class="w-full rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">ایمیل</label>
                    <input type="email" name="external_party_email" value="{{ old('external_party_email') }}" maxlength="320"
                           class="w-full rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                </div>
            </div>
        </div>

        <div id="internal-recipient-field" class="hidden rounded-xl bg-gray-50 dark:bg-gray-900 p-4">
            <label class="block text-sm font-medium mb-2">گیرنده داخلی</label>
            <select name="internal_recipient_user_id" class="w-full rounded-xl border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                <option value="">انتخاب عضو</option>
                @foreach($members as $member)
                    @php $memberName = trim((string) $member->first_name . ' ' . (string) $member->last_name); @endphp
                    <option value="{{ $member->id }}" @selected((string) old('internal_recipient_user_id') === (string) $member->id)>
                        {{ $memberName ?: $member->email ?: ('#' . $member->id) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">خلاصه</label>
            <textarea name="summary" rows="3" class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">{{ old('summary') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-2">متن مکاتبه</label>
            <textarea name="body" rows="10" class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">{{ old('body') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-2">پیوست اختیاری</label>
            <input type="file" name="attachment" class="w-full rounded-xl border border-gray-300 p-2 dark:bg-gray-900 dark:border-gray-700">
        </div>

        <div class="flex justify-end">
            <button class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3">ایجاد پیش‌نویس مکاتبه</button>
        </div>
    </form>
</div>

<script>
(() => {
    const direction = document.getElementById('direction');
    const external = document.getElementById('external-party-fields');
    const internal = document.getElementById('internal-recipient-field');
    const received = document.getElementById('received-at-field');
    const sent = document.getElementById('sent-at-field');
    const sync = () => {
        const value = direction.value;
        external.classList.toggle('hidden', value === 'internal');
        internal.classList.toggle('hidden', value !== 'internal');
        received.classList.toggle('hidden', value !== 'incoming');
        sent.classList.toggle('hidden', value === 'incoming');
    };
    direction.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
