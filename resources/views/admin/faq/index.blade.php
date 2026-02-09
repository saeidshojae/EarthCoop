@extends('layouts.admin')

@section('title', 'مدیریت سوالات متداول')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-question-circle ml-2"></i>
                مدیریت سوالات متداول
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                مدیریت و پاسخگویی به سوالات متداول کاربران
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
            <span class="text-green-800 dark:text-green-200">{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400"></i>
                <span class="text-red-800 dark:text-red-200 font-semibold">خطا در اعتبارسنجی</span>
            </div>
            <ul class="list-disc list-inside text-red-700 dark:text-red-300 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کل سوالات</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-question-circle text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-slate-500 to-slate-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-100 text-sm mb-1">جدید</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['new']) }}</p>
                </div>
                <div class="bg-slate-400/20 rounded-full p-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm mb-1">در حال بررسی</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['in_progress']) }}</p>
                </div>
                <div class="bg-yellow-400/20 rounded-full p-4">
                    <i class="fas fa-spinner text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">پاسخ داده شده</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['answered']) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">منتشر شده</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['published']) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-eye text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- فیلترها و جستجو -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" action="{{ route('admin.faq.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- جستجو -->
            <div class="lg:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="جستجو در عنوان، متن، ایمیل..."
                           class="w-full px-4 py-2.5 pr-10 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                </div>
            </div>

            <!-- فیلتر وضعیت -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                <select name="status" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>جدید</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>در حال بررسی</option>
                    <option value="answered" {{ request('status') == 'answered' ? 'selected' : '' }}>پاسخ داده شده</option>
                </select>
            </div>

            <!-- فیلتر انتشار -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">انتشار</label>
                <select name="published" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    <option value="1" {{ request('published') == '1' ? 'selected' : '' }}>منتشر شده</option>
                    <option value="0" {{ request('published') == '0' ? 'selected' : '' }}>منتشر نشده</option>
                </select>
            </div>

            <!-- فیلتر دسته‌بندی -->
            @if($categories->isNotEmpty())
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">دسته‌بندی</label>
                <select name="category" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="">همه</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- فیلتر تاریخ -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">از تاریخ</label>
                <input type="date" 
                       name="from" 
                       value="{{ request('from') }}"
                       class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تا تاریخ</label>
                <input type="date" 
                       name="to" 
                       value="{{ request('to') }}"
                       class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
            </div>

            <!-- دکمه‌های فیلتر -->
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <i class="fas fa-filter ml-2"></i>
                    اعمال فیلتر
                </button>
                <a href="{{ route('admin.faq.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    پاک‌سازی
                </a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <form id="bulkForm" action="{{ route('admin.faq.bulk') }}" method="POST" class="hidden mb-6">
        @csrf
        <input type="hidden" name="action" id="bulkAction">
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 flex items-center justify-between">
            <div class="text-yellow-800 dark:text-yellow-200 text-sm font-semibold">
                <span id="selectedCount">0</span> سوال انتخاب شده
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="submitBulk('publish')" class="px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">انتشار</button>
                <button type="button" onclick="submitBulk('unpublish')" class="px-3 py-1.5 text-xs font-semibold text-white bg-slate-600 rounded-lg hover:bg-slate-700">لغو انتشار</button>
                <button type="button" onclick="submitBulk('mark_answered')" class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">علامت‌گذاری پاسخ داده شده</button>
                <button type="button" onclick="submitBulk('mark_in_progress')" class="px-3 py-1.5 text-xs font-semibold text-white bg-yellow-600 rounded-lg hover:bg-yellow-700">علامت‌گذاری در حال بررسی</button>
                <button type="button" onclick="submitBulk('delete')" class="px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">حذف</button>
            </div>
        </div>
    </form>

    <!-- جدول سوالات -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right">
                            <input type="checkbox" id="selectAll" class="size-4 text-blue-600 focus:ring-blue-500 rounded">
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">سوال</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تماس‌کننده</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">دسته‌بندی</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">انتشار</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($questions as $question)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" 
                                       name="ids[]" 
                                       value="{{ $question->id }}"
                                       class="rowCheckbox size-4 text-blue-600 focus:ring-blue-500 rounded">
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900 dark:text-white mb-1">
                                    {{ $question->title }}
                                </div>
                                <div class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2">
                                    {{ Str::limit($question->question, 100) }}
                                </div>
                                @if($question->answer)
                                    <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                        <i class="fas fa-check-circle ml-1"></i>
                                        پاسخ: {{ Str::limit($question->answer, 60) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($question->contact_name || $question->contact_email)
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        <i class="fas fa-user text-slate-400 ml-1"></i>
                                        {{ $question->contact_name ?? 'نامشخص' }}
                                    </div>
                                    <div class="text-xs text-slate-600 dark:text-slate-400">
                                        <i class="fas fa-envelope text-slate-400 ml-1"></i>
                                        {{ $question->contact_email ?? '---' }}
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">---</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                                    {{ $question->category ?? 'عمومی' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($question->status === 'answered')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                        <i class="fas fa-check-circle ml-1"></i>
                                        پاسخ داده شده
                                    </span>
                                @elseif($question->status === 'in_progress')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">
                                        <i class="fas fa-spinner ml-1"></i>
                                        در حال بررسی
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                                        <i class="fas fa-clock ml-1"></i>
                                        جدید
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($question->is_published)
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                        <i class="fas fa-eye ml-1"></i>
                                        منتشر شده
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                                        <i class="fas fa-eye-slash ml-1"></i>
                                        منتشر نشده
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                @php
                                    $createdAt = $question->created_at instanceof \Carbon\Carbon 
                                        ? $question->created_at 
                                        : \Carbon\Carbon::parse($question->created_at);
                                @endphp
                                <div>{{ \Morilog\Jalali\Jalalian::fromCarbon($createdAt)->format('Y/m/d') }}</div>
                                <div class="text-xs">{{ \Morilog\Jalali\Jalalian::fromCarbon($createdAt)->format('H:i') }}</div>
                                @if($question->answered_at)
                                    @php
                                        $answeredAt = $question->answered_at instanceof \Carbon\Carbon 
                                            ? $question->answered_at 
                                            : \Carbon\Carbon::parse($question->answered_at);
                                    @endphp
                                    <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                        پاسخ: {{ \Morilog\Jalali\Jalalian::fromCarbon($answeredAt)->format('Y/m/d') }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <button onclick="openEditModal({{ json_encode($question->toArray()) }})" 
                                            class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                        <i class="fas fa-edit ml-1"></i>
                                        ویرایش
                                    </button>
                                    <form action="{{ route('admin.faq.destroy', $question) }}" 
                                          method="POST" 
                                          class="inline"
                                          onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این سوال را حذف کنید؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                                            <i class="fas fa-trash ml-1"></i>
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                سوالی یافت نشد
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($questions->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $questions->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal ویرایش -->
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeEditModal()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-4xl w-full p-6 transform transition-all">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">
                    <i class="fas fa-edit ml-2"></i>
                    ویرایش سوال
                </h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="editForm" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <!-- عنوان -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            عنوان سوال <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="title" 
                               id="editTitle"
                               required
                               class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    </div>

                    <!-- متن سوال -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            متن سوال <span class="text-red-500">*</span>
                        </label>
                        <textarea name="question" 
                                  id="editQuestion"
                                  rows="4"
                                  required
                                  class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"></textarea>
                    </div>

                    <!-- پاسخ -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            پاسخ
                        </label>
                        <textarea name="answer" 
                                  id="editAnswer"
                                  rows="6"
                                  class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"></textarea>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            در صورت پر کردن پاسخ و انتخاب وضعیت «پاسخ داده شده»، ایمیل اطلاع‌رسانی به کاربر ارسال می‌شود.
                        </p>
                    </div>

                    <!-- دسته‌بندی -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            دسته‌بندی
                        </label>
                        <input type="text" 
                               name="category" 
                               id="editCategory"
                               placeholder="مثال: عمومی، فنی، مالی..."
                               class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    </div>

                    <!-- وضعیت و انتشار -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                وضعیت <span class="text-red-500">*</span>
                            </label>
                            <select name="status" 
                                    id="editStatus"
                                    required
                                    class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                                <option value="new">جدید</option>
                                <option value="in_progress">در حال بررسی</option>
                                <option value="answered">پاسخ داده شده</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="is_published" value="0">
                                <input type="checkbox" 
                                       name="is_published" 
                                       value="1"
                                       id="editPublished"
                                       class="size-4 text-blue-600 focus:ring-blue-500 rounded">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    نمایش در صفحه FAQ
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- اطلاعات تماس -->
                    <div id="contactInfo" class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4">
                        <div class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                            <strong>اطلاعات تماس:</strong>
                        </div>
                        <div id="contactDetails" class="text-sm text-slate-700 dark:text-slate-300"></div>
                        <div id="emailStatus" class="text-xs mt-2"></div>
                    </div>
                </div>

                <!-- دکمه‌های فرم -->
                <div class="flex items-center justify-end gap-3 mt-6">
                    <button type="button" 
                            onclick="closeEditModal()"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                        <i class="fas fa-times ml-2"></i>
                        انصراف
                    </button>
                    <button type="submit" 
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-save ml-2"></i>
                        ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Bulk Actions
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.rowCheckbox');
    const bulkForm = document.getElementById('bulkForm');
    const selectedCount = document.getElementById('selectedCount');

    function updateBulkBar() {
        const checked = document.querySelectorAll('.rowCheckbox:checked');
        if (checked.length > 0) {
            bulkForm.classList.remove('hidden');
            selectedCount.textContent = checked.length;
        } else {
            bulkForm.classList.add('hidden');
        }
    }

    selectAll?.addEventListener('change', function() {
        rowCheckboxes.forEach(cb => {
            cb.checked = this.checked;
        });
        updateBulkBar();
    });

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });

    function submitBulk(action) {
        const checked = document.querySelectorAll('.rowCheckbox:checked');
        if (checked.length === 0) {
            alert('لطفاً حداقل یک سوال را انتخاب کنید');
            return;
        }

        if (action === 'delete' && !confirm(`آیا مطمئن هستید که می‌خواهید ${checked.length} سوال را حذف کنید؟`)) {
            return;
        }

        const form = document.getElementById('bulkForm');
        const actionInput = document.getElementById('bulkAction');
        actionInput.value = action;

        // Add selected IDs
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });

        form.submit();
    }

    // Edit Modal
    function openEditModal(question) {
        const modal = document.getElementById('editModal');
        const form = document.getElementById('editForm');
        const contactInfo = document.getElementById('contactInfo');
        const contactDetails = document.getElementById('contactDetails');
        const emailStatus = document.getElementById('emailStatus');

        form.action = `/admin/faq-questions/${question.id}`;
        document.getElementById('editTitle').value = question.title || '';
        document.getElementById('editQuestion').value = question.question || '';
        document.getElementById('editAnswer').value = question.answer || '';
        document.getElementById('editCategory').value = question.category || '';
        document.getElementById('editStatus').value = question.status || 'new';
        document.getElementById('editPublished').checked = question.is_published == 1;

        // نمایش اطلاعات تماس
        if (question.contact_name || question.contact_email) {
            contactInfo.classList.remove('hidden');
            contactDetails.innerHTML = `
                <div><i class="fas fa-user text-slate-400 ml-1"></i> ${question.contact_name || 'نامشخص'}</div>
                <div class="mt-1"><i class="fas fa-envelope text-slate-400 ml-1"></i> ${question.contact_email || '---'}</div>
            `;
            
            if (question.contact_email) {
                if (question.notified_at) {
                    const notifiedDate = new Date(question.notified_at).toLocaleDateString('fa-IR');
                    emailStatus.innerHTML = `<i class="fas fa-check-circle text-green-600 ml-1"></i> ایمیل اطلاع‌رسانی در ${notifiedDate} ارسال شده است.`;
                    emailStatus.className = 'text-xs text-green-600 dark:text-green-400 mt-2';
                } else {
                    emailStatus.innerHTML = `<i class="fas fa-envelope-open-text text-yellow-600 ml-1"></i> پس از ثبت پاسخ و انتخاب وضعیت «پاسخ داده شده»، ایمیل اطلاع‌رسانی ارسال می‌شود.`;
                    emailStatus.className = 'text-xs text-yellow-600 dark:text-yellow-400 mt-2';
                }
            } else {
                emailStatus.innerHTML = `<i class="fas fa-exclamation-circle text-red-600 ml-1"></i> ایمیلی برای کاربر ثبت نشده است.`;
                emailStatus.className = 'text-xs text-red-600 dark:text-red-400 mt-2';
            }
        } else {
            contactInfo.classList.add('hidden');
        }

        modal.classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // Close modal on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });

    // Auto-hide success message
    @if(session('success'))
        setTimeout(() => {
            const alert = document.querySelector('.bg-green-50');
            if (alert) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    @endif
</script>
@endpush
@endsection
