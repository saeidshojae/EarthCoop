@extends('layouts.admin')

@section('title', 'مدیریت گروه‌ها')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-users ml-2"></i>
                مدیریت گروه‌ها
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">مدیریت و مشاهده تمام گروه‌های سیستم</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle ml-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @php
        $levels = [
            'alley' => 'کوچه',
            'street' => 'خیابان',
            'neighborhood' => 'محله',
            'region' => 'منطقه',
            'city' => 'شهر',
            'section' => 'بخش',
            'county' => 'شهرستان',
            'province' => 'استان',
            'country' => 'کشور'
        ];
        
        $sorts = [
            'experience' => 'علمی/تجربی',
            'job' => 'صنفی/شغلی',
            'age' => 'سنی',
            'gender' => 'جنسیتی',
            'total' => 'عمومی'
        ];
        
        $currentUser = request('user');
        $currentLevel = request('level');
        $currentSort = request('sort');
    @endphp

    <!-- فیلترها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-filter ml-2"></i>
            فیلترها
        </h3>
        
        <div class="space-y-4">
            <!-- فیلتر سطح -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">سطح جغرافیایی</label>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.groups.index', array_merge(request()->except('level'), ['user' => $currentUser, 'sort' => $currentSort])) }}" 
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ !$currentLevel ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                        همه
                    </a>
                    @foreach($levels as $levelKey => $levelName)
                        <a href="{{ route('admin.groups.index', array_merge(request()->except('level'), ['level' => $levelKey, 'user' => $currentUser, 'sort' => $currentSort])) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $currentLevel == $levelKey ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                            {{ $levelName }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- فیلتر نوع -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">نوع گروه</label>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.groups.index', array_merge(request()->except('sort'), ['user' => $currentUser, 'level' => $currentLevel])) }}" 
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ !$currentSort ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                        همه
                    </a>
                    @foreach($sorts as $sortKey => $sortName)
                        <a href="{{ route('admin.groups.index', array_merge(request()->except('sort'), ['sort' => $sortKey, 'user' => $currentUser, 'level' => $currentLevel])) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $currentSort == $sortKey ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
                            {{ $sortName }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- فیلتر کاربر (اختیاری) -->
            @if($currentUser)
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">فیلتر بر اساس کاربر</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-600 dark:text-slate-400">کاربر ID: {{ $currentUser }}</span>
                        <a href="{{ route('admin.groups.index', request()->except('user')) }}" 
                           class="px-3 py-1 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600 transition-colors">
                            <i class="fas fa-times ml-1"></i>
                            حذف فیلتر
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- آمار کلی -->
    @php
        $totalGroups = $groups->count();
        $activeGroups = $groups->where('is_open', 1)->count();
        $totalMembers = \App\Models\GroupUser::whereIn('group_id', $groups->pluck('id'))->distinct('user_id')->count();
    @endphp
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">کل گروه‌ها</p>
                    <p class="text-3xl font-bold">{{ number_format($totalGroups) }}</p>
                </div>
                <div class="bg-blue-400/20 rounded-full p-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">گروه‌های فعال</p>
                    <p class="text-3xl font-bold">{{ number_format($activeGroups) }}</p>
                </div>
                <div class="bg-green-400/20 rounded-full p-4">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">کل اعضا</p>
                    <p class="text-3xl font-bold">{{ number_format($totalMembers) }}</p>
                </div>
                <div class="bg-purple-400/20 rounded-full p-4">
                    <i class="fas fa-user-friends text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول گروه‌ها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                <i class="fas fa-list ml-2"></i>
                لیست گروه‌ها
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="groupsTable">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">نام</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">توضیحات</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">سطح</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تخصص</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">صنف</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">رده سنی</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">جنسیت</th>
                        @if($currentUser)
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">نقش</th>
                        @endif
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($groups as $key => $group)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">{{ $key + 1 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($group->avatar)
                                        <img src="{{ asset($group->avatar) }}" alt="{{ $group->name }}" class="h-10 w-10 rounded-full object-cover ml-3">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold ml-3">
                                            {{ mb_substr($group->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $group->name }}</div>
                                        @if($group->is_open)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 mt-1">
                                                <i class="fas fa-unlock text-xs ml-1"></i>
                                                باز
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 mt-1">
                                                <i class="fas fa-lock text-xs ml-1"></i>
                                                بسته
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-600 dark:text-slate-400 max-w-xs truncate">
                                    {{ $group->description ?? '-' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $levels[$group->location_level] ?? $group->location_level }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $group->experience_id ? ($group->experience->name ?? '-') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $group->specialty_id ? ($group->specialty->name ?? '-') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $group->age_group_id ? ($group->ageGroup->title ?? '-') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                {{ $group->gender ? ($group->gender() ?? '-') : '-' }}
                            </td>
                            @if($currentUser)
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $groupUser = \App\Models\GroupUser::where('user_id', $currentUser)->where('group_id', $group->id)->first();
                                        $roleLabels = [
                                            0 => 'ناظر',
                                            1 => 'فعال',
                                            2 => 'بازرس',
                                            3 => 'مدیر',
                                            4 => 'مهمان'
                                        ];
                                        $roleColors = [
                                            0 => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                                            1 => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            2 => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            3 => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            4 => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                        ];
                                        $role = $groupUser ? ($groupUser->role ?? $groupUser->main_role ?? null) : null;
                                    @endphp
                                    @if($role !== null)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleColors[$role] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $roleLabels[$role] ?? '-' }}
                                        </span>
                                    @else
                                        <span class="text-sm text-slate-400">-</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.groups.manage', $group) }}" 
                                       class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-cog ml-1"></i>
                                        مدیریت
                                    </a>
                                    @hasPermission('groups.manage-settings')
                                        <a href="{{ route('admin.groups.delete', $group) }}" 
                                           onclick="return confirm('آیا مطمئن هستید که می‌خواهید این گروه را حذف کنید؟')"
                                           class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors">
                                            <i class="fas fa-trash ml-1"></i>
                                            حذف
                                        </a>
                                    @endhasPermission
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $currentUser ? 10 : 9 }}" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-users text-4xl text-slate-400 mb-3"></i>
                                    <p class="text-slate-600 dark:text-slate-400">هیچ گروهی یافت نشد</p>
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
        $('#groupsTable').DataTable({
            language: {
                "decimal": "",
                "emptyTable": "هیچ داده‌ای در جدول وجود ندارد",
                "info": "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
                "infoEmpty": "نمایش 0 تا 0 از 0 رکورد",
                "infoFiltered": "(فیلتر شده از مجموع _MAX_ رکورد)",
                "infoPostFix": "",
                "thousands": ",",
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
                },
                "aria": {
                    "sortAscending": ": فعال‌سازی مرتب‌سازی صعودی",
                    "sortDescending": ": فعال‌سازی مرتب‌سازی نزولی"
                }
            },
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true
        });
    });
</script>
@endpush
@endsection
