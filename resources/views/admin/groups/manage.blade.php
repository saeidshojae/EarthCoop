@extends('layouts.admin')

@section('title', 'مدیریت گروه: ' . $group->name)

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-users-cog ml-2"></i>
                مدیریت گروه: {{ $group->name }}
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">ویرایش اطلاعات، مدیریت اعضا و پست‌های گروه</p>
        </div>
        <a href="{{ route('admin.groups.index') }}" 
           class="inline-flex items-center px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors">
            <i class="fas fa-arrow-right ml-2"></i>
            بازگشت به لیست
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @php
        $roleLabels = [
            0 => 'ناظر',
            1 => 'فعال',
            2 => 'بازرس',
            3 => 'مدیر',
            4 => 'مهمان'
        ];
    @endphp

    <!-- آمار گروه -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کل اعضا</p>
                    <p class="text-3xl font-bold">{{ number_format($users->count()) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">مدیران</p>
                    <p class="text-3xl font-bold">{{ number_format($users->filter(function($user) { return ($user->pivot->role ?? $user->pivot->main_role ?? 0) == 3; })->count()) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-user-shield text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">کل پست‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($blogs->count()) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-file-alt text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-sm mb-1">وضعیت</p>
                    <p class="text-lg font-bold">{{ $group->is_open ? 'باز' : 'بسته' }}</p>
                </div>
                <div class="bg-orange-400/20 rounded-full p-4">
                    <i class="fas {{ $group->is_open ? 'fa-unlock' : 'fa-lock' }} text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ویرایش اطلاعات گروه -->
    @hasPermission('groups.manage-settings')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-edit ml-2"></i>
            ویرایش اطلاعات گروه
        </h3>
        
        <form action="{{ route('admin.groups.update', $group) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        نام گروه <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           value="{{ old('name', $group->name) }}"
                           required
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="avatar" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        آواتار گروه
                    </label>
                    <input type="file" 
                           name="avatar" 
                           id="avatar" 
                           accept="image/*"
                           class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    @if($group->avatar)
                        <div class="mt-2">
                            <img src="{{ asset($group->avatar) }}" 
                                 alt="{{ $group->name }}" 
                                 class="h-20 w-20 rounded-lg object-cover border-2 border-slate-200 dark:border-slate-600">
                        </div>
                    @endif
                    @error('avatar')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            
            <div>
                <label for="description" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    توضیحات
                </label>
                <textarea name="description" 
                          id="description" 
                          rows="4"
                          class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">{{ old('description', $group->description) }}</textarea>
                @error('description')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div class="flex justify-end">
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
    @endhasPermission

    <!-- مدیریت اعضا -->
    @hasPermission('groups.manage-members')
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-user-friends ml-2"></i>
            مدیریت اعضای گروه
        </h3>
        
        <!-- عملیات دسته‌جمعی -->
        <form action="{{ route('admin.groups.changeRoles', $group) }}" method="POST" id="bulkActionForm" class="mb-4">
            @csrf
            @method('PUT')
            <div class="flex items-center gap-3 p-4 bg-slate-50 dark:bg-slate-900 rounded-lg">
                <select name="main_role" 
                        id="main_role"
                        class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                    <option value="">-- عملیات دسته‌جمعی --</option>
                    @foreach($roleLabels as $roleValue => $roleLabel)
                        @if($roleValue != 4)
                            <option value="{{ $roleValue }}">{{ $roleLabel }}</option>
                        @endif
                    @endforeach
                </select>
                <button type="submit" 
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-check ml-2"></i>
                    اعمال به کاربران انتخاب شده
                </button>
            </div>
        </form>
        
        <!-- جدول اعضا -->
        <div class="overflow-x-auto">
            <table class="w-full" id="membersTable">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">نام کاربر</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ایمیل</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">نقش فعلی</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تغییر نقش</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" 
                                       name="users[]" 
                                       value="{{ $user->id }}"
                                       class="user-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $user->fullName() }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $user->email }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $currentRole = isset($user->pivot) ? ($user->pivot->role ?? $user->pivot->main_role ?? 0) : 0;
                                    $roleColors = [
                                        0 => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                        1 => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        2 => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        3 => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        4 => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleColors[$currentRole] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $roleLabels[$currentRole] ?? '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form action="{{ route('admin.groups.updateRole', [$group, $user]) }}" 
                                      method="POST" 
                                      class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="role" 
                                            class="px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                                        @foreach($roleLabels as $roleValue => $roleLabel)
                                            <option value="{{ $roleValue }}" {{ ($currentRole == $roleValue) ? 'selected' : '' }}>
                                                {{ $roleLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-check text-xs"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-users text-4xl text-slate-400 mb-3"></i>
                                    <p class="text-slate-600 dark:text-slate-400">هیچ عضوی در این گروه وجود ندارد</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endhasPermission

    <!-- مدیریت پست‌های گروه -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-file-alt ml-2"></i>
            لیست پست‌های گروه
        </h3>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="postsTable">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عنوان پست</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">محتوا</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تصویر</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">دسته‌بندی</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($blogs as $blog)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <form action="{{ url('admin/group-post-update/' . $blog->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="text" 
                                           name="title" 
                                           value="{{ $blog->title }}"
                                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                                </td>
                                <td class="px-6 py-4">
                                    <textarea name="content" 
                                              rows="3"
                                              class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">{{ $blog->content }}</textarea>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="space-y-2">
                                        @if($blog->img)
                                            <img src="{{ asset('images/blogs/' . $blog->img) }}" 
                                                 alt="{{ $blog->title }}" 
                                                 class="h-20 w-20 rounded-lg object-cover border-2 border-slate-200 dark:border-slate-600">
                                        @endif
                                        <input type="file" 
                                               name="img" 
                                               accept="image/*"
                                               class="w-full px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level)->first();
                                        if($group->specialty_id != null){
                                            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_job')->first();
                                        }elseif($group->experience_id != null){
                                            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_experience')->first();
                                        }elseif($group->age_group_id != null){
                                            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_age')->first();
                                        }elseif($group->gender != null){
                                            $groupSetting = \App\Models\GroupSetting::where('level', $group->location_level . '_gender')->first();
                                        }
                                        
                                        $categoryGroupSetting = $groupSetting ? \App\Models\CategoryGroupSetting::where('group_setting_id', $groupSetting->id)->get()->pluck('category_id')->toArray() : [];
                                        $categories = $categoryGroupSetting ? \App\Models\Category::whereIn('id', $categoryGroupSetting)->get() : [];
                                    @endphp
                                    <select name="category_id" 
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
                                        <option value="">انتخاب دسته‌بندی</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ ($blog->category_id == $category->id) ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <button type="submit" 
                                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-save text-xs ml-1"></i>
                                            ویرایش
                                        </button>
                                        <a href="{{ route('admin.group.post.delete', $blog->id) }}" 
                                           onclick="return confirm('آیا مطمئن هستید که می‌خواهید این پست را حذف کنید؟')"
                                           class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors">
                                            <i class="fas fa-trash text-xs ml-1"></i>
                                            حذف
                                        </a>
                                    </div>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-file-alt text-4xl text-slate-400 mb-3"></i>
                                    <p class="text-slate-600 dark:text-slate-400">هیچ پستی در این گروه وجود ندارد</p>
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
        // DataTables initialization
        if ($('#membersTable').length) {
            $('#membersTable').DataTable({
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
                    { orderable: false, targets: [0, 4] }
                ]
            });
        }
        
        if ($('#postsTable').length) {
            $('#postsTable').DataTable({
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
                pageLength: 10,
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: [4] }
                ]
            });
        }
        
        // انتخاب همه چک‌باکس‌ها
        $('#selectAll').on('change', function() {
            $('.user-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // کنترل ارسال فرم عملیات دسته‌جمعی
        $('#bulkActionForm').on('submit', function(e) {
            let selectedUsers = $('.user-checkbox:checked');
            let selectedRole = $('#main_role').val();
            
            if (!selectedRole) {
                e.preventDefault();
                alert('لطفاً یک نقش برای اعمال دسته‌جمعی انتخاب کنید.');
                return false;
            }
            
            if (selectedUsers.length === 0) {
                e.preventDefault();
                alert('لطفاً حداقل یک کاربر را انتخاب کنید.');
                return false;
            }
            
            return confirm('آیا مطمئن هستید که می‌خواهید نقش ' + selectedUsers.length + ' کاربر را تغییر دهید؟');
        });
    });
</script>
@endpush
@endsection
