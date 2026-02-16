@extends('layouts.admin')

@section('title', 'بررسی پروژه - نجم بهار')

@section('content')
<div class="container mx-auto px-4 py-8" dir="rtl">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">بررسی پروژه</h1>
            <p class="text-gray-600 mt-2">{{ $project->title }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($project->status === 'pending')
                <form method="POST" action="{{ route('admin.najm-bahar.projects.start-review', $project) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">شروع بررسی</button>
                </form>
            @endif

            @if(in_array($project->status, ['pending', 'under_review']))
                <button type="button" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg" onclick="document.getElementById('approveForm').classList.remove('hidden')">تایید</button>
                <button type="button" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg" onclick="document.getElementById('rejectForm').classList.remove('hidden')">رد</button>
                <button type="button" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg" onclick="document.getElementById('revisionForm').classList.remove('hidden')">درخواست اصلاح</button>
                <button type="button" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg" onclick="document.getElementById('assignForm').classList.remove('hidden')">ارجاع برای بررسی</button>
            @endif

            <button type="button" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg" onclick="document.getElementById('archiveForm').classList.remove('hidden')">بایگانی</button>
            <a href="{{ route('admin.najm-bahar.projects.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">بازگشت</a>
        </div>
    </div>

    <!-- فرم‌ها -->
    <div id="approveForm" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
        <h2 class="text-lg font-bold text-gray-900 mb-3">تایید پروژه</h2>
        <form method="POST" action="{{ route('admin.najm-bahar.projects.approve', $project) }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-2">یادداشت (اختیاری)</label>
            <textarea name="comment" rows="3" class="w-full rounded-lg border-gray-300"></textarea>
            <button type="submit" class="mt-4 px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">تایید نهایی</button>
        </form>
    </div>

    <div id="rejectForm" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
        <h2 class="text-lg font-bold text-gray-900 mb-3">رد پروژه</h2>
        <form method="POST" action="{{ route('admin.najm-bahar.projects.reject', $project) }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-2">دلیل رد</label>
            <textarea name="reason" rows="3" class="w-full rounded-lg border-gray-300" required></textarea>
            <label class="block text-sm font-medium text-gray-700 mb-2 mt-4">یادداشت (اختیاری)</label>
            <textarea name="comment" rows="2" class="w-full rounded-lg border-gray-300"></textarea>
            <button type="submit" class="mt-4 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">ثبت رد</button>
        </form>
    </div>

    <div id="revisionForm" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
        <h2 class="text-lg font-bold text-gray-900 mb-3">درخواست اصلاح</h2>
        <form method="POST" action="{{ route('admin.najm-bahar.projects.request-revision', $project) }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-2">توضیحات اصلاح</label>
            <textarea name="revision_notes" rows="3" class="w-full rounded-lg border-gray-300" required></textarea>
            <button type="submit" class="mt-4 px-6 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg">ارسال درخواست</button>
        </form>
    </div>

    <div id="archiveForm" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
        <h2 class="text-lg font-bold text-gray-900 mb-3">بایگانی پروژه</h2>
        <form method="POST" action="{{ route('admin.najm-bahar.projects.archive', $project) }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-2">دلیل بایگانی (اختیاری)</label>
            <textarea name="reason" rows="3" class="w-full rounded-lg border-gray-300"></textarea>
            <button type="submit" class="mt-4 px-6 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg">بایگانی</button>
        </form>
    </div>

    <div id="assignForm" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
        <h2 class="text-lg font-bold text-gray-900 mb-3">ارجاع پروژه برای بررسی</h2>
        <form method="POST" action="{{ route('admin.najm-bahar.projects.assign', $project) }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع مقصد</label>
                    <select name="assigned_to_type" id="assignedToType" class="w-full rounded-lg border-gray-300" required onchange="updateAssigneeList()">
                        <option value="">انتخاب کنید...</option>
                        <option value="User">کاربر</option>
                        <option value="Group">گروه</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">انتخاب مقصد</label>
                    <select name="assigned_to_id" id="assignedToId" class="w-full rounded-lg border-gray-300" required disabled>
                        <option value="">ابتدا نوع مقصد را انتخاب کنید</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">توضیحات ارجاع (اختیاری)</label>
                    <textarea name="assignment_note" rows="3" class="w-full rounded-lg border-gray-300" placeholder="مثلاً: این پروژه نیاز به تخصص در زمینه فناوری دارد"></textarea>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg">ارجاع</button>
                    <button type="button" onclick="document.getElementById('assignForm').classList.add('hidden')" class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded-lg">لغو</button>
                </div>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">خلاصه طرح</h2>
                <p class="text-gray-700 leading-relaxed">{{ $project->summary }}</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">توضیحات کامل</h2>
                <p class="text-gray-700 leading-relaxed whitespace-pre-line">{{ $project->description ?? '—' }}</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">پیوست‌ها</h2>
                @if(!empty($project->attachments))
                    <ul class="list-disc pr-6 text-sm text-gray-700">
                        @foreach($project->attachments as $file)
                            @php $path = $file['path'] ?? null; @endphp
                            <li>
                                @if($path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($path) }}" target="_blank" class="text-blue-600 hover:underline">
                                        {{ $file['original_name'] ?? $path }}
                                    </a>
                                @else
                                    {{ $file['original_name'] ?? 'فایل پیوست' }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-gray-500">فایلی ثبت نشده است.</p>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3">تاریخچه بررسی</h2>
                @if($project->reviews->isEmpty())
                    <p class="text-gray-500 text-sm">هنوز بررسی انجام نشده است.</p>
                @else
                    <ul class="space-y-3 text-sm">
                        @foreach($project->reviews as $review)
                            <li class="border-r-2 border-gray-200 pr-3">
                                <div class="text-gray-700 font-semibold">{{ $review->action_label ?? $review->action }}</div>
                                <div class="text-gray-500">{{ $review->comment ?? '—' }}</div>
                                <div class="text-xs text-gray-400">
                                    {{ optional($review->reviewer)->fullName() ?? 'سیستم' }} - {{ $review->created_at->diffForHumans() }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">اطلاعات کلیدی</h2>
                <div class="space-y-3 text-sm">
                    @php
                        $typeLabels = [
                            'production' => 'تولیدی',
                            'service' => 'خدماتی',
                            'infrastructure' => 'زیرساخت',
                            'research' => 'تحقیقی',
                            'social' => 'اجتماعی',
                        ];
                        $visibilityLabels = [
                            'public' => 'عمومی',
                            'private' => 'خصوصی',
                        ];
                        $stageLabels = [
                            'idea' => 'ایده',
                            'documented' => 'مستند شده',
                            'prototype' => 'نمونه سازی',
                            'active' => 'فعال',
                        ];
                        $riskLabels = [
                            'low' => 'کم',
                            'medium' => 'متوسط',
                            'high' => 'زیاد',
                        ];
                        $typeLabel = $typeLabels[$project->project_type] ?? $project->project_type;
                        $visibilityLabel = $visibilityLabels[$project->project_visibility] ?? $project->project_visibility;
                        $stageLabel = $stageLabels[$project->project_stage] ?? $project->project_stage;
                        $riskLabel = $riskLabels[$project->risk_level] ?? $project->risk_level;
                    @endphp
                    <div class="flex justify-between">
                        <span class="text-gray-600">نوع پروژه:</span>
                        <span class="font-semibold">{{ $typeLabel }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">میزان دید:</span>
                        <span class="font-semibold">{{ $visibilityLabel }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">مرحله:</span>
                        <span class="font-semibold">{{ $stageLabel ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">سرمایه مورد نیاز:</span>
                        <span class="font-semibold">{{ number_format($project->required_capital) }} گل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">درصد سود:</span>
                        <span class="font-semibold text-green-600">{{ $project->profit_percentage }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">ارزش پایه:</span>
                        <span class="font-semibold">{{ number_format($project->base_value_min ?? 0) }} - {{ number_format($project->base_value_max ?? 0) }} گل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">مدت:</span>
                        <span class="font-semibold">{{ $project->investment_duration_months ?? '—' }} ماه</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">سطح ریسک:</span>
                        <span class="font-semibold">{{ $riskLabel ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">وضعیت:</span>
                        <span class="font-semibold">{{ $project->status }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">تاریخ ارسال:</span>
                        <span class="font-semibold">{{ $project->submitted_at ? $project->submitted_at->diffForHumans() : '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">ارزش گذاری و سهام</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">کل سهام:</span>
                        <span class="font-semibold">{{ $project->total_shares ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">درصد عرضه اولیه:</span>
                        <span class="font-semibold">{{ $project->initial_auction_percent ?? '—' }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">حداکثر مالکیت هر کاربر:</span>
                        <span class="font-semibold">{{ $project->max_user_ownership_percent ?? '—' }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">دوره مزایده:</span>
                        <span class="font-semibold">{{ $project->auction_period ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">ریسک و نظارت</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">نوع نظارت:</span>
                        <span class="font-semibold">{{ $project->oversight_type ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 block mb-2">ریسک های اصلی:</span>
                        @if(!empty($project->main_risks))
                            <ul class="list-disc pr-5 text-gray-700">
                                @foreach($project->main_risks as $risk)
                                    <li>{{ $risk }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-gray-500">—</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">تعهدات و سیاست ها</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">دوره گزارش دهی:</span>
                        <span class="font-semibold">{{ $project->reporting_interval ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">دامنه استفاده سرمایه:</span>
                        <span class="font-semibold">{{ $project->fund_usage_scope ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">سیاست شکست:</span>
                        <span class="font-semibold">{{ $project->failure_policy ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">راهبر تحدیث ارزش:</span>
                        <span class="font-semibold">{{ $project->value_update_trigger ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">تعهد شفاف سازی:</span>
                        <span class="font-semibold">{{ $project->accept_transparency ? 'دارد' : 'ندارد' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">پذیرش مقررات:</span>
                        <span class="font-semibold">{{ $project->accept_rules ? 'دارد' : 'ندارد' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">صاحب پروژه</h2>
                @if($project->owner_type === 'App\\Models\\User')
                    <p class="text-sm text-gray-700">{{ $project->owner->fullName() }}</p>
                    <p class="text-xs text-gray-500">کاربر</p>
                @else
                    <p class="text-sm text-gray-700">{{ $project->owner->name }}</p>
                    <p class="text-xs text-gray-500">گروه</p>
                @endif
            </div>

            @if($project->assigned_to_id && $project->assigned_to_type)
                <div class="bg-blue-50 rounded-lg shadow p-6 border-r-4 border-blue-600">
                    <h2 class="text-lg font-bold text-blue-900 mb-4">وضعیت ارجاع</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">ارجاع شده به:</span>
                            <span class="font-semibold">
                                @if($project->assigned_to_type === 'App\\Models\\User')
                                    {{ $project->assignedTo->name }}
                                    <span class="text-xs text-gray-500">(کاربر)</span>
                                @else
                                    {{ $project->assignedTo->name }}
                                    <span class="text-xs text-gray-500">(گروه)</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">تاریخ ارجاع:</span>
                            <span class="font-semibold">{{ optional($project->assigned_at)->diffForHumans() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">وضعیت:</span>
                            <span class="px-2 py-1 rounded text-xs font-semibold
                                @if($project->assignment_status === 'pending')
                                    bg-yellow-200 text-yellow-800
                                @elseif($project->assignment_status === 'under_review')
                                    bg-blue-200 text-blue-800
                                @elseif($project->assignment_status === 'completed')
                                    bg-green-200 text-green-800
                                @else
                                    bg-red-200 text-red-800
                                @endif
                            ">
                                {{ $project->assignment_status === 'pending' ? 'در انتظار' : ($project->assignment_status === 'under_review' ? 'تحت بررسی' : ($project->assignment_status === 'completed' ? 'تکمیل شده' : 'رد شده')) }}
                            </span>
                        </div>
                        @if($project->assignment_note)
                            <div>
                                <span class="text-gray-600">توضیحات ارجاع:</span>
                                <p class="text-gray-700 mt-1">{{ $project->assignment_note }}</p>
                            </div>
                        @endif
                        @if($project->assignment_review_note && in_array($project->assignment_status, ['completed', 'rejected']))
                            <div>
                                <span class="text-gray-600">نظر بررسی کننده:</span>
                                <p class="text-gray-700 mt-1">{{ $project->assignment_review_note }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">آمار سرمایه‌گذاری</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">جمع‌آوری شده:</span>
                        <span class="font-semibold">{{ number_format($project->total_invested ?? 0) }} گل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">تعداد سرمایه‌گذار:</span>
                        <span class="font-semibold">{{ $project->investors_count ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">سرمایه‌گذاری‌ها</h2>
                @if($project->investments->isEmpty())
                    <p class="text-gray-500 text-sm">سرمایه‌گذاری‌ای ثبت نشده است.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach($project->investments as $investment)
                            <li class="flex justify-between">
                                <span class="text-gray-600">{{ number_format($investment->amount) }} گل</span>
                                <span class="text-gray-500">{{ $investment->status_label ?? $investment->status }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
async function updateAssigneeList() {
    const typeSelect = document.getElementById('assignedToType');
    const idSelect = document.getElementById('assignedToId');
    const type = typeSelect.value;

    if (!type) {
        idSelect.disabled = true;
        idSelect.innerHTML = '<option value="">ابتدا نوع مقصد را انتخاب کنید</option>';
        return;
    }

    try {
        const endpoint = type === 'User' 
            ? '{{ route("admin.najm-bahar.get-users") }}'
            : '{{ route("admin.najm-bahar.get-groups") }}';

        const response = await fetch(endpoint);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'خطا در دریافت لیست');
        }

        let options = '<option value="">انتخاب کنید...</option>';
        data.items.forEach(item => {
            options += `<option value="${item.id}">${item.name}</option>`;
        });

        idSelect.innerHTML = options;
        idSelect.disabled = false;
    } catch (error) {
        alert('خطا: ' + error.message);
        idSelect.disabled = true;
    }
}
</script>
@endsection
