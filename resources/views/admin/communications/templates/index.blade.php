@extends('layouts.admin')

@section('title', 'قالب‌های ارتباطی - مرکز ارتباطات')
@section('page-title', 'قالب‌های ارتباطی')
@section('page-description', 'قالب‌های مرجع و نسخه‌های منتشرشدهٔ پیام‌ها')

@section('content')
@php($labels = \App\Support\Communication\CommunicationAdminLabels::class)
<div class="container mx-auto px-4 py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">قالب‌های مرجع</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">نسخه‌های منتشرشده تغییرناپذیرند؛ هر انتشار، نسخه‌ای تازه ایجاد می‌کند.</p>
        </div>
        <a href="{{ route('admin.communications.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-200">بازگشت به مرکز ارتباطات</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr class="text-right text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">کلید فنی</th><th class="px-4 py-3">نام قالب</th><th class="px-4 py-3">طبقه‌بندی</th><th class="px-4 py-3">آخرین نسخه</th><th class="px-4 py-3">وضعیت</th><th class="px-4 py-3">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($templates as $template)
                        @php($latest = $template->versions->first())
                        <tr class="text-sm text-gray-700 dark:text-gray-200">
                            <td class="px-4 py-3 font-mono text-xs">{{ $template->key }}</td>
                            <td class="px-4 py-3 font-medium">{{ $template->name }}</td>
                            <td class="px-4 py-3">{{ $labels::classification($template->classification) }}</td>
                            <td class="px-4 py-3">{{ $latest ? 'نسخه '.$latest->version.' / '.$labels::locale($latest->locale) : '—' }}</td>
                            <td class="px-4 py-3">{{ $template->is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.communications.templates.show', $template) }}" class="text-blue-600 hover:underline dark:text-blue-400">مشاهده و مدیریت نسخه‌ها</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">قالبی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())<div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">{{ $templates->links() }}</div>@endif
    </div>
</div>
@endsection
