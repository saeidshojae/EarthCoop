@extends('layouts.admin')

@section('title', 'مدیریت اطلاعیه‌ها')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-bullhorn ml-2"></i>
                مدیریت اطلاعیه‌ها
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مدیریت و ارسال اطلاعیه‌های عمومی</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کل اطلاعیه‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['total'] ?? 0) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-bullhorn text-2xl"></i>
                </div>
            </div>
        </div>
        
        @foreach(['global' => 'جهانی', 'country' => 'کشور', 'province' => 'استان', 'city' => 'شهر'] as $level => $label)
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">{{ $label }}</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['by_level'][$level] ?? 0) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-map-marker-alt text-2xl"></i>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- فرم ایجاد اطلاعیه -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-plus-circle ml-2"></i>
            ایجاد اطلاعیه جدید
        </h3>
        
        <form action="{{ route('admin.announcement.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="title" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        تیتر اطلاعیه <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="{{ old('title') }}"
                           required
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="تیتر اطلاعیه را وارد کنید">
                    @error('title')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="group_level" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        سطح گروه <span class="text-red-500">*</span>
                    </label>
                    <select id="group_level" 
                            name="group_level"
                            required
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                        <option value="">انتخاب کنید</option>
                        <option value="global" {{ old('group_level') == 'global' ? 'selected' : '' }}>جهانی</option>
                        <option value="continent" {{ old('group_level') == 'continent' ? 'selected' : '' }}>قاره</option>
                        <option value="country" {{ old('group_level') == 'country' ? 'selected' : '' }}>کشور</option>
                        <option value="province" {{ old('group_level') == 'province' ? 'selected' : '' }}>استان</option>
                        <option value="county" {{ old('group_level') == 'county' ? 'selected' : '' }}>شهرستان</option>
                        <option value="section" {{ old('group_level') == 'section' ? 'selected' : '' }}>بخش</option>
                        <option value="city" {{ old('group_level') == 'city' ? 'selected' : '' }}>شهر/روستا</option>
                        <option value="region" {{ old('group_level') == 'region' ? 'selected' : '' }}>منطقه/دهستان</option>
                        <option value="neighborhood" {{ old('group_level') == 'neighborhood' ? 'selected' : '' }}>محله</option>
                        <option value="street" {{ old('group_level') == 'street' ? 'selected' : '' }}>خیابان</option>
                        <option value="alley" {{ old('group_level') == 'alley' ? 'selected' : '' }}>کوچه</option>
                    </select>
                    @error('group_level')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="content" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    متن اطلاعیه <span class="text-red-500">*</span>
                </label>
                <textarea id="content" 
                          name="content" 
                          rows="6"
                          required
                          class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                          placeholder="متن اطلاعیه را وارد کنید">{{ old('content') }}</textarea>
                @error('content')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="image" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        تصویر اطلاعیه
                    </label>
                    <input type="file" 
                           id="image" 
                           name="image" 
                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    @error('image')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">حداکثر حجم: 5 مگابایت | فرمت‌های مجاز: JPG, PNG, GIF, WEBP</p>
                </div>

                <div class="flex items-center">
                    <div class="flex items-center h-5">
                        <input id="should_pin" 
                               name="should_pin" 
                               type="checkbox" 
                               value="1"
                               {{ old('should_pin', true) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-slate-800 focus:ring-2 dark:bg-slate-700 dark:border-slate-600">
                    </div>
                    <label for="should_pin" class="mr-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        پین شدن خودکار در گروه‌های مربوطه
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-plus ml-2"></i>
                    ایجاد اطلاعیه
                </button>
            </div>
        </form>
    </div>

    <!-- فیلترها و جستجو -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" action="{{ route('admin.announcement.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}"
                       placeholder="جستجو در عنوان و محتوا..."
                       class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">سطح گروه</label>
                <select name="group_level" 
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    <option value="">همه سطوح</option>
                    <option value="global" {{ request('group_level') == 'global' ? 'selected' : '' }}>جهانی</option>
                    <option value="country" {{ request('group_level') == 'country' ? 'selected' : '' }}>کشور</option>
                    <option value="province" {{ request('group_level') == 'province' ? 'selected' : '' }}>استان</option>
                    <option value="city" {{ request('group_level') == 'city' ? 'selected' : '' }}>شهر</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search ml-2"></i>
                    جستجو
                </button>
                @if(request()->has('search') || request()->has('group_level'))
                <a href="{{ route('admin.announcement.index') }}" 
                   class="inline-flex items-center px-6 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors mr-2">
                    <i class="fas fa-times ml-2"></i>
                    پاک کردن
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- جدول اطلاعیه‌ها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                <i class="fas fa-list ml-2"></i>
                لیست اطلاعیه‌ها
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="announcementsTable">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تیتر</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تصویر</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">متن</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">سطح گروه</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">وضعیت پین</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ ایجاد</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($announcements as $index => $announcement)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $announcement->title }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($announcement->image)
                                    <img src="{{ asset($announcement->image) }}" 
                                         alt="{{ $announcement->title }}"
                                         class="w-16 h-16 object-cover rounded-lg cursor-pointer"
                                         onclick="window.open('{{ asset($announcement->image) }}', '_blank')">
                                @else
                                    <span class="text-slate-400 text-sm">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-600 dark:text-slate-400 max-w-md truncate">
                                    {!! Str::limit(strip_tags($announcement->content), 100) !!}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $levelLabels = [
                                        'global' => 'جهانی',
                                        'continent' => 'قاره',
                                        'country' => 'کشور',
                                        'province' => 'استان',
                                        'county' => 'شهرستان',
                                        'section' => 'بخش',
                                        'city' => 'شهر/روستا',
                                        'region' => 'منطقه/دهستان',
                                        'neighborhood' => 'محله',
                                        'street' => 'خیابان',
                                        'alley' => 'کوچه'
                                    ];
                                    $levelColors = [
                                        'global' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                        'country' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'province' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'city' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
                                    ];
                                    $level = $announcement->group_level ?? 'global';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $levelColors[$level] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $levelLabels[$level] ?? $level }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($announcement->should_pin)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <i class="fas fa-thumbtack ml-1"></i>
                                        پین شده
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                        پین نشده
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                @php
                                    try {
                                        $date = isset($announcement->created_at) ? \Carbon\Carbon::parse($announcement->created_at) : null;
                                        echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                    } catch (\Exception $e) {
                                        echo '-';
                                    }
                                @endphp
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2 space-x-reverse">
                                    <a href="{{ route('admin.announcement.edit', $announcement) }}" 
                                       class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-edit ml-1"></i>
                                        ویرایش
                                    </a>
                                    
                                    @if($announcement->should_pin)
                                    <form action="{{ route('admin.announcement.unpin', $announcement) }}" 
                                          method="POST" 
                                          class="inline"
                                          onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این اطلاعیه را از پین خارج کنید؟')">
                                        @csrf
                                        @method('POST')
                                        <button type="submit" 
                                                class="inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700 transition-colors">
                                            <i class="fas fa-thumbtack ml-1"></i>
                                            برداشتن پین
                                        </button>
                                    </form>
                                    @endif
                                    
                                    <form action="{{ route('admin.announcement.delete', $announcement->id) }}" 
                                          method="POST" 
                                          class="inline"
                                          onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این اطلاعیه را حذف کنید؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors">
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
                                    <i class="fas fa-bullhorn text-4xl text-slate-400 mb-3"></i>
                                    <p class="text-slate-600 dark:text-slate-400">هیچ اطلاعیه‌ای یافت نشد</p>
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
        if ($('#announcementsTable').length) {
            $('#announcementsTable').DataTable({
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
                order: [[4, 'desc']] // مرتب‌سازی بر اساس تاریخ ایجاد
            });
        }
    });
</script>
@endpush
@endsection
