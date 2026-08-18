@extends('layouts.unified')

@section('title', 'پرونده جدید - ' . $office->name)

@section('content')
<div class="container mx-auto px-4 py-6 max-w-3xl">
    <a href="{{ route('secretariat.cases.index', $office) }}" class="text-sm text-emerald-700 hover:underline">بازگشت به پرونده‌ها</a>
    <h1 class="text-2xl font-bold mt-3 mb-6">ایجاد پرونده دبیرخانه</h1>

    <form method="POST" action="{{ route('secretariat.cases.store', $office) }}" class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-6 space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-2">عنوان پرونده</label>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="500" class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            @error('title')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-2">خلاصه موضوع</label>
            <textarea name="summary" rows="6" maxlength="10000" class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">{{ old('summary') }}</textarea>
            @error('summary')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-2">سطح دسترسی</label>
            <select name="confidentiality" required class="w-full rounded-xl border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                @foreach($confidentialities as $level)
                    <option value="{{ $level }}" @selected(old('confidentiality', 'office_members') === $level)>{{ $level }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-2">تا پیش از Case ACL مستقل، restricted/confidential فقط توسط مدیر سیستم قابل ایجاد و مشاهده است.</p>
            @error('confidentiality')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <button class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3">ایجاد پرونده</button>
    </form>
</div>
@endsection
