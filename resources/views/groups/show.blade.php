@extends('layouts.unified')

@section('title', $group->name . ' - جزئیات گروه')

@php
use App\Models\Region;
use App\Models\Village;
use App\Models\Rural;
use App\Models\Neighborhood;
use App\Models\Street;
use App\Models\Alley;
use App\Modules\NajmBahar\Services\AccountNumberService;

$pivot = $group->users()
->where('users.id', auth()->id())
->first();

$roleLabels = [
0 => 'ناظر',
1 => 'فعال',
2 => 'بازرس',
3 => 'مدیر',
4 => 'مهمان',
5 => 'فعال ۲',
];

// نقش کاربر ابتدا از pivot خوانده می‌شود؛ در نبود pivot، از location_level استفاده می‌کنیم
$pivotRole = isset($pivot?->pivot?->role) ? (int) $pivot->pivot->role : null;
if ($pivotRole !== null) {
$roleValue = $pivotRole;
} else {
$locationLevel = strtolower(trim((string)($group->location_level ?? '')));
if (in_array($locationLevel, ['neighborhood', 'street', 'alley'], true)) {
$roleValue = 1; // عضو فعال
} else {
$roleValue = 0; // ناظر
}
}

$roleLabel = $roleLabels[$roleValue] ?? 'عضو';
$isGroupManager = in_array((int)($pivot?->pivot?->role ?? 0), [2, 3], true);
$groupAccountNumber = AccountNumberService::makeLegalEntityAccountNumberForGroup($group->id);

$locationApproved = true;
if ($group->address_id !== null) {
$level = $group->location_level;
if (!in_array($level, ['continent', 'country', 'province', 'county', 'section', 'city'], true)) {
$modelMap = [
'region' => Region::class,
'village' => Village::class,
'rural' => Rural::class,
'neighborhood' => Neighborhood::class,
'street' => Street::class,
'alley' => Alley::class,
];
$model = $modelMap[$level] ?? null;
if ($model) {
$instance = $model::find($group->address_id);
if ($instance && (int)($instance->status ?? 1) === 0) {
$locationApproved = false;
}
}
}
}

$specialtyApproved = true;
if (($group->specialty && (int)($group->specialty->status ?? 1) === 0) ||
($group->experience && (int)($group->experience->status ?? 1) === 0)) {
$specialtyApproved = false;
}

$membershipStatus = (int)($pivot?->pivot?->status ?? 1) === 1;
$pendingApproval = !$locationApproved || !$specialtyApproved;

if (!$membershipStatus) {
$statusLabel = 'غیرفعال';
$statusClass = 'badge-danger';
} elseif ($pendingApproval) {
$statusLabel = 'در انتظار تأیید';
$statusClass = 'badge-warning';
} else {
$statusLabel = 'فعال';
$statusClass = 'badge-success';
}

$typeLabel = 'عمومی';
if (!empty($group->gender) || !empty($group->age_group_id) || in_array($group->group_type, [3, 4], true)) {
    $typeLabel = 'اختصاصی';
} elseif (!empty($group->specialty_id) || !empty($group->experience_id) || in_array($group->group_type, [1, 'speciality', 'specialized'], true)) {
    $typeLabel = 'تخصصی';
}

$membersCount = $group->users()->wherePivot('status', 1)->count();

// ترکیب همه فعالیت‌ها و سورت بر اساس تاریخ
$allActivities = collect();

foreach($recentMessages ?? collect() as $msg) {
$allActivities->push([
'type' => 'messages',
'item' => $msg,
'created_at' => $msg->created_at,
]);
}

foreach($recentPosts ?? collect() as $post) {
$allActivities->push([
'type' => 'posts',
'item' => $post,
'created_at' => $post->created_at,
]);
}

foreach($recentPolls ?? collect() as $poll) {
$allActivities->push([
'type' => 'polls',
'item' => $poll,
'created_at' => $poll->created_at,
]);
}

foreach($recentElections ?? collect() as $election) {
$allActivities->push([
'type' => 'elections',
'item' => $election,
'created_at' => $election->created_at ?? now(),
]);
}

