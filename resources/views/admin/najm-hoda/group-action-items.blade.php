@extends('layouts.admin')

@section('title', 'Group Action Items - ' . config('app.name', 'EarthCoop'))
@section('page-title', 'Group Action Items')
@section('page-description', 'Manage AI-generated meeting action items across groups')

@section('content')
<div class="space-y-6" style="direction: rtl;">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">برد مصوبات گروهی نجم هدا</h2>
            <p class="text-gray-600 text-sm">پیگیری وضعیت مصوبات استخراج شده از گفتگوهای گروهی</p>
        </div>
        <a href="{{ route('admin.najm-hoda.settings') }}" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm">
            بازگشت به تنظیمات
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-xs text-gray-500">کل</div>
            <div class="text-xl font-bold text-gray-800">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-xs text-gray-500">باز</div>
            <div class="text-xl font-bold text-blue-700">{{ number_format($stats['open']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-xs text-gray-500">در حال انجام</div>
            <div class="text-xl font-bold text-amber-600">{{ number_format($stats['in_progress']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-xs text-gray-500">انجام شده</div>
            <div class="text-xl font-bold text-emerald-600">{{ number_format($stats['done']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-xs text-gray-500">معوق</div>
            <div class="text-xl font-bold text-red-600">{{ number_format($stats['overdue']) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <form method="GET" action="{{ route('admin.najm-hoda.group-action-items') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="group_id" class="border rounded-lg px-3 py-2">
                <option value="">همه گروه ها</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}" {{ (string) ($filters['group_id'] ?? '') === (string) $group->id ? 'selected' : '' }}>
                        {{ $group->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="border rounded-lg px-3 py-2">
                <option value="">همه وضعیت ها</option>
                @foreach($statusOptions as $status)
                    <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>

            <select name="priority" class="border rounded-lg px-3 py-2">
                <option value="">همه اولویت ها</option>
                @foreach($priorityOptions as $priority)
                    <option value="{{ $priority }}" {{ ($filters['priority'] ?? '') === $priority ? 'selected' : '' }}>{{ $priority }}</option>
                @endforeach
            </select>

            <input
                type="text"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="جستجو در عنوان، شرح، مسئول"
                class="border rounded-lg px-3 py-2"
            />

            <button type="submit" class="bg-blue-600 text-white rounded-lg px-3 py-2 hover:bg-blue-700 transition-colors">اعمال فیلتر</button>
        </form>
    </div>

    <div id="action-items-toast" class="hidden fixed top-6 left-6 z-50 px-4 py-2 rounded-lg text-white text-sm shadow-lg"></div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-700">
                <tr>
                    <th class="px-3 py-3 text-right">ID</th>
                    <th class="px-3 py-3 text-right">گروه</th>
                    <th class="px-3 py-3 text-right">عنوان</th>
                    <th class="px-3 py-3 text-right">مسئول</th>
                    <th class="px-3 py-3 text-right">سررسید</th>
                    <th class="px-3 py-3 text-right">وضعیت / اولویت</th>
                    <th class="px-3 py-3 text-right">آخرین بروزرسانی</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr class="border-t">
                        <td class="px-3 py-3">#{{ $item->id }}</td>
                        <td class="px-3 py-3">{{ $item->group->name ?? '-' }}</td>
                        <td class="px-3 py-3">
                            <div class="font-semibold text-gray-800">{{ $item->title }}</div>
                            @if($item->details)
                                <div class="text-gray-500 text-xs mt-1">{{ \Illuminate\Support\Str::limit($item->details, 160) }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                                <input
                                    type="text"
                                    class="js-assignee-search border rounded px-2 py-1 w-48 mb-2"
                                    placeholder="جستجو مسئول..."
                                    data-select-id="assignee-select-{{ $item->id }}"
                                >
                                <select id="assignee-select-{{ $item->id }}" name="assigned_user_id" form="action-item-form-{{ $item->id }}" class="border rounded px-2 py-1 w-48">
                                    <option value="">بدون مسئول</option>
                                    @foreach(($groupUsersByGroup[$item->group_id] ?? []) as $member)
                                        <option value="{{ $member['id'] }}" {{ (int) $item->assigned_user_id === (int) $member['id'] ? 'selected' : '' }}>
                                            {{ $member['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @if(($groupUsersByGroup[$item->group_id] ?? collect())->count() === 0)
                                    <div class="text-xs text-amber-600">برای این گروه عضو فعالی یافت نشد.</div>
                                @endif
                        </td>
                        <td class="px-3 py-3">
                                <input
                                    type="datetime-local"
                                    name="due_at"
                                    form="action-item-form-{{ $item->id }}"
                                    value="{{ optional($item->due_at)->format('Y-m-d\TH:i') }}"
                                    class="border rounded px-2 py-1"
                                >
                                @if($item->due_text)
                                    <div class="text-xs text-gray-400 mt-1">{{ $item->due_text }}</div>
                                @endif
                        </td>
                        <td class="px-3 py-3">
                                <form id="action-item-form-{{ $item->id }}" method="POST" action="{{ route('admin.najm-hoda.group-action-items.update', $item) }}" class="js-action-item-form" data-item-id="{{ $item->id }}">
                                    @csrf
                                    @method('PUT')
                                <div class="flex items-center gap-2">
                                    <select name="status" class="border rounded px-2 py-1">
                                        @foreach($statusOptions as $status)
                                            <option value="{{ $status }}" {{ $item->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    <select name="priority" class="border rounded px-2 py-1">
                                        @foreach($priorityOptions as $priority)
                                            <option value="{{ $priority }}" {{ $item->priority === $priority ? 'selected' : '' }}>{{ $priority }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="js-save-btn bg-emerald-600 text-white rounded px-3 py-1 hover:bg-emerald-700 transition-colors">
                                        ذخیره
                                    </button>
                                </div>
                                </form>
                        </td>
                        <td class="px-3 py-3 text-gray-500 text-xs js-updated-at" data-item-id="{{ $item->id }}">
                            {{ $item->updated_at?->diffForHumans() }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-500">هنوز مصوبه ای ثبت نشده است.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $items->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const toast = document.getElementById('action-items-toast');

    const showToast = (message, type = 'success') => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden', 'bg-emerald-600', 'bg-red-600');
        toast.classList.add(type === 'success' ? 'bg-emerald-600' : 'bg-red-600');
        setTimeout(() => toast.classList.add('hidden'), 2200);
    };

    document.querySelectorAll('.js-action-item-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const saveBtn = form.querySelector('.js-save-btn');
            const originalLabel = saveBtn ? saveBtn.textContent : '';
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = '...';
            }

            try {
                const formData = new FormData(form);
                const csrfToken = formData.get('_token');
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new URLSearchParams(formData),
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Save failed');
                }

                const itemId = form.dataset.itemId;
                const updatedCell = document.querySelector('.js-updated-at[data-item-id="' + itemId + '"]');
                if (updatedCell) {
                    updatedCell.textContent = 'لحظاتی پیش';
                }

                showToast('مصوبه ذخیره شد', 'success');
            } catch (error) {
                showToast('خطا در ذخیره', 'error');
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalLabel;
                }
            }
        });
    });

    document.querySelectorAll('.js-assignee-search').forEach((input) => {
        input.addEventListener('input', () => {
            const selectId = input.dataset.selectId;
            const select = document.getElementById(selectId);
            if (!select) return;

            const term = (input.value || '').toLowerCase().trim();
            Array.from(select.options).forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const text = (option.textContent || '').toLowerCase();
                const isMatch = term === '' || text.includes(term);
                option.hidden = !isMatch;
            });
        });
    });
})();
</script>
@endpush
