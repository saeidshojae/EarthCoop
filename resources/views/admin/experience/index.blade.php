@extends('layouts.admin')

@section('title', 'تأیید تخصص‌ها و صنف‌های جدید')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-briefcase ml-2"></i>
                تأیید تخصص‌ها و صنف‌های جدید
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                بررسی و تأیید تخصص‌ها و صنف‌های جدید اضافه شده توسط کاربران
            </p>
        </div>
        <button onclick="openCreateModal()" 
                class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
            <i class="fas fa-plus ml-2"></i>
            ایجاد تخصص/صنف جدید
        </button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
            <span class="text-green-800 dark:text-green-200">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6 flex items-center gap-3">
            <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400"></i>
            <span class="text-red-800 dark:text-red-200">{{ session('error') }}</span>
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کل</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-list text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm mb-1">در انتظار</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['pending']) }}</p>
                </div>
                <div class="bg-yellow-400/20 rounded-full p-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">تأیید شده</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['approved']) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">تخصص</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['experience']) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-user-graduate text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-teal-100 text-sm mb-1">صنف</p>
                    <p class="text-3xl font-bold">{{ number_format($stats['occupational']) }}</p>
                </div>
                <div class="bg-teal-400/20 rounded-full p-4">
                    <i class="fas fa-building text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- فیلترها و جستجو -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" action="{{ route('admin.active.experience') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- جستجو -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="جستجو در نام تخصص یا صنف..."
                           class="w-full px-4 py-2.5 pr-10 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                </div>
            </div>

            <!-- فیلتر نوع -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">نوع</label>
                <select name="type" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="all" {{ request('type', 'all') == 'all' ? 'selected' : '' }}>همه</option>
                    <option value="experience" {{ request('type') == 'experience' ? 'selected' : '' }}>تخصص</option>
                    <option value="occupational" {{ request('type') == 'occupational' ? 'selected' : '' }}>صنف</option>
                </select>
            </div>
            
            <!-- فیلتر وضعیت -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                <select name="status" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>همه</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>در انتظار تأیید</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>تأیید شده</option>
                </select>
            </div>

            <!-- دکمه‌های فیلتر -->
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <i class="fas fa-filter ml-2"></i>
                    اعمال فیلتر
                </button>
                <a href="{{ route('admin.active.experience') }}" class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    پاک‌سازی
                </a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <form id="bulkForm" action="{{ route('admin.active.experience.bulk.approve') }}" method="POST" class="hidden mb-6">
        @csrf
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 flex items-center justify-between">
            <div class="text-yellow-800 dark:text-yellow-200 text-sm font-semibold">
                <span id="selectedCount">0</span> آیتم انتخاب شده
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="submitBulk('approve')" class="px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">تأیید</button>
                <button type="button" onclick="submitBulk('delete')" class="px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">حذف</button>
            </div>
        </div>
    </form>

    <!-- جدول تخصص‌ها و صنف‌ها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right">
                            <input type="checkbox" id="selectAll" class="size-4 text-blue-600 focus:ring-blue-500 rounded">
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">نام</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">نوع</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">والدها</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($query as $key => $item)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" 
                                       name="items[]" 
                                       value="{{ $item->table_name }}_{{ $item->id }}"
                                       class="rowCheckbox size-4 text-blue-600 focus:ring-blue-500 rounded">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">
                                {{ $key + 1 }}
                            </td>
                            <td class="px-6 py-4">
                                <form action="{{ route('admin.active.experience.update', $item->id) }}" method="POST" class="inline-form-{{ $item->id }}" onsubmit="event.preventDefault(); updateName({{ $item->id }}, '{{ $item->table_name }}');">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="table" value="{{ $item->table_name }}">
                                    <input type="text" 
                                           name="name" 
                                           value="{{ $item->name }}"
                                           class="w-full px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                                           id="name-input-{{ $item->id }}">
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $item->type_label == 'تخصص' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200' : 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200' }}">
                                    {{ $item->type_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $parents = \App\Http\Controllers\Admin\ExperienceController::getParents($item);
                                @endphp
                                @if(count($parents) > 0)
                                    <div class="text-xs text-slate-600 dark:text-slate-400 space-y-1">
                                        @foreach($parents as $index => $parent)
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-chevron-left text-slate-400"></i>
                                                <span>{{ $parent['label'] }} {{ count($parents) > 1 ? ($index + 1) : '' }}: <strong>{{ $parent['name'] }}</strong></span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">فاقد والد</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item->status == 1)
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                        <i class="fas fa-check-circle ml-1"></i>
                                        تأیید شده
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">
                                        <i class="fas fa-clock ml-1"></i>
                                        در انتظار تأیید
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <button onclick="updateName({{ $item->id }}, '{{ $item->table_name }}')" 
                                            class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                        <i class="fas fa-save ml-1"></i>
                                        ذخیره
                                    </button>
                                    @if($item->status == 0)
                                    <a href="{{ route('admin.active.experience.edit', $item->id) }}?table={{ $item->table_name }}" 
                                       class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                        <i class="fas fa-check ml-1"></i>
                                        تأیید
                                    </a>
                                    @endif
                                    <a href="{{ route('admin.active.experience.delete', $item->id) }}?table={{ $item->table_name }}" 
                                       onclick="return confirm('آیا مطمئن هستید که می‌خواهید این آیتم را حذف کنید؟')"
                                       class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                                        <i class="fas fa-trash ml-1"></i>
                                        حذف
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                آیتمی یافت نشد
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal ایجاد جدید -->
<div id="createModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeCreateModal()"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-2xl w-full p-6 transform transition-all">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">
                    <i class="fas fa-plus-circle ml-2"></i>
                    ایجاد تخصص/صنف جدید
                </h3>
                <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="createForm" action="{{ route('admin.active.experience.store') }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <!-- نوع -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            نوع <span class="text-red-500">*</span>
                        </label>
                        <select name="table" 
                                id="createTable"
                                required
                                onchange="updateParentOptions()"
                                class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                            <option value="">انتخاب کنید</option>
                            <option value="experience_fields">تخصص</option>
                            <option value="occupational_fields">صنف</option>
                        </select>
                    </div>

                    <!-- نام -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            نام <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="createName"
                               required
                               placeholder="مثال: برنامه‌نویسی"
                               class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                    </div>

                    <!-- والد -->
                    <div id="parentField" style="display: none;">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            والد
                        </label>
                        <select name="parent_id" 
                                id="createParentId"
                                class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                            <option value="">بدون والد</option>
                        </select>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">در صورت نیاز می‌توانید یک والد انتخاب کنید</p>
                    </div>
                </div>

                <!-- دکمه‌های فرم -->
                <div class="flex items-center justify-end gap-3 mt-6">
                    <button type="button" 
                            onclick="closeCreateModal()"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                        <i class="fas fa-times ml-2"></i>
                        انصراف
                    </button>
                    <button type="submit" 
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-save ml-2"></i>
                        ایجاد
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
            alert('لطفاً حداقل یک آیتم را انتخاب کنید');
            return;
        }

        if (action === 'delete' && !confirm(`آیا مطمئن هستید که می‌خواهید ${checked.length} آیتم را حذف کنید؟`)) {
            return;
        }

        if (action === 'approve' && !confirm(`آیا مطمئن هستید که می‌خواهید ${checked.length} آیتم را تأیید کنید؟`)) {
            return;
        }

        // Get selected items
        const items = Array.from(checked).map(cb => cb.value);
        
        // Determine URL
        const url = action === 'delete' 
            ? '{{ route("admin.active.experience.bulk.delete") }}'
            : '{{ route("admin.active.experience.bulk.approve") }}';

        // Create form data
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        items.forEach(item => {
            formData.append('items[]', item);
        });

        // Show loading
        const bulkBar = document.getElementById('bulkForm');
        const originalHTML = bulkBar.innerHTML;
        bulkBar.innerHTML = '<div class="text-yellow-800 dark:text-yellow-200 text-sm font-semibold">در حال پردازش...</div>';

        // Send request
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message with SweetAlert
                Swal.fire({
                    icon: 'success',
                    title: 'موفق',
                    text: data.message,
                    confirmButtonText: 'باشه',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: data.message || 'عملیات با خطا مواجه شد',
                    confirmButtonText: 'باشه',
                    confirmButtonColor: '#ef4444'
                });
                bulkBar.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطا',
                text: 'خطا در ارسال درخواست',
                confirmButtonText: 'باشه',
                confirmButtonColor: '#ef4444'
            });
            bulkBar.innerHTML = originalHTML;
        });
    }

    // Update name function
    function updateName(id, table) {
        const input = document.getElementById('name-input-' + id);
        const name = input.value.trim();
        
        if (!name) {
            Swal.fire({
                icon: 'warning',
                title: 'هشدار',
                text: 'نام نمی‌تواند خالی باشد',
                confirmButtonText: 'باشه',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        const form = document.querySelector('.inline-form-' + id);
        const formData = new FormData(form);

        // Show loading
        Swal.fire({
            title: 'در حال پردازش...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                return response.json();
            }
        })
        .then(data => {
            if (data) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: data.message || 'خطا در به‌روزرسانی',
                    confirmButtonText: 'باشه',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // If redirect happened, page will reload automatically
        });
    }

    // Auto-hide success message with SweetAlert
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'موفق',
            text: '{{ session('success') }}',
            confirmButtonText: 'باشه',
            confirmButtonColor: '#10b981',
            timer: 3000,
            timerProgressBar: true
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'خطا',
            text: '{{ session('error') }}',
            confirmButtonText: 'باشه',
            confirmButtonColor: '#ef4444'
        });
    @endif

    // Create Modal Functions
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
        document.getElementById('createForm').reset();
        document.getElementById('parentField').style.display = 'none';
        updateParentOptions();
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
    }

    // Update parent options based on selected table type
    async function updateParentOptions() {
        const table = document.getElementById('createTable').value;
        const parentField = document.getElementById('parentField');
        const parentSelect = document.getElementById('createParentId');

        // Reset
        parentField.style.display = 'none';
        parentSelect.innerHTML = '<option value="">بدون والد</option>';

        if (!table) return;

        // نمایش فیلد والد
        parentField.style.display = 'block';

        // بارگذاری والدهای موجود
        try {
            const response = await fetch(`/admin/active-experience/parents/${table}`);
            if (response.ok) {
                const data = await response.json();
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    parentSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading parents:', error);
        }
    }

    // Close modal on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCreateModal();
        }
    });
</script>
@endpush
@endsection
