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

    // This partial renders both desktop and mobile representations. Resolve
    // shared metadata once so responsive rendering never multiplies queries.
    $memberCounts = $groups->isEmpty()
        ? collect()
        : \App\Models\GroupUser::query()
            ->whereIn('group_id', $groups->pluck('id'))
            ->selectRaw('group_id, COUNT(*) as aggregate')
            ->groupBy('group_id')
            ->pluck('aggregate', 'group_id');

    // Only specialty lists need these approval relations. Bulk-load them once
    // instead of lazy-loading one relation per card/table row.
    if ($type === 'specialty' && $groups->isNotEmpty()) {
        $groups->loadMissing(['specialty', 'experience']);
    }

    // Normalize each group once so desktop and mobile representations reuse the
    // exact same membership/status business rules without doubling queries.
    $groupRows = $groups->map(function ($group) use ($currentUserId, $roleLabels, $type, $levelKey, $filters, $memberCounts) {
        $pivot = $group->pivot ?? \App\Models\GroupUser::where('group_id', $group->id)
            ->where('user_id', $currentUserId)
            ->first();

        if (!$pivot) {
            return null;
        }

        $pivotRole = isset($pivot->role) ? (int) $pivot->role : null;
        if ($pivotRole === null) {
            $locationLevel = strtolower(trim((string) ($group->location_level ?? '')));
            $pivotRole = in_array($locationLevel, ['neighborhood', 'street', 'alley'], true) ? 1 : 0;
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
                    if ($instance && (int) ($instance->status ?? 1) === 0) {
                        $locationApproved = false;
                    }
                }
            }
        }

        $specialtyApproved = true;
        if ($type === 'specialty'
            && (($group->specialty && (int) ($group->specialty->status ?? 1) === 0)
                || ($group->experience && (int) ($group->experience->status ?? 1) === 0))) {
            $specialtyApproved = false;
        }

        $isActiveMembership = (int) ($pivot->status ?? 1) === 1;
        $pendingApproval = match ($type) {
            'general' => !$locationApproved,
            'specialty' => !$specialtyApproved || !$locationApproved,
            default => !$locationApproved || !$specialtyApproved,
        };

        if (!$isActiveMembership) {
            $statusClass = 'status-badge inactive';
            $statusLabel = 'غیرفعال';
            $statusKey = 'inactive';
        } elseif ($pendingApproval) {
            $statusClass = 'status-badge pending';
            $statusLabel = 'در انتظار تأیید';
            $statusKey = 'pending';
        } else {
            $statusClass = 'status-badge active-status';
            $statusLabel = 'فعال';
            $statusKey = 'active';
        }

        $canAccess = $isActiveMembership && !$pendingApproval;

        $filterValue = 'all';
        if ($levelKey) {
            $rawValue = data_get($group, $levelKey);
            if ($rawValue) {
                $filterValue = \Illuminate\Support\Str::lower(trim((string) $rawValue));
                if ($filterValue === 'rural' || $filterValue === 'village') {
                    $filterValue = 'region';
                }
                if (!empty($filters) && !isset($filters[$filterValue])) {
                    $filterValue = 'all';
                }
            }
        }

        return [
            'group' => $group,
            'roleText' => $roleText,
            'statusClass' => $statusClass,
            'statusLabel' => $statusLabel,
            'statusKey' => $statusKey,
            'isActiveMembership' => $isActiveMembership,
            'canAccess' => $canAccess,
            'filterValue' => $filterValue,
            'memberCount' => (int) ($memberCounts[$group->id] ?? 0),
        ];
    })->filter()->values();
@endphp

@if(!empty($filters))
    <div class="filter-buttons" data-target="{{ $filterTarget }}">
        @foreach($filters as $value => $label)
            <button type="button" class="filter-button {{ $loop->first ? 'active' : '' }}" data-filter="{{ $value }}">
                {{ $label }}
            </button>
        @endforeach
    </div>
@endif

