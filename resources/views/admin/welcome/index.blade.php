@extends('layouts.admin')

@section('title', $isHome ? 'مدیریت صفحه خانه' : 'مدیریت صفحه خوش‌آمد')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas {{ $isHome ? 'fa-home' : 'fa-hand-holding-heart' }} ml-2"></i>
                {{ $isHome ? 'مدیریت صفحه خانه' : 'مدیریت صفحه خوش‌آمد' }}
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                {{ $isHome ? 'مدیریت محتوا و اسلایدرهای صفحه خانه' : 'مدیریت محتوا و اسلایدرهای صفحه خوش‌آمد' }}
            </p>
        </div>
        <div class="flex items-center space-x-3 space-x-reverse">
            @if($isHome)
                <a href="{{ route('admin.welcome-page') }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-hand-holding-heart ml-2"></i>
                    صفحه خوش‌آمد
                </a>
            @else
                <a href="{{ route('admin.welcome-page', ['home' => 1]) }}" 
                   class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-home ml-2"></i>
                    صفحه خانه
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">{{ $isHome ? 'اسلایدرهای صفحه خانه' : 'اسلایدرهای صفحه خوش‌آمد' }}</p>
                    <p class="text-3xl font-bold">{{ number_format($sliders->count()) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-images text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">کل اسلایدرهای خوش‌آمد</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['welcome_sliders']) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-hand-holding-heart text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">کل اسلایدرهای خانه</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['home_sliders']) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-home text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- فرم مدیریت محتوا -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-edit ml-2"></i>
            {{ $isHome ? 'ویرایش محتوای صفحه خانه' : 'ویرایش محتوای صفحه خوش‌آمد' }}
        </h3>
        
        <form action="{{ route('admin.welcome-page.update') }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            
            @if($isHome)
                <input type="hidden" name="home" value="1">
            @endif
            
            <div>
                <label for="{{ $isHome ? 'home_titre' : 'welcome_titre' }}" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    {{ $isHome ? 'تیتر صفحه خانه' : 'تیتر صفحه خوش‌آمد' }} <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       id="{{ $isHome ? 'home_titre' : 'welcome_titre' }}" 
                       name="{{ $isHome ? 'home_titre' : 'welcome_titre' }}" 
                       value="{{ old($isHome ? 'home_titre' : 'welcome_titre', $isHome ? ($setting->home_titre ?? '') : ($setting->welcome_titre ?? '')) }}"
                       required
                       class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                       placeholder="{{ $isHome ? 'تیتر صفحه خانه را وارد کنید' : 'تیتر صفحه خوش‌آمد را وارد کنید' }}">
                @error($isHome ? 'home_titre' : 'welcome_titre')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="{{ $isHome ? 'home_content' : 'welcome_content' }}" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    {{ $isHome ? 'متن صفحه خانه' : 'متن صفحه خوش‌آمد' }} <span class="text-red-500">*</span>
                </label>
                <textarea id="{{ $isHome ? 'home_content' : 'welcome_content' }}" 
                          name="{{ $isHome ? 'home_content' : 'welcome_content' }}" 
                          rows="12"
                          required
                          class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                          placeholder="{{ $isHome ? 'متن صفحه خانه را وارد کنید' : 'متن صفحه خوش‌آمد را وارد کنید' }}">{{ old($isHome ? 'home_content' : 'welcome_content', $isHome ? ($setting->home_content ?? '') : ($setting->welcome_content ?? '')) }}</textarea>
                @error($isHome ? 'home_content' : 'welcome_content')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-slate-200 dark:border-slate-700">
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>

    <!-- فرم افزودن اسلایدر جدید -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-plus-circle ml-2"></i>
            افزودن اسلایدر جدید
        </h3>
        
        <form action="{{ route('admin.welcome-page.slider.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            
            @if($isHome)
                <input type="hidden" name="home" value="1">
                <input type="hidden" name="position" value="1">
            @else
                <input type="hidden" name="position" value="0">
            @endif
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="src" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        تصویر اسلایدر <span class="text-red-500">*</span>
                    </label>
                    <input type="file" 
                           id="src" 
                           name="src" 
                           accept="image/png,image/jpg,image/jpeg,image/webp"
                           required
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    @error('src')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">حداکثر حجم: 5 مگابایت | فرمت‌های مجاز: PNG, JPG, JPEG, WEBP</p>
                </div>

                <div>
                    <label for="alt" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        متن جایگزین (Alt)
                    </label>
                    <input type="text" 
                           id="alt" 
                           name="alt" 
                           value="{{ old('alt') }}"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="متن جایگزین تصویر">
                    @error('alt')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="link" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        لینک (اختیاری)
                    </label>
                    <input type="url" 
                           id="link" 
                           name="link" 
                           value="{{ old('link') }}"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white"
                           placeholder="https://example.com">
                    @error('link')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-slate-200 dark:border-slate-700">
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-plus ml-2"></i>
                    افزودن اسلایدر
                </button>
            </div>
        </form>
    </div>

    <!-- لیست اسلایدرها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                <i class="fas fa-images ml-2"></i>
                لیست اسلایدرها
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="slidersTable">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تصویر</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">متن جایگزین</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">لینک</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ ایجاد</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($sliders as $index => $slider)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-6 py-4">
                                <img src="{{ asset('images/sliders/' . $slider->src) }}" 
                                     alt="{{ $slider->alt ?? 'اسلایدر ' . ($index + 1) }}"
                                     class="w-32 h-24 object-cover rounded-lg cursor-pointer border border-slate-200 dark:border-slate-700"
                                     onclick="window.open('{{ asset('images/sliders/' . $slider->src) }}', '_blank')">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $slider->alt ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                @if($slider->link)
                                    <a href="{{ $slider->link }}" 
                                       target="_blank"
                                       class="text-blue-600 dark:text-blue-400 hover:underline">
                                        <i class="fas fa-external-link-alt ml-1"></i>
                                        مشاهده لینک
                                    </a>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                @php
                                    try {
                                        $date = isset($slider->created_at) ? \Carbon\Carbon::parse($slider->created_at) : null;
                                        echo $date ? \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d H:i') : '-';
                                    } catch (\Exception $e) {
                                        echo '-';
                                    }
                                @endphp
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <form action="{{ route('admin.welcome-page.slider.destroy', $slider) }}" 
                                      method="POST" 
                                      class="inline"
                                      onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این اسلایدر را حذف کنید؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors">
                                        <i class="fas fa-trash ml-1"></i>
                                        حذف
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-images text-4xl text-slate-400 mb-3"></i>
                                    <p class="text-slate-600 dark:text-slate-400">هیچ اسلایدری یافت نشد</p>
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
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script>
    // CKEditor
    CKEDITOR.replace('{{ $isHome ? 'home_content' : 'welcome_content' }}', {
        filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
        filebrowserUploadMethod: 'form',
        language: 'fa',
        height: 400,
        extraPlugins: 'uploadimage',
        removeButtons: '',
        toolbarGroups: [
            { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
            { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align' ] },
            { name: 'styles' },
            { name: 'colors' },
            { name: 'insert' },
            { name: 'tools' },
            { name: 'editing' },
            { name: 'document', groups: [ 'mode', 'document' ] },
            { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
            { name: 'links' }
        ]
    });

    // DataTables
    $(document).ready(function() {
        if ($('#slidersTable').length) {
            $('#slidersTable').DataTable({
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
                    { orderable: false, searchable: false, targets: [0, 5] }, // #, عملیات
                    { orderable: true, searchable: true, targets: [2, 3] }, // متن جایگزین, لینک
                    { orderable: true, searchable: false, targets: [4] } // تاریخ ایجاد
                ],
                order: [[4, 'desc']] // مرتب‌سازی بر اساس تاریخ ایجاد
            });
        }
    });
</script>
@endpush
@endsection
