@extends('layouts.admin')

@section('title', 'مدیریت تیکت‌های پشتیبانی')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-ticket-alt ml-2"></i>
                مدیریت تیکت‌های پشتیبانی
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">
                مدیریت و پیگیری تیکت‌های پشتیبانی کاربران
            </p>
        </div>
        <a href="{{ route('admin.tickets.export', request()->all()) }}" 
           class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
            <i class="fas fa-download ml-2"></i>
            خروجی CSV
        </a>
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">کل تیکت‌ها</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <i class="fas fa-ticket-alt text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">باز</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['open']) }}</p>
                </div>
                <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                    <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">در حال بررسی</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['in_progress']) }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <i class="fas fa-spinner text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">بسته شده</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['closed']) }}</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">اولویت بالا</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['high_priority']) }}</p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- نمودار روند -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
            <i class="fas fa-chart-line ml-2"></i>
            روند تیکت‌ها (12 ماه گذشته)
        </h3>
        <canvas id="ticketsChart" height="80"></canvas>
    </div>

    <!-- فیلترها و جستجو -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <form method="GET" action="{{ route('admin.tickets.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- جستجو -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">جستجو</label>
                    <input type="text" 
                           name="q" 
                           value="{{ request('q') }}"
                           placeholder="کد پیگیری، موضوع، ایمیل..."
                           class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                </div>

                <!-- وضعیت -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">وضعیت</label>
                    <select name="status" 
                            class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>باز</option>
                        <option value="in-progress" {{ request('status') == 'in-progress' ? 'selected' : '' }}>در حال بررسی</option>
                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>بسته شده</option>
                    </select>
                </div>

                <!-- اولویت -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">اولویت</label>
                    <select name="priority" 
                            class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                        <option value="">همه اولویت‌ها</option>
                        <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>بالا</option>
                        <option value="normal" {{ request('priority') == 'normal' ? 'selected' : '' }}>عادی</option>
                        <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>پایین</option>
                    </select>
                </div>

                <!-- مسئول -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">مسئول</label>
                    <select name="assignee_id" 
                            class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                        <option value="">همه مسئولان</option>
                        @foreach($assignees as $assignee)
                            <option value="{{ $assignee->id }}" {{ request('assignee_id') == $assignee->id ? 'selected' : '' }}>
                                {{ $assignee->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- تاریخ از -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">از تاریخ</label>
                    <input type="date" 
                           name="from" 
                           value="{{ request('from') }}"
                           class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                </div>

                <!-- تاریخ تا -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">تا تاریخ</label>
                    <input type="date" 
                           name="to" 
                           value="{{ request('to') }}"
                           class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.tickets.index') }}" 
                   class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times ml-2"></i>
                    پاک کردن فیلترها
                </a>
                <button type="submit" 
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <i class="fas fa-search ml-2"></i>
                    جستجو
                </button>
            </div>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <div id="bulkForm" class="hidden fixed bottom-0 left-0 right-0 bg-blue-600 text-white p-4 shadow-lg z-50">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <span id="selectedCount" class="font-semibold">0 تیکت انتخاب شده</span>
                <select id="bulkAction" class="px-4 py-2 bg-white text-slate-900 rounded-lg">
                    <option value="">انتخاب عملیات...</option>
                    <option value="close">بستن</option>
                    <option value="delete">حذف</option>
                    <option value="assign">اختصاص مسئول</option>
                    <option value="change_status">تغییر وضعیت</option>
                </select>
                <select id="bulkAssignee" class="hidden px-4 py-2 bg-white text-slate-900 rounded-lg">
                    <option value="">انتخاب مسئول...</option>
                    @foreach($assignees as $assignee)
                        <option value="{{ $assignee->id }}">{{ $assignee->fullName() }}</option>
                    @endforeach
                </select>
                <select id="bulkStatus" class="hidden px-4 py-2 bg-white text-slate-900 rounded-lg">
                    <option value="">انتخاب وضعیت...</option>
                    <option value="open">باز</option>
                    <option value="in-progress">در حال بررسی</option>
                    <option value="closed">بسته شده</option>
                </select>
                <button onclick="submitBulk()" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg font-semibold">
                    اجرا
                </button>
            </div>
            <button onclick="closeBulkBar()" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-semibold">
                <i class="fas fa-times ml-1"></i>
                بستن
            </button>
        </div>
    </div>

    <!-- جدول تیکت‌ها -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">کد پیگیری</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">موضوع</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">کاربر</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">وضعیت</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">اولویت</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">مسئول</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" 
                                       class="rowCheckbox rounded border-slate-300 text-blue-600 focus:ring-blue-500" 
                                       value="{{ $ticket->id }}"
                                       onchange="updateBulkBar()">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-mono text-slate-900 dark:text-white">{{ $ticket->tracking_code }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ Str::limit($ticket->subject, 50) }}</div>
                                @if($ticket->message)
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ Str::limit($ticket->message, 80) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-900 dark:text-white">
                                    {{ $ticket->user ? $ticket->user->fullName() : ($ticket->name ?? '-') }}
                                </div>
                                @if($ticket->email)
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $ticket->email }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($ticket->status == 'open')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">
                                        <i class="fas fa-clock ml-1"></i>
                                        باز
                                    </span>
                                @elseif($ticket->status == 'in-progress')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                        <i class="fas fa-spinner ml-1"></i>
                                        در حال بررسی
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                        <i class="fas fa-check-circle ml-1"></i>
                                        بسته شده
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($ticket->priority == 'high')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">
                                        <i class="fas fa-exclamation-triangle ml-1"></i>
                                        بالا
                                    </span>
                                @elseif($ticket->priority == 'low')
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/40 dark:text-gray-200">
                                        <i class="fas fa-arrow-down ml-1"></i>
                                        پایین
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                        عادی
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                {{ $ticket->assignee ? $ticket->assignee->fullName() : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($ticket->created_at)->format('Y/m/d H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('admin.tickets.show', $ticket->id) }}" 
                                   class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                    <i class="fas fa-eye ml-1"></i>
                                    مشاهده
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="text-slate-500 dark:text-slate-400">
                                    <i class="fas fa-inbox text-4xl mb-4"></i>
                                    <p class="text-lg font-medium">تیکتی یافت نشد</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($tickets->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Chart
    const ctx = document.getElementById('ticketsChart');
    if (ctx) {
        const chartData = @json($chartData);
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.month),
                datasets: [
                    {
                        label: 'باز',
                        data: chartData.map(d => d.open),
                        borderColor: 'rgb(234, 179, 8)',
                        backgroundColor: 'rgba(234, 179, 8, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'بسته شده',
                        data: chartData.map(d => d.closed),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Bulk Actions
    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.rowCheckbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateBulkBar();
    }

    function updateBulkBar() {
        const checkboxes = document.querySelectorAll('.rowCheckbox:checked');
        const bulkForm = document.getElementById('bulkForm');
        const selectedCount = document.getElementById('selectedCount');
        const bulkAction = document.getElementById('bulkAction');
        const bulkAssignee = document.getElementById('bulkAssignee');
        const bulkStatus = document.getElementById('bulkStatus');

        if (checkboxes.length > 0) {
            bulkForm.classList.remove('hidden');
            selectedCount.textContent = `${checkboxes.length} تیکت انتخاب شده`;
        } else {
            bulkForm.classList.add('hidden');
        }

        // نمایش/مخفی کردن فیلدهای اضافی
        bulkAssignee.classList.add('hidden');
        bulkStatus.classList.add('hidden');
        
        if (bulkAction.value === 'assign') {
            bulkAssignee.classList.remove('hidden');
        } else if (bulkAction.value === 'change_status') {
            bulkStatus.classList.remove('hidden');
        }
    }

    function closeBulkBar() {
        document.getElementById('bulkForm').classList.add('hidden');
        document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
    }

    function submitBulk() {
        const checkboxes = document.querySelectorAll('.rowCheckbox:checked');
        const ticketIds = Array.from(checkboxes).map(cb => cb.value);
        const action = document.getElementById('bulkAction').value;
        const assigneeId = document.getElementById('bulkAssignee').value;
        const status = document.getElementById('bulkStatus').value;

        if (!action) {
            Swal.fire({
                icon: 'warning',
                title: 'هشدار',
                text: 'لطفا عملیات را انتخاب کنید',
                confirmButtonText: 'باشه',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        if (action === 'assign' && !assigneeId) {
            Swal.fire({
                icon: 'warning',
                title: 'هشدار',
                text: 'لطفا مسئول را انتخاب کنید',
                confirmButtonText: 'باشه',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        if (action === 'change_status' && !status) {
            Swal.fire({
                icon: 'warning',
                title: 'هشدار',
                text: 'لطفا وضعیت را انتخاب کنید',
                confirmButtonText: 'باشه',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        Swal.fire({
            title: 'آیا مطمئن هستید؟',
            text: `این عملیات روی ${ticketIds.length} تیکت اعمال خواهد شد`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'بله، انجام بده',
            cancelButtonText: 'انصراف'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', action);
                ticketIds.forEach(id => {
                    formData.append('ticket_ids[]', id);
                });
                if (assigneeId) formData.append('assignee_id', assigneeId);
                if (status) formData.append('status', status);

                fetch('{{ route("admin.tickets.bulk-action") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
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
                });
            }
        });
    }

    // Update bulk bar when action changes
    document.getElementById('bulkAction').addEventListener('change', updateBulkBar);
</script>
@endpush
@endsection
