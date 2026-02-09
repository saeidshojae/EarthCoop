@extends('layouts.admin')

@section('title', 'مدیریت قالب‌های ایمیل - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت قالب‌های ایمیل')
@section('page-description', 'ایجاد و مدیریت قالب‌های ایمیل')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">قالب‌های ایمیل</h2>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت و ویرایش قالب‌های ایمیل سیستم</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.system-emails.index') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                <i class="fas fa-envelope"></i>
                ایمیل‌های سیستم
            </a>
            <a href="{{ route('admin.emails.send') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                <i class="fas fa-paper-plane"></i>
                ارسال ایمیل
            </a>
            <a href="{{ route('admin.emails.create') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                <i class="fas fa-plus"></i>
                قالب جدید
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <!-- Templates Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">نام</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">موضوع</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">دسته‌بندی</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">تاریخ ایجاد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($templates as $template)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $template->name }}</div>
                                @if($template->description)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ \Illuminate\Support\Str::limit($template->description, 50) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-gray-100">{{ \Illuminate\Support\Str::limit($template->subject, 50) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($template->category)
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ $template->category }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($template->is_active)
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        فعال
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        غیرفعال
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ verta($template->created_at)->format('Y/m/d H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.emails.edit', $template) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.emails.destroy', $template) }}" method="POST" class="inline" onsubmit="return confirm('آیا از حذف این قالب مطمئن هستید؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                هیچ قالب ایمیلی یافت نشد.
                                <a href="{{ route('admin.emails.create') }}" class="text-blue-600 hover:underline mr-2">ایجاد قالب جدید</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $templates->links() }}
    </div>
</div>
@endsection

