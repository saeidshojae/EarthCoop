@php
    $groups = $groups ?? collect();
    $icon = $icon ?? 'fas fa-users';
    $emptyMessage = $emptyMessage ?? 'هیچ گروهی یافت نشد';
    $filters = $filters ?? [];
    $filterTarget = $tableId ?? ('table_' . \Illuminate\Support\Str::uuid()->toString());
    $levelKey = $levelKey ?? null;
    $type = $type ?? null;
    $roleLabels = [
        0 => 'ناظر',
        1 => 'فعال',
        2 => 'بازرس',
        3 => 'مدیر',
        4 => 'مهمان',
        5 => 'فعال ۲',
    ];
    $currentUserId = auth()->id();
@endphp

@if(!empty($filters))
    <div class="filter-buttons" data-target="{{ $filterTarget }}">
        @foreach($filters as $value => $label)
            <button class="filter-button {{ $loop->first ? 'active' : '' }}" data-filter="{{ $value }}">
                {{ $label }}
            </button>
        @endforeach
    </div>
@endif

<div class="data-table-container">
    <table class="data-table" id="{{ $filterTarget }}">
        <thead>
            <tr>
                <th></th>
                <th>نام مجمع</th>
                <th>سمت</th>
                <th>وضعیت</th>
                <th>تعداد اعضاء</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                @php
                    $pivot = $group->pivot ?? \App\Models\GroupUser::where('group_id', $group->id)
                        ->where('user_id', $currentUserId)
                        ->first();
                    if (!$pivot) {
                        continue;
                    }

                    // نقش کاربر ابتدا از pivot خوانده می‌شود؛ در نبود pivot، از location_level استفاده می‌کنیم
                    $pivotRole = isset($pivot->role) ? (int) $pivot->role : null;
                    if ($pivotRole === null) {
                        $locationLevel = strtolower(trim((string)($group->location_level ?? '')));
                        if (in_array($locationLevel, ['neighborhood', 'street', 'alley'], true)) {
                            $pivotRole = 1; // عضو فعال
                        } else {
                            $pivotRole = 0; // ناظر
                        }
                    }

                    $roleText = $roleLabels[$pivotRole] ?? 'عضو';

                    $locationApproved = true;
                    if ($group->address_id !== null) {
                        $level = $group->location_level;
                        if (!in_array($level, ['continent', 'country', 'province', 'county', 'section', 'city'], true)) {
                            $modelMap = [
                                'region' => \App\Models\Region::class,
                                'village' => \App\Models\Village::class,
                                'rural' => \App\Models\Rural::class,
                                'neighborhood' => \App\Models\Neighborhood::class,
                                'street' => \App\Models\Street::class,
                                'alley' => \App\Models\Alley::class,
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

                    $isActiveMembership = (int)($pivot->status ?? 1) === 1;

                    /**
                     * منطق "در انتظار تأیید" باید بسته به نوع جدول تغییر کند:
                     * - گروه‌های عمومی: تأیید لوکیشن (محله/منطقه/...) مهم است
                     * - گروه‌های تخصصی/تجربی: تأیید تخصص/تجربه مهم است (نه لوکیشن)
                     * - سایر موارد: حالت پیش‌فرض (برای سازگاری)
                     */
                    $pendingApproval = match ($type) {
                        // گروه‌های عمومی: فقط تأیید لوکیشن (در سطوح ریز) مهم است
                        'general' => !$locationApproved,

                        // گروه‌های تخصصی: تأیید صنف/تخصص مهم است + اگر لوکیشنِ همان گروه هنوز تایید نشده باشد، pending است
                        // (سطوح قابل ایجاد در استپ۳ مثل region/village/neighborhood/street/alley و... با locationApproved پوشش داده می‌شوند)
                        'specialty' => (!$specialtyApproved) || !$locationApproved,

                        // سایر موارد: حالت پیش‌فرض (برای سازگاری)
                        default => (!$locationApproved || !$specialtyApproved),
                    };

                    if (!$isActiveMembership) {
                        $statusClass = 'status-badge inactive';
                        $statusLabel = 'غیرفعال';
                    } elseif ($pendingApproval) {
                        $statusClass = 'status-badge pending';
                        $statusLabel = 'در انتظار تأیید';
                    } else {
                        $statusClass = 'status-badge active-status';
                        $statusLabel = 'فعال';
                    }

                    $canAccess = $isActiveMembership && !$pendingApproval;

                    $filterValue = 'all';
                    if ($levelKey) {
                        $rawValue = data_get($group, $levelKey);
                        if ($rawValue) {
                            $filterValue = \Illuminate\Support\Str::lower(trim((string)$rawValue));
                            // Handle special cases: if location_level is 'rural' or 'village', map to 'region' for filter
                            if ($filterValue === 'rural' || $filterValue === 'village') {
                                $filterValue = 'region';
                            }
                            // If filter value doesn't exist in filters array, default to 'all'
                            if (!empty($filters) && !isset($filters[$filterValue])) {
                                $filterValue = 'all';
                            }
                        }
                    }
                @endphp

                <tr data-filter-value="{{ $filterValue }}">
                    <td><i class="{{ $icon }} table-icon"></i></td>
                    <td>
                        @if($canAccess)
                            <a href="{{ route('groups.chat', $group) }}"
                               class="text-earth-green hover:text-dark-green transition font-medium">
                                {{ $group->name }}
                            </a>
                        @else
                            <span class="text-gray-500">
                                {{ $group->name }}
                                @if($statusClass === 'status-badge pending' && !\Illuminate\Support\Str::contains($group->name, 'در انتظار'))
                                    (در انتظار تأیید)
                                @endif
                            </span>
                        @endif
                    </td>
                    <td>
                        @if(!$isActiveMembership)
                            <span class="text-gray-500">خارج شده</span>
                        @else
                            {{ $roleText }}
                        @endif
                    </td>
                    <td>
                        <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                        @if(!$isActiveMembership)
                            <br><a href="{{ route('groups.relogout', $group) }}" class="text-sm text-emerald-600 hover:text-emerald-700 mt-1 inline-block">بازگردانی</a>
                        @endif
                    </td>
                    <td>{{ $group->users()->count() }} عضو</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
