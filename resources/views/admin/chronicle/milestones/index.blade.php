@extends('layouts.admin')

@section('title', 'مدیریت گاه‌شمار ارث‌کوپ')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-timeline ml-2"></i>
                مدیریت گاه‌شمار ارث‌کوپ
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                رویدادهای تاریخی با تاریخ canonical ذخیره می‌شوند؛ سال ارث‌کوپ از تاریخ محاسبه می‌شود و ذخیره نمی‌شود.
            </p>
        </div>
        <a href="{{ route('chronicle.index') }}" target="_blank"
           class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-800">
            <i class="fas fa-external-link-alt ml-2"></i>
            مشاهده صفحه عمومی
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <section class="xl:col-span-1 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-5">افزودن رویداد</h2>

            <form method="POST" action="{{ route('admin.chronicle.milestones.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تاریخ رویداد</label>
                    <x-temporal.date-input name="occurred_on" :value="old('occurred_on')" class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white" required />
                    @error('occurred_on')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">عنوان فارسی</label>
                    <input name="title_fa" value="{{ old('title_fa') }}" required maxlength="255"
                           class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">عنوان انگلیسی</label>
                    <input name="title_en" value="{{ old('title_en') }}" maxlength="255" dir="ltr"
                           class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">عنوان عربی</label>
                    <input name="title_ar" value="{{ old('title_ar') }}" maxlength="255"
                           class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">شرح فارسی</label>
                    <textarea name="description_fa" rows="4" maxlength="5000"
                              class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('description_fa') }}</textarea>
                </div>
                <details class="rounded-lg border border-slate-200 dark:border-slate-700 p-3">
                    <summary class="cursor-pointer font-semibold text-slate-700 dark:text-slate-300">ترجمه شرح</summary>
                    <div class="space-y-3 mt-3">
                        <textarea name="description_en" rows="3" maxlength="5000" dir="ltr" placeholder="English description"
                                  class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('description_en') }}</textarea>
                        <textarea name="description_ar" rows="3" maxlength="5000" placeholder="الوصف بالعربية"
                                  class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('description_ar') }}</textarea>
                    </div>
                </details>

                <div class="grid grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">ترتیب</label>
                        <input type="number" min="0" max="65535" name="sort_order" value="{{ old('sort_order', 0) }}"
                               class="w-full rounded-lg border-slate-300 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published')) class="rounded">
                        منتشر شود
                    </label>
                </div>

                <button class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
                    ثبت رویداد
                </button>
            </form>
        </section>

        <section class="xl:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">رویدادهای ثبت‌شده</h2>
                <span class="text-sm text-slate-500">{{ number_format($milestones->count()) }} رویداد</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-right">تاریخ</th>
                            <th class="px-4 py-3 text-right">عنوان</th>
                            <th class="px-4 py-3 text-right">وضعیت</th>
                            <th class="px-4 py-3 text-right">ترتیب</th>
                            <th class="px-4 py-3 text-right">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($milestones as $milestone)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-temporal.date :value="$milestone->occurred_on" style="medium" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $milestone->translatedTitle('fa') }}</div>
                                    @if($milestone->translatedTitle('en') !== $milestone->translatedTitle('fa'))
                                        <div class="text-xs text-slate-500 mt-1" dir="ltr">{{ $milestone->translatedTitle('en') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($milestone->is_published)
                                        <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">منتشر</span>
                                    @else
                                        <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-600">پیش‌نویس</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $milestone->sort_order }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.chronicle.milestones.edit', $milestone) }}"
                                           class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700">ویرایش</a>
                                        <form method="POST" action="{{ route('admin.chronicle.milestones.destroy', $milestone) }}"
                                              onsubmit="return confirm('این رویداد حذف شود؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-3 py-1.5 rounded bg-red-600 text-white hover:bg-red-700">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">هنوز رویدادی ثبت نشده است.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
