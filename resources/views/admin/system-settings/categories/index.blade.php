@extends('layouts.admin')

@section('title', 'مدیریت دسته‌بندی‌ گروه‌ها')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-folders text-purple-500"></i>
                مدیریت دسته‌بندی‌ها
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1 max-w-2xl">
                دسته‌بندی‌های قابل استفاده در پست‌های گروهی را مدیریت کنید و برای هر سطح یا نوع گروه، موضوعات اختصاصی تعریف کنید.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 flex items-center gap-3">
            <i class="fas fa-check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3">
            <ul class="list-disc pr-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">تعداد کل دسته‌بندی‌ها</p>
            <p class="text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">فعال برای همه گروه‌ها</p>
            <p class="text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['global']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">دسته‌بندی‌های اختصاصی</p>
            <p class="text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['custom']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex flex-col gap-4 mb-6 lg:flex-row lg:items-center lg:justify-between">
                    <form method="GET" class="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <div class="flex items-center bg-slate-100 dark:bg-slate-700 rounded-xl px-3 py-2 w-full lg:w-64">
                            <i class="fas fa-search text-slate-400 ml-2"></i>
                            <input type="text" name="q" value="{{ $filters['search'] }}"
                                   class="bg-transparent w-full focus:outline-none text-sm dark:text-white"
                                   placeholder="جستجو بر اساس نام دسته">
                        </div>
                        <div class="bg-slate-100 dark:bg-slate-700 rounded-xl px-3 py-2">
                            <select name="group" class="bg-transparent focus:outline-none text-sm dark:text-white">
                                <option value="any" {{ $filters['group'] === 'any' ? 'selected' : '' }}>همه گروه‌ها</option>
                                <option value="all" {{ $filters['group'] === 'all' ? 'selected' : '' }}>دسته‌های فعال برای همه</option>
                                <option value="custom" {{ $filters['group'] === 'custom' ? 'selected' : '' }}>فقط دسته‌های اختصاصی</option>
                                @foreach($groupSettings as $setting)
                                    <option value="{{ $setting->id }}" {{ (string)$filters['group'] === (string)$setting->id ? 'selected' : '' }}>
                                        {{ $setting->name() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            اعمال فیلتر
                        </button>
                    </form>
                    <div>
                        <a href="{{ route('admin.categories.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                            پاک‌سازی فیلترها
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead>
                            <tr class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3 text-right">نام دسته</th>
                                <th class="px-4 py-3 text-right">گروه‌های هدف</th>
                                <th class="px-4 py-3 text-right">ایجاد</th>
                                <th class="px-4 py-3 text-left">اقدامات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                            @forelse($categories as $category)
                                @php
                                    $appliesToAll = $category->appliesToAllGroups();
                                    $groupLabels = $appliesToAll
                                        ? collect(['همه گروه‌ها'])
                                        : $category->groupSettings->map(fn($gs) => $gs->name());
                                @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                    <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                        {{ $category->name }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($groupLabels as $label)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium 
                                                    {{ $label === 'همه گروه‌ها' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
                                                    {{ $label }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                        {{ $category->created_at ? verta($category->created_at)->format('Y/n/j') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-left">
                                        <div class="flex items-center gap-3 justify-end">
                                            <button type="button"
                                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 text-sm font-semibold"
                                                    data-edit-category
                                                    data-action="{{ route('admin.categories.update', $category) }}"
                                                    data-category='@json([
                                                        'id' => $category->id,
                                                        'name' => $category->name,
                                                        'groups' => $category->groupSettingLinks->pluck('group_setting_id')->map(fn($id) => (int)$id)->values()
                                                    ])'>
                                                <i class="fas fa-pen ml-1"></i>ویرایش
                                            </button>
                                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST"
                                                  onsubmit="return confirm('آیا از حذف این دسته‌بندی مطمئن هستید؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm font-semibold">
                                                    <i class="fas fa-trash ml-1"></i>حذف
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">
                                        هیچ دسته‌بندی‌ای پیدا نشد.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $categories->links() }}
                </div>
            </div>
        </div>

        <div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 sticky top-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-green-500"></i>
                    ایجاد دسته‌بندی جدید
                </h2>
                <form action="{{ route('admin.categories.store') }}" method="POST" class="space-y-4" data-group-selector>
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">نام دسته</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="form-input w-full" placeholder="مثلاً: معرفی پروژه‌ها">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">گروه‌های قابل استفاده</label>
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1 custom-scroll">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-700/50 rounded-lg px-3 py-2">
                                <input type="checkbox" name="groups[]" value="0" class="size-4 text-green-600 focus:ring-green-500">
                                <span>فعال برای تمام گروه‌ها</span>
                            </label>
                            @foreach($groupSettings as $setting)
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2">
                                    <input type="checkbox" name="groups[]" value="{{ $setting->id }}" class="size-4 text-blue-600 focus:ring-blue-500">
                                    <span>{{ $setting->name() }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">برای فعال‌سازی در همه گروه‌ها، گزینه «فعال برای تمام گروه‌ها» را انتخاب کنید.</p>
                    </div>
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        ثبت دسته‌بندی
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="categoryEditModal" class="fixed inset-0 bg-slate-900/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl max-w-2xl w-full p-6 relative">
        <button type="button" class="absolute top-4 left-4 text-slate-500 hover:text-slate-700" data-close-modal>
            <i class="fas fa-times text-lg"></i>
        </button>
        <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">ویرایش دسته‌بندی</h3>
        <form method="POST" id="editCategoryForm" data-group-selector>
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">نام دسته</label>
                    <input type="text" name="name" id="editCategoryName" class="form-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">گروه‌های قابل استفاده</label>
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1 custom-scroll" id="editCategoryGroups">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-700/50 rounded-lg px-3 py-2">
                            <input type="checkbox" name="groups[]" value="0" class="size-4 text-green-600 focus:ring-green-500">
                            <span>فعال برای تمام گروه‌ها</span>
                        </label>
                        @foreach($groupSettings as $setting)
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2">
                                <input type="checkbox" name="groups[]" value="{{ $setting->id }}" class="size-4 text-blue-600 focus:ring-blue-500">
                                <span>{{ $setting->name() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 rounded-xl hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400" data-close-modal>انصراف</button>
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .custom-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scroll::-webkit-scrollbar-thumb {
        background-color: rgba(148, 163, 184, 0.6);
        border-radius: 999px;
    }
    .custom-scroll::-webkit-scrollbar-track {
        background-color: transparent;
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('categoryEditModal');
        const editButtons = document.querySelectorAll('[data-edit-category]');
        const editForm = document.getElementById('editCategoryForm');
        const nameInput = document.getElementById('editCategoryName');

        function openModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        editButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const data = JSON.parse(btn.getAttribute('data-category'));
                nameInput.value = data.name;
                editForm.action = btn.getAttribute('data-action');

                const groups = data.groups || [];
                editForm.querySelectorAll('input[name="groups[]"]').forEach(input => {
                    const value = parseInt(input.value, 10);
                    input.checked = groups.includes(value);
                });

                enforceGroupSelection();

                openModal();
            });
        });

        function enforceGroupSelection() {
            document.querySelectorAll('[data-group-selector]').forEach(container => {
                const checkboxes = Array.from(container.querySelectorAll('input[name="groups[]"]'));
                const allCheckbox = checkboxes.find(cb => parseInt(cb.value, 10) === 0);
                const others = checkboxes.filter(cb => parseInt(cb.value, 10) !== 0);

                if (!allCheckbox) return;

                allCheckbox.addEventListener('change', () => {
                    if (allCheckbox.checked) {
                        others.forEach(cb => cb.checked = true);
                    } else {
                        others.forEach(cb => cb.checked = false);
                    }
                });

                others.forEach(cb => {
                    cb.addEventListener('change', () => {
                        if (cb.checked === false) {
                            const anyChecked = others.some(other => other.checked);
                            if (!anyChecked) {
                                allCheckbox.checked = true;
                                others.forEach(other => other.checked = true);
                            }
                        } else {
                            allCheckbox.checked = false;
                        }
                    });
                });
            });
        }

        enforceGroupSelection();
    })();
</script>
@endpush