// سورت بر اساس تاریخ (جدیدترین اول)
$allActivities = $allActivities->sortByDesc('created_at')->take(10)->values();
@endphp
<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-8" style="direction: rtl;">
    <div class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden">
        <div class="bg-gradient-to-l from-emerald-500 to-emerald-700 text-white px-6 py-10">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-black mb-2">{{ $group->name }}</h1>
                    <p class="text-sm text-emerald-100 flex items-center gap-2">
                        <i class="fas fa-layer-group"></i>
                        نوع گروه: <span class="font-semibold">{{ $typeLabel }}</span>
                    </p>
                    @if($membershipStatus)
                        <p class="text-sm text-emerald-100 flex items-center gap-2 mt-2">
                            <i class="fas fa-id-card"></i>
                            شماره حساب مالی: <span class="font-mono font-semibold">{{ $groupAccountNumber }}</span>
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    <span
                        class="inline-flex items-center gap-2 bg-emerald-600/40 backdrop-blur px-4 py-2 rounded-full text-sm">
                        <i class="fas fa-user-shield text-lg"></i>
                        نقش شما: <strong>{{ $roleLabel }}</strong>
                    </span>
                    <span
                        class="inline-flex items-center gap-2 bg-emerald-600/40 backdrop-blur px-4 py-2 rounded-full text-sm">
                        <i class="fas fa-signal text-lg"></i>
                        وضعیت عضویت: <strong>{{ $statusLabel }}</strong>
                    </span>
                    <a href="{{ route('groups.chat', $group) }}"
                        class="inline-flex items-center gap-2 bg-white/90 text-emerald-700 px-4 py-2 rounded-full text-sm font-semibold hover:bg-white transition">
                        <i class="fas fa-comments"></i>
                        ورود به گفت‌وگو
                    </a>
                </div>
            </div>
        </div>

        @if($membershipStatus)
            <div class="px-6 py-4 bg-emerald-50 border-t border-emerald-100"></div>
        @endif

        <div class="px-6 py-8 grid gap-6 md:grid-cols-3">
            <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm text-emerald-600">اعضای فعال</span>
                    <i class="fas fa-users text-emerald-500"></i>
                </div>
                <p class="text-3xl font-extrabold text-emerald-700">{{ number_format($membersCount) }}</p>
            </div>

            <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm text-emerald-600">مدیران و بازرسان</span>
                    <i class="fas fa-user-shield text-emerald-500"></i>
                </div>
                @if($admins && $admins->count() > 0)
                <div class="space-y-2">
                    @foreach($admins as $admin)
                    @php
                    $roleLabel = match((int)($admin->pivot->role ?? 0)) {
                    2 => 'بازرس',
                    3 => 'مدیر',
                    default => 'عضو'
                    };
                    $roleColor = (int)($admin->pivot->role ?? 0) === 3 ? 'text-emerald-700' : 'text-emerald-600';
                    @endphp
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-emerald-800 font-medium">
                            {{ $admin->first_name ?? '' }} {{ $admin->last_name ?? '' }}
                        </span>
                        <span class="{{ $roleColor }} font-semibold text-xs">
                            {{ $roleLabel }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-base text-emerald-600 italic">این گروه هنوز مدیری ندارد</p>
                @endif
            </div>

            <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm text-emerald-600">اقدامات سریع</span>
                    <i class="fas fa-bolt text-emerald-500"></i>
                </div>
                <div class="flex flex-col gap-2 text-sm">
                    @if($isGroupManager)
                        <a href="{{ route('groups.najm-bahar.dashboard', $group) }}"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition">
                            <i class="fas fa-wallet"></i>
                            حساب نجم بهار گروه
                        </a>
                    @endif
                    @if($membershipStatus)
                        <a href="{{ route('groups.najm-bahar.reports', $group) }}"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white text-emerald-700 border border-emerald-200 rounded-xl hover:bg-emerald-50 transition">
                            <i class="fas fa-chart-bar"></i>
                            گزارش مالی گروه
                        </a>
                    @endif
                    <a href="{{ route('groups.logout', $group) }}"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-emerald-200 text-emerald-600 rounded-xl hover:bg-emerald-100 transition">
                        <i class="fas fa-sign-out-alt"></i>
                        خروج از گروه
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-800">آخرین فعالیت‌های گروه</h2>
            <div class="flex gap-2">
                <select id="activityFilter"
                    class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="all">همه</option>
                    <option value="messages">پیام‌ها</option>
                    <option value="posts">پست‌ها</option>
                    <option value="polls">نظرسنجی‌ها</option>
                    <option value="elections">انتخابات</option>
                </select>
                <a href="{{ route('groups.chat', $group) }}" class="text-sm text-emerald-600 hover:text-emerald-700">
                    مشاهده گفت‌وگو
                </a>
            </div>
        </div>
        <div class="divide-y divide-gray-100" id="activitiesList">
            @forelse($allActivities as $activity)
            @if($activity['type'] === 'messages')
            @php $message = $activity['item']; @endphp
            <div class="activity-item px-6 py-4 flex gap-4" data-type="messages">
                <div
                    class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">
                    @if($message->user)
                    {{ mb_substr($message->user->fullName() ?? $message->user->name, 0, 1) }}
                    @else
                    ؟
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-900">
                                @if($message->user)
                                {{ $message->user->fullName() ?? $message->user->name }}
                                @else
                                کاربر حذف شده
                                @endif
                            </span>
                            <span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full">💬 پیام</span>
                        </div>
                        <span class="text-xs text-gray-500 whitespace-nowrap">
                            {{ verta($message->created_at)->formatDifference() }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">
                        {{ \Illuminate\Support\Str::limit(strip_tags($message->message), 160) }}
                    </p>
                </div>
            </div>
            @elseif($activity['type'] === 'posts')
            @php $post = $activity['item']; @endphp
            <div class="activity-item px-6 py-4 flex gap-4" data-type="posts">
                <div
                    class="w-10 h-10 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-sm">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full">📄 پست</span>
                            @if($post->user)
                            <span class="text-xs text-gray-600">
                                توسط <strong>{{ $post->user->fullName() ?? $post->user->name }}</strong>
                            </span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500 whitespace-nowrap">
                            {{ verta($post->created_at)->formatDifference() }}
                        </span>
                    </div>
                    <div class="mb-1">
                        <p class="font-semibold text-gray-900 text-sm">
                            {{ $post->title ?? 'بدون عنوان' }}
                        </p>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">
                        {{ \Illuminate\Support\Str::limit(strip_tags($post->content ?? ''), 160) }}
                    </p>
                    <a href="{{ route('groups.comment', $post) }}"
                        class="text-xs text-purple-600 hover:text-purple-700 mt-2 inline-flex items-center gap-1">
                        <i class="fas fa-eye"></i>
                        مشاهده پست
                    </a>
                </div>
            </div>
            @elseif($activity['type'] === 'polls')
            @php $poll = $activity['item']; @endphp
            <div class="activity-item px-6 py-4 flex gap-4" data-type="polls">
                <div
                    class="w-10 h-10 rounded-full bg-yellow-100 text-yellow-700 flex items-center justify-center font-bold text-sm">
                    <i class="fas fa-poll"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full">📊
                                نظرسنجی</span>
                            @if($poll->user)
                            <span class="text-xs text-gray-600">
                                توسط <strong>{{ $poll->user->fullName() ?? $poll->user->name }}</strong>
                            </span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500 whitespace-nowrap">
                            {{ verta($poll->created_at)->formatDifference() }}
                        </span>
                    </div>
                    <div class="mb-1">
                        <p class="font-semibold text-gray-900 text-sm">
                            {{ $poll->question }}
                        </p>
                    </div>
                    <p class="text-sm text-gray-700">
                        <span>تعداد گزینه‌ها: <strong>{{ $poll->options->count() ?? 0 }}</strong></span>
                        @if($poll->expires_at)
                        <span class="mx-2">·</span>
                        <span>مهلت: <strong>{{ verta($poll->expires_at)->format('Y/m/d') }}</strong></span>
                        @endif
                    </p>
                </div>
            </div>
            @elseif($activity['type'] === 'elections')
            @php $election = $activity['item']; @endphp
            <div class="activity-item px-6 py-4 flex gap-4" data-type="elections">
                <div
                    class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-bold text-sm">
                    <i class="fas fa-vote-yea"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">🗳️
                                انتخابات</span>
                            @if($election->user)
                            <span class="text-xs text-gray-600">
                                توسط <strong>{{ $election->user->fullName() ?? $election->user->name }}</strong>
                            </span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500 whitespace-nowrap">
                            {{ verta($election->created_at ?? now())->formatDifference() }}
                        </span>
                    </div>
                    <p class="font-semibold text-gray-900 text-sm mb-1">
                        انتخابات گروه
                    </p>
                    <p class="text-sm text-gray-700">
                        @if($election->starts_at && $election->ends_at)
                        از <strong>{{ verta($election->starts_at)->format('Y/m/d') }}</strong>
                        تا <strong>{{ verta($election->ends_at)->format('Y/m/d') }}</strong>
                        @endif
                        @if($election->is_closed)
                        <span class="mx-2">·</span>
                        <span class="text-red-600">بسته شده</span>
                        @else
                        <span class="mx-2">·</span>
                        <span class="text-emerald-600">فعال</span>
                        @endif
                    </p>
                </div>
            </div>
            @endif
            @empty
            <div class="px-6 py-8 text-center text-gray-500">
                <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i>
                <p>هنوز فعالیتی ثبت نشده است</p>
            </div>
            @endforelse
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">سایر گروه‌های مجمع عمومی من</h3>
                <a href="{{ route('groups.index') }}" class="text-xs text-emerald-600 hover:text-emerald-700">
                    مشاهده همه
                </a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse($generalGroups as $item)
                <li>
                    <a href="{{ route('groups.show', $item) }}"
                        class="flex items-center justify-between px-6 py-4 hover:bg-emerald-50 transition">
                        <span class="text-sm font-medium text-gray-700">{{ $item->name }}</span>
                        <i class="fas fa-chevron-left text-xs text-gray-400"></i>
                    </a>
                </li>
                @empty
                <li class="px-6 py-4 text-sm text-gray-400 text-center">
                    <i class="fas fa-info-circle text-gray-300 mb-2 block text-2xl"></i>
                    شما عضو هیچ گروه عمومی دیگری نیستید
                </li>
                @endforelse
            </ul>
        </div>

        <div class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">گروه‌های تخصصی و اختصاصی من</h3>
                <a href="{{ route('groups.index') }}" class="text-xs text-emerald-600 hover:text-emerald-700">
                    مشاهده همه
                </a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse($specializedGroups as $item)
                <li>
                    <a href="{{ route('groups.show', $item) }}"
                        class="flex items-center justify-between px-6 py-4 hover:bg-emerald-50 transition">
                        <span class="text-sm font-medium text-gray-700">{{ $item->name }}</span>
                        <i class="fas fa-chevron-left text-xs text-gray-400"></i>
                    </a>
                </li>
                @empty
                <li class="px-6 py-4 text-sm text-gray-400 text-center">
                    <i class="fas fa-info-circle text-gray-300 mb-2 block text-2xl"></i>
                    شما عضو هیچ گروه تخصصی یا اختصاصی دیگری نیستید
                </li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.getElementById('activityFilter');
    const activityItems = document.querySelectorAll('.activity-item');

    if (filterSelect && activityItems.length > 0) {
        filterSelect.addEventListener('change', function() {
            const filterValue = this.value;

            activityItems.forEach(item => {
                if (filterValue === 'all' || item.dataset.type === filterValue) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });

            // اگر هیچ آیتمی نمایش داده نمی‌شود، پیام مناسب نشان بده
            const visibleItems = Array.from(activityItems).filter(item => item.style.display !==
                'none');
            let emptyMessage = document.querySelector('.activity-empty-message');

            if (visibleItems.length === 0 && filterValue !== 'all') {
                if (!emptyMessage) {
                    emptyMessage = document.createElement('div');
                    emptyMessage.className =
                        'activity-empty-message px-6 py-8 text-center text-gray-500';
                    emptyMessage.innerHTML =
                        '<i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i><p>فعالیتی در این دسته یافت نشد</p>';
                    document.getElementById('activitiesList').appendChild(emptyMessage);
                }
                emptyMessage.style.display = '';
            } else if (emptyMessage) {
                emptyMessage.style.display = 'none';
            }
        });
    }
});
</script>
@endpush

@endsection