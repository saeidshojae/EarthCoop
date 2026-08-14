@extends('layouts.admin')

@section('title', 'مدیریت سراسری نقش اعضای گروه‌ها')

@section('content')
<div class="container-fluid px-4 py-6" dir="rtl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="fas fa-users-cog ml-2 text-emerald-600"></i>
                مدیریت سراسری نقش اعضای گروه‌ها
            </h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1">تغییر زمان‌دار نقش اعضا در چند گروه بر اساس نوع و سطح گروه</p>
        </div>
        <a href="{{ route('admin.groups.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600">
            <i class="fas fa-arrow-right"></i> بازگشت به مدیریت گروه‌ها
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <section class="xl:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
            <h2 class="text-lg font-bold mb-5">تعریف عملیات جدید</h2>
            <form id="globalRoleForm" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold mb-2" for="group_category">نوع گروه‌ها</label>
                    <select id="group_category" name="group_category" class="w-full rounded-xl border-slate-300 dark:bg-slate-700">
                        <option value="all">همه انواع</option>
                        <option value="general">فقط عمومی</option>
                        <option value="specialized">فقط تخصصی</option>
                        <option value="exclusive">فقط اختصاصی</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2" for="location_level">سطح گروه‌ها</label>
                    <select id="location_level" name="location_level" class="w-full rounded-xl border-slate-300 dark:bg-slate-700">
                        <option value="">همه سطوح</option>
                        @foreach([
                            'global' => 'جهانی', 'continent' => 'قاره', 'country' => 'کشور', 'province' => 'استان',
                            'county' => 'شهرستان', 'section' => 'بخش', 'city' => 'شهر', 'rural' => 'دهستان',
                            'region' => 'منطقه', 'village' => 'روستا', 'neighborhood' => 'محله',
                            'street' => 'خیابان', 'alley' => 'کوچه'
                        ] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2" for="source_role">نقش فعلی اعضا</label>
                    <select id="source_role" name="source_role" class="w-full rounded-xl border-slate-300 dark:bg-slate-700">
                        <option value="0">ناظر</option>
                        <option value="1">فعال</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2" for="target_role">نقش مقصد</label>
                    <select id="target_role" name="target_role" class="w-full rounded-xl border-slate-300 dark:bg-slate-700">
                        <option value="1">فعال</option>
                        <option value="0">ناظر</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2" for="duration_value">مدت</label>
                    <input id="duration_value" name="duration_value" type="number" min="1" max="31" value="1" class="w-full rounded-xl border-slate-300 dark:bg-slate-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2" for="duration_unit">واحد مدت</label>
                    <select id="duration_unit" name="duration_unit" class="w-full rounded-xl border-slate-300 dark:bg-slate-700">
                        <option value="day">روز</option>
                        <option value="month">ماه</option>
                        <option value="unlimited">بدون محدودیت زمانی</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-2">در حالت ماه، حداکثر ۱۲ ماه مجاز است.</p>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                    <button type="button" id="previewButton" class="px-5 py-3 rounded-xl bg-slate-700 text-white font-bold">
                        <i class="fas fa-search ml-1"></i> پیش‌نمایش عملیات
                    </button>
                    <button type="submit" id="startButton" disabled class="px-5 py-3 rounded-xl bg-emerald-600 text-white font-bold disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-play ml-1"></i> تأیید و شروع
                    </button>
                </div>
            </form>
            <div id="formError" class="hidden mt-4 p-4 rounded-xl bg-red-50 text-red-700 border border-red-200"></div>
        </section>

        <aside class="space-y-6">
            <div id="previewCard" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h2 class="font-bold mb-4">پیش‌نمایش اثر عملیات</h2>
                <p id="previewHint" class="text-sm text-slate-500">ابتدا فیلترها را انتخاب و پیش‌نمایش را اجرا کنید.</p>
                <div id="previewStats" class="hidden grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-blue-50 p-3"><div class="text-xs text-blue-700">گروه‌ها</div><strong id="previewGroups">۰</strong></div>
                    <div class="rounded-xl bg-violet-50 p-3"><div class="text-xs text-violet-700">عضویت‌ها</div><strong id="previewMembers">۰</strong></div>
                    <div class="rounded-xl bg-emerald-50 p-3"><div class="text-xs text-emerald-700">تغییر جدید</div><strong id="previewApply">۰</strong></div>
                    <div class="rounded-xl bg-amber-50 p-3"><div class="text-xs text-amber-700">لغو تغییر قبلی</div><strong id="previewCancel">۰</strong></div>
                </div>
            </div>

            <div id="progressCard" class="hidden bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h2 class="font-bold mb-4">وضعیت اجرا</h2>
                <div class="h-3 rounded-full bg-slate-200 overflow-hidden"><div id="progressBar" class="h-full bg-emerald-500 transition-all" style="width:0"></div></div>
                <div class="flex justify-between text-sm mt-2"><span id="progressText">۰ از ۰</span><strong id="progressPercent">۰٪</strong></div>
                <div id="progressResults" class="text-xs text-slate-600 mt-3"></div>
            </div>
        </aside>
    </div>

    <section class="mt-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700"><h2 class="font-bold">تاریخچه عملیات سراسری</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900"><tr>
                    <th class="p-3 text-right">شناسه</th><th class="p-3 text-right">مدیر</th><th class="p-3 text-right">فیلتر</th>
                    <th class="p-3 text-right">تغییر نقش</th><th class="p-3 text-right">پیشرفت</th><th class="p-3 text-right">تاریخ</th><th class="p-3 text-right">عملیات</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse($operations as $operation)
                    <tr>
                        <td class="p-3">#{{ $operation->id }}</td>
                        <td class="p-3">{{ $operation->creator?->fullName() ?? $operation->creator?->email }}</td>
                        <td class="p-3">{{ data_get($operation->filters, 'group_category', 'all') }} / {{ data_get($operation->filters, 'location_level') ?: 'همه سطوح' }}</td>
                        <td class="p-3">{{ $operation->source_role == 0 ? 'ناظر' : 'فعال' }} ← {{ $operation->target_role == 0 ? 'ناظر' : 'فعال' }}</td>
                        <td class="p-3">{{ $operation->processed_items }} / {{ $operation->total_items }} ({{ $operation->status }})</td>
                        <td class="p-3">{{ $operation->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-3">
                            @if($operation->status !== 'completed')
                                <button type="button" class="resume-operation px-3 py-1.5 rounded-lg bg-amber-500 text-white" data-operation-id="{{ $operation->id }}">ادامه اجرا</button>
                            @else
                                <span class="text-emerald-600">تکمیل‌شده</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-slate-500">هنوز عملیاتی ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('globalRoleForm');
    const previewButton = document.getElementById('previewButton');
    const startButton = document.getElementById('startButton');
    const errorBox = document.getElementById('formError');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let previewSignature = null;

    const values = () => Object.fromEntries(new FormData(form).entries());
    const signature = data => JSON.stringify(data);
    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {})}
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'خطا در انجام درخواست');
        return data;
    };
    const showError = error => {
        errorBox.textContent = error.message || String(error);
        errorBox.classList.remove('hidden');
    };
    const clearError = () => errorBox.classList.add('hidden');

    form.addEventListener('input', () => {
        if (signature(values()) !== previewSignature) startButton.disabled = true;
        const unlimited = form.duration_unit.value === 'unlimited';
        form.duration_value.disabled = unlimited;
        form.duration_value.max = form.duration_unit.value === 'month' ? 12 : 31;
    });

    previewButton.addEventListener('click', async () => {
        clearError(); previewButton.disabled = true;
        try {
            const payload = values();
            const data = await request(@json(route('admin.groups.global-roles.preview')), {method: 'POST', body: JSON.stringify(payload)});
            document.getElementById('previewHint').classList.add('hidden');
            document.getElementById('previewStats').classList.remove('hidden');
            document.getElementById('previewGroups').textContent = data.groups.toLocaleString('fa-IR');
            document.getElementById('previewMembers').textContent = data.memberships.toLocaleString('fa-IR');
            document.getElementById('previewApply').textContent = data.will_apply.toLocaleString('fa-IR');
            document.getElementById('previewCancel').textContent = data.will_cancel.toLocaleString('fa-IR');
            previewSignature = signature(values());
            startButton.disabled = data.memberships === 0;
        } catch (error) { showError(error); }
        finally { previewButton.disabled = false; }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault(); clearError();
        if (signature(values()) !== previewSignature) return showError(new Error('فیلترها تغییر کرده‌اند؛ ابتدا پیش‌نمایش را دوباره اجرا کنید.'));
        if (!confirm('این عملیات روی تمام اعضای مطابق فیلتر اعمال می‌شود. ادامه می‌دهید؟')) return;
        startButton.disabled = true;
        try {
            const operation = await request(@json(route('admin.groups.global-roles.store')), {method: 'POST', body: JSON.stringify(values())});
            await runOperation(operation);
            setTimeout(() => window.location.reload(), 1200);
        } catch (error) { showError(error); startButton.disabled = false; }
    });

    document.querySelectorAll('.resume-operation').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true; clearError();
        try {
            await runOperation({id: Number(button.dataset.operationId), status: 'processing', total: 0, processed: 0, percent: 0});
            setTimeout(() => window.location.reload(), 1200);
        } catch (error) { showError(error); button.disabled = false; }
    }));

    async function runOperation(operation) {
        document.getElementById('progressCard').classList.remove('hidden');
        const processPattern = @json(route('admin.groups.global-roles.process', ['operation' => '__operation__']));
        while (operation.status !== 'completed') {
            operation = await request(processPattern.replace('__operation__', operation.id), {method: 'POST', body: '{}'});
            renderProgress(operation);
            await new Promise(resolve => setTimeout(resolve, 150));
        }
        renderProgress(operation);
    }

    function renderProgress(operation) {
        document.getElementById('progressBar').style.width = `${operation.percent}%`;
        document.getElementById('progressText').textContent = `${operation.processed.toLocaleString('fa-IR')} از ${operation.total.toLocaleString('fa-IR')}`;
        document.getElementById('progressPercent').textContent = `${operation.percent.toLocaleString('fa-IR')}٪`;
        document.getElementById('progressResults').textContent = `اعمال‌شده: ${operation.applied}، لغوشده: ${operation.cancelled}، بدون تغییر: ${operation.skipped}، خطا: ${operation.failed}`;
    }
});
</script>
@endsection
