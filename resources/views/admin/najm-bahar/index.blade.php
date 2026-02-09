@extends('layouts.admin')

@section('title', 'مدیریت توافقنامه‌های نجم بهار')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-file-contract ml-2"></i>
                مدیریت توافقنامه‌های نجم بهار
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مدیریت و ویرایش توافقنامه‌های نجم بهار</p>
        </div>
        <a href="{{ route('admin.najm-bahar.create') }}" 
           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus ml-2"></i>
            ایجاد توافقنامه جدید
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle ml-2"></i>
            {{ session('error') }}
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کل توافقنامه‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-file-contract text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">توافقنامه‌های اصلی</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['main']) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-file-alt text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">زیرمجموعه‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['children']) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-sitemap text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- فیلترها و جستجو -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" action="{{ route('admin.najm-bahar.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}"
                       placeholder="جستجو در عنوان و محتوا..."
                       class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">فیلتر</label>
                <select name="filter" 
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    <option value="">همه توافقنامه‌ها</option>
                    <option value="main" {{ request('filter') == 'main' ? 'selected' : '' }}>فقط توافقنامه‌های اصلی</option>
                    <option value="children" {{ request('filter') == 'children' ? 'selected' : '' }}>فقط زیرمجموعه‌ها</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search ml-2"></i>
                    جستجو
                </button>
                @if(request()->has('search') || request()->has('filter'))
                <a href="{{ route('admin.najm-bahar.index') }}" 
                   class="inline-flex items-center px-6 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors mr-2">
                    <i class="fas fa-times ml-2"></i>
                    پاک کردن
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- جدول توافقنامه‌ها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                <i class="fas fa-list ml-2"></i>
                لیست توافقنامه‌ها
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="agreementsTable">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عنوان</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">محتوا</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">والد</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">زیرمجموعه‌ها</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ترتیب</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ ایجاد</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($agreements as $index => $agreement)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $agreement->title }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-600 dark:text-slate-400 max-w-md truncate">
                                    {!! Str::limit(strip_tags($agreement->content), 100) !!}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($agreement->parent_id)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        <i class="fas fa-level-up-alt ml-1"></i>
                                        {{ $agreement->parent->title ?? '-' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <i class="fas fa-star ml-1"></i>
                                        اصلی
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($agreement->children->count() > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                        <i class="fas fa-sitemap ml-1"></i>
                                        {{ $agreement->children->count() }} زیرمجموعه
                                    </span>
                                @else
                                    <span class="text-slate-400 text-sm">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $agreement->order }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                @php
                                    try {
                                        $date = isset($agreement->created_at) ? \Carbon\Carbon::parse($agreement->created_at) : null;
                                        echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                    } catch (\Exception $e) {
                                        echo '-';
                                    }
                                @endphp
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2 space-x-reverse">
                                    <a href="{{ route('admin.najm-bahar.edit', $agreement->id) }}" 
                                       class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-edit ml-1"></i>
                                        ویرایش
                                    </a>
                                    
                                    <form action="{{ route('admin.najm-bahar.destroy', $agreement->id) }}" 
                                          method="POST" 
                                          class="inline"
                                          onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این توافقنامه را حذف کنید؟{{ $agreement->children->count() > 0 ? ' توجه: این توافقنامه ' . $agreement->children->count() . ' زیرمجموعه دارد.' : '' }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors {{ $agreement->children->count() > 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                                                {{ $agreement->children->count() > 0 ? 'disabled' : '' }}>
                                            <i class="fas fa-trash ml-1"></i>
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-file-contract text-4xl text-slate-400 mb-3"></i>
                                    <p class="text-slate-600 dark:text-slate-400">هیچ توافقنامه‌ای یافت نشد</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        if ($('#agreementsTable').length) {
            $('#agreementsTable').DataTable({
                language: {
                    "decimal": "",
                    "emptyTable": "هیچ داده‌ای در جدول وجود ندارد",
                    "info": "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
                    "infoEmpty": "نمایش 0 تا 0 از 0 رکورد",
                    "infoFiltered": "(فیلتر شده از مجموع _MAX_ رکورد)",
                    "lengthMenu": "نمایش _MENU_ رکورد",
                    "loadingRecords": "در حال بارگذاری...",
                    "processing": "در حال پردازش...",
                    "search": "جستجو:",
                    "zeroRecords": "رکوردی یافت نشد",
                    "paginate": {
                        "first": "اولین",
                        "last": "آخرین",
                        "next": "بعدی",
                        "previous": "قبلی"
                    }
                },
                pageLength: 25,
                responsive: true,
                columnDefs: [
                    { orderable: false, searchable: false, targets: [0, 4, 7] }, // #, زیرمجموعه‌ها, عملیات
                    { orderable: true, searchable: true, targets: [1, 2] }, // عنوان, محتوا
                    { orderable: true, searchable: false, targets: [3, 5, 6] } // والد, ترتیب, تاریخ ایجاد
                ],
                order: [[5, 'asc'], [6, 'desc']], // مرتب‌سازی بر اساس ترتیب و سپس تاریخ ایجاد
                autoWidth: false,
                processing: false,
                serverSide: false
            });
        }
    });
</script>
@endpush
@endsection

