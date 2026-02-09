@extends('layouts.admin')

@section('title', 'مدیریت ایمیل‌های سیستم - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'مدیریت ایمیل‌های سیستم')
@section('page-description', 'ایجاد و مدیریت آدرس‌های ایمیل سیستم')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">ایمیل‌های سیستم</h2>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت آدرس‌های ایمیل سیستم (support, contact, info, ...)</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.emails.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                <i class="fas fa-arrow-right"></i>
                قالب‌های ایمیل
            </a>
            <a href="{{ route('admin.system-emails.create') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                <i class="fas fa-plus"></i>
                ایمیل جدید
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error') || $errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    <!-- Emails Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">نام</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">آدرس ایمیل</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">نام نمایشی</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($emails as $email)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $email->name }}
                                    @if($email->is_default)
                                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            پیش‌فرض
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-gray-100">{{ $email->email }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ $email->display_name ?? '-' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($email->is_active)
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        فعال
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        غیرفعال
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-2">
                                    @if(!$email->is_default)
                                        <form action="{{ route('admin.system-emails.set-default', $email) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400" title="تنظیم به عنوان پیش‌فرض">
                                                <i class="fas fa-star"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.system-emails.edit', $email) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @if(!$email->is_default)
                                        <form action="{{ route('admin.system-emails.destroy', $email) }}" method="POST" class="inline" onsubmit="return confirm('آیا از حذف این ایمیل مطمئن هستید؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                هیچ ایمیل سیستمی یافت نشد.
                                <a href="{{ route('admin.system-emails.create') }}" class="text-blue-600 hover:underline mr-2">ایجاد ایمیل جدید</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $emails->links() }}
    </div>
</div>
@endsection