{{-- Mobile/tablet: semantic entity cards, never a squeezed multi-column table. --}}
<div class="ec-entity-list lg:hidden" data-mobile-group-list data-mobile-filter-target="{{ $filterTarget }}">
    @forelse($groupRows as $row)
        @php
            $group = $row['group'];
            $roleText = $row['roleText'];
            $statusClass = $row['statusClass'];
            $statusLabel = $row['statusLabel'];
            $statusKey = $row['statusKey'];
            $isActiveMembership = $row['isActiveMembership'];
            $canAccess = $row['canAccess'];
            $filterValue = $row['filterValue'];
            $memberCount = $row['memberCount'];
            $cardClass = $canAccess ? 'ec-entity-card ec-entity-card--interactive' : 'ec-entity-card ec-entity-card--muted';
        @endphp

        @if($canAccess)
            <a href="{{ route('groups.chat', $group) }}"
               class="{{ $cardClass }}"
               data-filter-value="{{ $filterValue }}"
               aria-label="ورود به {{ $group->name }}">
                <span class="ec-entity-card__avatar" aria-hidden="true">
                    @if($group->avatar_url)
                        <img src="{{ $group->avatar_url }}" alt="">
                    @else
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($group->name, 0, 2)) }}
                    @endif
                </span>
                <span class="ec-entity-card__body">
                    <span class="ec-entity-card__title">{{ $group->name }}</span>
                    <span class="ec-entity-card__meta">
                        <span class="ec-entity-card__meta-item"><i class="fas fa-user-tag" aria-hidden="true"></i>{{ $roleText }}</span>
                        <span class="ec-entity-card__status ec-entity-card__status--{{ $statusKey }}">{{ $statusLabel }}</span>
                        <span class="ec-entity-card__meta-item"><i class="fas fa-users" aria-hidden="true"></i>{{ $memberCount }} عضو</span>
                    </span>
                </span>
                <span class="ec-entity-card__affordance" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
            </a>
        @else
            <article class="{{ $cardClass }}" data-filter-value="{{ $filterValue }}" aria-disabled="true">
                <span class="ec-entity-card__avatar" aria-hidden="true">
                    @if($group->avatar_url)
                        <img src="{{ $group->avatar_url }}" alt="">
                    @else
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($group->name, 0, 2)) }}
                    @endif
                </span>
                <span class="ec-entity-card__body">
                    <span class="ec-entity-card__title">{{ $group->name }}</span>
                    <span class="ec-entity-card__meta">
                        <span class="ec-entity-card__meta-item"><i class="fas fa-user-tag" aria-hidden="true"></i>{{ $isActiveMembership ? $roleText : 'خارج شده' }}</span>
                        <span class="ec-entity-card__status ec-entity-card__status--{{ $statusKey }}">{{ $statusLabel }}</span>
                        <span class="ec-entity-card__meta-item"><i class="fas fa-users" aria-hidden="true"></i>{{ $memberCount }} عضو</span>
                    </span>
                    @if(!$isActiveMembership)
                        <a href="{{ route('groups.relogout', $group) }}" class="ec-entity-card__secondary-action">
                            <i class="fas fa-rotate-left" aria-hidden="true"></i>بازگردانی عضویت
                        </a>
                    @endif
                </span>
                <span class="ec-entity-card__affordance" aria-hidden="true">
                    <i class="fas {{ $statusKey === 'pending' ? 'fa-clock' : 'fa-lock' }}"></i>
                </span>
            </article>
        @endif
    @empty
        <div class="ec-empty-state" data-mobile-empty-state>{{ $emptyMessage }}</div>
    @endforelse
</div>

{{-- Desktop: preserve the comparative table representation. --}}
<div class="data-table-container hidden lg:block" data-desktop-group-table>
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
            @forelse($groupRows as $row)
                @php
                    $group = $row['group'];
                    $roleText = $row['roleText'];
                    $statusClass = $row['statusClass'];
                    $statusLabel = $row['statusLabel'];
                    $isActiveMembership = $row['isActiveMembership'];
                    $canAccess = $row['canAccess'];
                    $filterValue = $row['filterValue'];
                    $memberCount = $row['memberCount'];
                @endphp
                <tr data-filter-value="{{ $filterValue }}">
                    <td><i class="{{ $icon }} table-icon"></i></td>
                    <td>
                        @if($canAccess)
                            <a href="{{ route('groups.chat', $group) }}" class="text-earth-green hover:text-dark-green transition font-medium">
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
                    <td>{{ $isActiveMembership ? $roleText : 'خارج شده' }}</td>
                    <td>
                        <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                        @if(!$isActiveMembership)
                            <br><a href="{{ route('groups.relogout', $group) }}" class="text-sm text-emerald-600 hover:text-emerald-700 mt-1 inline-block">بازگردانی</a>
                        @endif
                    </td>
                    <td>{{ $memberCount }} عضو</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
