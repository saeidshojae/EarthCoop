@extends('layouts.admin')

@section('title', 'مدیریت کدهای دعوت')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-ticket-alt ml-2"></i>
                مدیریت کدهای دعوت
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">ایجاد و مدیریت کدهای دعوت، تنظیمات و درخواست‌ها</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.invitation_codes.logs', request()->all()) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-history ml-2"></i>
                لاگ‌ها
            </a>
            <a href="{{ route('admin.invitation_codes.export', request()->all()) }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                <i class="fas fa-file-csv ml-2"></i>
                خروجی CSV
            </a>
            <a href="{{ route('admin.system-settings.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition-colors">
                <i class="fas fa-cog ml-2"></i>
                تنظیمات سیستمی
            </a>
        </div>
    </div>

    @if(isset($stats))
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-gradient-to-br from-slate-500 to-slate-600 rounded-xl p-4 text-white shadow"><div class="text-sm opacity-90">کل کدها</div><div class="text-2xl font-bold">{{ number_format($stats['total']) }}</div></div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 text-white shadow"><div class="text-sm opacity-90">فعال (استفاده‌نشده)</div><div class="text-2xl font-bold">{{ number_format($stats['active']) }}</div></div>
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-4 text-white shadow"><div class="text-sm opacity-90">استفاده‌شده</div><div class="text-2xl font-bold">{{ number_format($stats['used']) }}</div></div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-4 text-white shadow"><div class="text-sm opacity-90">منقضی</div><div class="text-2xl font-bold">{{ number_format($stats['expired']) }}</div></div>
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow"><div class="text-sm opacity-90">ایجاد امروز</div><div class="text-2xl font-bold">{{ number_format($stats['today']) }}</div></div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white shadow"><div class="text-sm opacity-90">این هفته</div><div class="text-2xl font-bold">{{ number_format($stats['week']) }}</div></div>
    </div>
    @endif

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

    <!-- Settings Form -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                <i class="fas fa-sliders-h ml-2"></i>
                تنظیمات کدهای دعوت
            </h3>
            <form action="{{ route('admin.invitation_codes.auto_invalidate') }}" method="POST" class="flex items-center gap-2">
                @csrf
                <label class="text-sm text-slate-600 dark:text-slate-300">بی‌اعتبارسازی خودکار کدهای قدیمی‌تر از</label>
                <input type="number" name="days" min="1" value="{{ old('days', 90) }}" class="w-20 px-2 py-1 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                <span class="text-sm text-slate-600 dark:text-slate-300">روز</span>
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-orange-600 text-white hover:bg-orange-700 text-sm">اجرا</button>
            </form>
        </div>

        <form action="{{ route('admin.activate.update') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @csrf
            @method('PUT')

            <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 rounded-lg p-4">
                <div>
                    <label for="invation_status" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">فعالسازی کد دعوت</label>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">در صورت فعال بودن، امکان ایجاد و استفاده از کدهای دعوت فراهم است.</p>
                </div>
                <input type="checkbox" name="invation_status" id="invation_status" class="w-5 h-5" {{ old('invation_status', \App\Models\Setting::find(1)->invation_status) == 1 ? 'checked' : '' }}>
            </div>

            <div>
                <label for="expire_invation_time" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">زمان انقضای کد (ساعت)</label>
                <input type="number" name="expire_invation_time" id="expire_invation_time" value="{{ old('expire_invation_time', \App\Models\Setting::find(1)->expire_invation_time) }}" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
            </div>

            <div>
                <label for="count_invation" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تعداد مجاز ایجاد کد</label>
                <input type="number" name="count_invation" id="count_invation" value="{{ old('count_invation', \App\Models\Setting::find(1)->count_invation) }}" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white">
            </div>

            <div class="md:col-span-3 flex items-center justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save ml-2"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>

    <!-- Create Code -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-plus-circle ml-2"></i>
            ایجاد کد دعوت جدید
        </h3>
        <form action="{{ route('admin.invitation_codes.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            @csrf
            <div class="md:col-span-2">
                <label for="code" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">کد دعوت</label>
                <input type="text" name="code" id="code" required class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-slate-700 dark:text-white" placeholder="مثال: EARTH-2025-AB12">
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors w-full md:w-auto">
                    <i class="fas fa-check ml-2"></i>
                    ایجاد کد دعوت
                </button>
            </div>
        </form>

        <div class="border-t border-slate-200 dark:border-slate-700 my-6"></div>

        <h4 class="text-base font-semibold text-slate-900 dark:text-white mb-4"><i class="fas fa-magic ml-2"></i> تولید خودکار کد دعوت</h4>
        <form action="{{ route('admin.invitation_codes.generate') }}" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تعداد</label>
                <input type="number" name="count" min="1" max="500" value="10" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">طول کد</label>
                <input type="number" name="length" min="4" max="32" value="6" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">پیشوند</label>
                <input type="text" name="prefix" placeholder="EARTH" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">پسوند</label>
                <input type="text" name="suffix" placeholder="2025" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جداکننده</label>
                <input type="text" name="separator" maxlength="1" placeholder="-" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">صادرکننده</label>
                <select name="issuer" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                    <option value="system">سیستم</option>
                    <option value="user">کاربر فعلی</option>
                </select>
            </div>
            <div class="md:col-span-6 flex items-center justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-magic ml-2"></i>
                    تولید کدها
                </button>
            </div>
        </form>
    </div>

    <!-- Tabs (Codes vs Requests) -->
    <div class="mb-4">
        <div class="inline-flex rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700">
            <a href="{{ route('admin.invitation_codes.index') }}" class="px-4 py-2 text-sm font-medium {{ request()->has('invation') ? 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300' : 'bg-blue-600 text-white' }}">کدهای دعوت</a>
            <a href="{{ route('admin.invitation_codes.index', ['invation' => 1]) }}" class="px-4 py-2 text-sm font-medium {{ request()->has('invation') ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">درخواست‌ها</a>
        </div>
    </div>

    @if(isset($_GET['invation']))
        <!-- Invitation Requests Table -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    <i class="fas fa-envelope-open-text ml-2"></i>
                    لیست درخواست‌های کد دعوت
                </h3>
                <form method="GET" action="{{ route('admin.invitation_codes.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="invation" value="1">
                    <select name="status" class="px-3 py-2 rounded-lg border dark:bg-slate-700 dark:text-white">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="pending" {{ request('status')=='pending' ? 'selected' : '' }}>در انتظار</option>
                        <option value="issued" {{ request('status')=='issued' ? 'selected' : '' }}>صادر شده</option>
                        <option value="rejected" {{ request('status')=='rejected' ? 'selected' : '' }}>رد شده</option>
                    </select>
                    <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 rounded-lg border dark:bg-slate-700 dark:text-white">
                    <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-2 rounded-lg border dark:bg-slate-700 dark:text-white">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو ایمیل" class="px-3 py-2 rounded-lg border dark:bg-slate-700 dark:text-white">
                    <button class="px-4 py-2 rounded-lg bg-blue-600 text-white">اعمال</button>
                </form>
            </div>

            <form id="reqBulkForm" action="{{ route('admin.invitation_requests.bulk') }}" method="POST" class="hidden m-4">
                @csrf
                <input type="hidden" name="action" id="reqBulkAction">
                <div class="flex items-center justify-between bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 px-4 py-3 rounded-lg">
                    <div class="text-yellow-800 dark:text-yellow-200 text-sm">
                        <span id="reqSelectedCount">0</span> درخواست انتخاب شده
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="submitReqBulk('approve')" class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">تأیید و صدور</button>
                        <button type="button" onclick="submitReqBulk('reject')" class="px-4 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700">رد</button>
                        <button type="button" onclick="submitReqBulk('delete')" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">حذف</button>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full" id="requestsTable">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th class="px-6 py-3"><input type="checkbox" id="reqSelectAll"></th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ایمیل</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">پیام</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">حرفه</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">وضعیت</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ ثبت</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ اقدام</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">اقدام‌کننده</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach(($requests ?? []) as $req)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4"><input type="checkbox" class="reqRowCheckbox" value="{{ $req->id }}"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">{{ $req->email }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $req->message ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">{{ $req->job ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($req->status == 0)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200">در انتظار</span>
                                    @elseif($req->status == 1)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">صادر شده</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200">رد شده</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">{{ optional($req->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">{{ optional($req->reviewed_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">{{ optional($req->reviewer)->fullName() ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($req->status == 0)
                                    <form action="{{ route('admin.invitation_requests.approve', $req) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">تأیید و صدور</button>
                                    </form>
                                    <form action="{{ route('admin.invitation_requests.reject', $req) }}" method="POST" class="inline" onsubmit="return confirm('رد این درخواست؟');">
                                        @csrf
                                        <input type="hidden" name="admin_note" value="-">
                                        <button type="submit" class="px-3 py-1.5 bg-orange-600 text-white rounded-lg text-sm hover:bg-orange-700">رد</button>
                                    </form>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4">{{ ($requests ?? null)?->links() }}</div>
        </div>

        <script>
            function updateReqBulkBar(){
                const n = document.querySelectorAll('.reqRowCheckbox:checked').length;
                document.getElementById('reqSelectedCount').textContent = n;
                document.getElementById('reqBulkForm').classList.toggle('hidden', n===0);
            }
            document.addEventListener('change', function(e){
                if (e.target.id==='reqSelectAll'){
                    document.querySelectorAll('.reqRowCheckbox').forEach(cb=>cb.checked=e.target.checked);
                    updateReqBulkBar();
                }
                if (e.target.classList && e.target.classList.contains('reqRowCheckbox')) updateReqBulkBar();
            });
            function submitReqBulk(action){
                const form = document.getElementById('reqBulkForm');
                document.getElementById('reqBulkAction').value = action;
                document.querySelectorAll('#reqBulkForm input[name="ids[]"]').forEach(el=>el.remove());
                Array.from(document.querySelectorAll('.reqRowCheckbox:checked')).forEach(cb=>{
                    const i = document.createElement('input');
                    i.type='hidden'; i.name='ids[]'; i.value=cb.value; form.appendChild(i);
                });
                form.submit();
            }
        </script>
    @else
        <!-- Advanced Filters -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
            <form method="GET" action="{{ route('admin.invitation_codes.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">وضعیت</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                        <option value="">همه</option>
                        <option value="unused" {{ request('status')=='unused' ? 'selected' : '' }}>استفاده نشده</option>
                        <option value="used" {{ request('status')=='used' ? 'selected' : '' }}>استفاده شده</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">صادرکننده</label>
                    <select name="issuer" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                        <option value="">همه</option>
                        <option value="system" {{ request('issuer')=='system' ? 'selected' : '' }}>سیستم</option>
                        <option value="user" {{ request('issuer')=='user' ? 'selected' : '' }}>کاربران</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">از تاریخ</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">تا تاریخ</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">جستجو در کد</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="مثال: EARTH" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                </div>
                <div class="md:col-span-5 flex items-center justify-end gap-2">
                    <a href="{{ route('admin.invitation_codes.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">پاک‌سازی</a>
                    <button class="px-4 py-2 rounded-lg bg-blue-600 text-white">اعمال فیلتر</button>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        <form id="bulkForm" action="{{ route('admin.invitation_codes.bulk') }}" method="POST" class="hidden mb-3">
            @csrf
            <input type="hidden" name="action" id="bulkAction">
            <div class="flex items-center justify-between bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 px-4 py-3 rounded-lg">
                <div class="text-yellow-800 dark:text-yellow-200 text-sm">
                    <span id="selectedCount">0</span> مورد انتخاب شده
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="submitBulk('invalidate')" class="px-4 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700">بی‌اعتبار کردن</button>
                    <button type="button" onclick="submitBulk('delete')" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">حذف</button>
                </div>
            </div>
        </form>

        <!-- Codes Table -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    <i class="fas fa-list ml-2"></i>
                    لیست کدهای دعوت
                </h3>
                <div class="flex items-center gap-2">
                    <a href='{{ route('admin.invitation_codes.index', ['filter' => 1]) }}' class='px-3 py-1.5 rounded-lg text-sm font-medium {{ request('filter', 1)==1 ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}'>کل کدها</a>
                    <a href='{{ route('admin.invitation_codes.index', ['filter' => 2]) }}' class='px-3 py-1.5 rounded-lg text-sm font-medium {{ request('filter')==2 ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}'>کدهای سیستم</a>
                    <a href='{{ route('admin.invitation_codes.index', ['filter' => 3]) }}' class='px-3 py-1.5 rounded-lg text-sm font-medium {{ request('filter')==3 ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}'>کدهای کاربران</a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full" id="codesTable">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th class="px-6 py-3"><input type="checkbox" id="selectAll"></th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">صادرکننده</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">استفاده‌کننده</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">کد</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">وضعیت</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">تاریخ صدور</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">انقضا/استفاده</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">used_at</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">اشتراک‌گذاری</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @php
                            $checkExpire = \App\Models\InvitationCode::where('used', 0)->where('expire_at', '<=', now())->where('user_id', auth()->user()->id)->orderBy('created_at', 'desc')->get();
                            foreach($checkExpire as $check){ $check->delete(); }
                        @endphp
                        @foreach($codes as $code)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4"><input type="checkbox" class="rowCheckbox" value="{{ $code->id }}"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    @if($code->user_id == 171)
                                        <span title="سیستم">سیستم</span>
                                    @elseif($code->user)
                                        <a href="{{ route('admin.users.show', $code->user->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline" title="ID: {{ $code->user->id }} | {{ $code->user->email }}">
                                            <i class="fas fa-user ml-1"></i>
                                            {{ $code->user->fullName() }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                    @if($code->used_by != null && $code->usedBy)
                                        <a href="{{ route('admin.users.show', $code->usedBy->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline" title="ID: {{ $code->usedBy->id }} | {{ $code->usedBy->email }}">
                                            <i class="fas fa-user-check ml-1"></i>
                                            {{ $code->usedBy->fullName() }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">{{ $code->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php
                                        $isExpired = isset($code->expire_at) && $code->expire_at && $code->expire_at <= now() && $code->used == 0;
                                    @endphp
                                    @if($code->used == 1)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">استفاده شده</span>
                                    @elseif($isExpired)
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200">منقضی</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">استفاده نشده</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">{{ optional($code->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                    @if($code->used == 1)
                                        <span class="text-red-600 dark:text-red-400">استفاده شده</span>
                                    @elseif(isset($code->expire_at) && $code->expire_at)
                                        <span class="{{ $code->expire_at <= now() ? 'text-orange-600 dark:text-orange-400' : 'text-slate-600 dark:text-slate-300' }}">{{ $code->expire_at->format('Y-m-d H:i') }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">{{ $code->used_at ? $code->used_at->format('Y-m-d H:i') : '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($code->user_id == 171)
                                        <button class='inline-flex items-center px-3 py-1.5 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600 transition-colors' @if($code->used == 1) disabled @else onclick="shareToSocialMedia('{{ $code->code }}')" @endif>
                                            <i class="fa fa-share-alt ml-1"></i>
                                            اشتراک
                                        </button>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if(isset($charts))
    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4"><i class="fas fa-chart-line ml-2"></i> روند روزانه 8 روز اخیر</h3>
            <canvas id="codesDailyChart" height="120"></canvas>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4"><i class="fas fa-chart-bar ml-2"></i> روند هفتگی 12 هفته اخیر</h3>
            <canvas id="codesWeeklyChart" height="120"></canvas>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4"><i class="fas fa-chart-area ml-2"></i> روند ماهانه 12 ماه اخیر</h3>
            <canvas id="codesMonthlyChart" height="120"></canvas>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function shareToSocialMedia(code) {
        var url = "https://earthcoop.info?code=" + code;
        if (navigator.share) {
            navigator.share({ title: 'دعوت از دوستان', text: 'به EarthCoop بپیوندید', url: url }).catch(() => {});
        } else {
            navigator.clipboard.writeText(url).then(() => alert('لینک با موفقیت کپی شد!')).catch(() => alert('خطا در کپی کردن لینک'));
        }
    }

    function updateBulkBar() {
        const selected = document.querySelectorAll('#codesTable tbody .rowCheckbox:checked');
        const bulkForm = document.getElementById('bulkForm');
        if (!bulkForm) return;
        document.getElementById('selectedCount').textContent = selected.length;
        bulkForm.classList.toggle('hidden', selected.length === 0);
    }

    function submitBulk(action) {
        const ids = Array.from(document.querySelectorAll('#codesTable tbody .rowCheckbox:checked')).map(cb => cb.value);
        if (ids.length === 0) return;
        const form = document.getElementById('bulkForm');
        document.getElementById('bulkAction').value = action;
        document.querySelectorAll('#bulkForm input[name="ids[]"]').forEach(el => el.remove());
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
        form.submit();
    }

    document.addEventListener('change', function(e) {
        const target = e.target;
        if (!target || target.type !== 'checkbox') return;
        if (target.id === 'selectAll') {
            document.querySelectorAll('#codesTable tbody .rowCheckbox').forEach(cb => { cb.checked = target.checked; });
        }
        if (target.classList && target.classList.contains('rowCheckbox')) {
            const all = document.querySelectorAll('#codesTable tbody .rowCheckbox');
            const checked = document.querySelectorAll('#codesTable tbody .rowCheckbox:checked');
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = all.length > 0 && checked.length === all.length;
        }
        updateBulkBar();
    });

    document.addEventListener('DOMContentLoaded', updateBulkBar);
</script>
@endpush
@endsection
