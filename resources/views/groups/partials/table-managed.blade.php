@php
    $groups = $groups ?? collect();
    $roleLabels = [
        0 => 'ناظر',
        1 => 'فعال',
        2 => 'بازرس',
        3 => 'مدیر',
        4 => 'مهمان',
    ];
    $currentUserId = auth()->id();

    $groupRows = $groups->map(function ($group) use ($currentUserId, $roleLabels) {
        $pivot = $group->pivot ?? \App\Models\GroupUser::where('group_id', $group->id)
            ->where('user_id', $currentUserId)
            ->first();

        return [
            'group' => $group,
            'roleText' => $roleLabels[(int) ($pivot->role ?? 3)] ?? 'مدیر',
            'memberCount' => isset($group->users_count) ? (int) $group->users_count : $group->users()->count(),
        ];
    })->values();
@endphp

<div class="ec-entity-list lg:hidden" data-mobile-group-list>
    @forelse($groupRows as $row)
        @php
            $group = $row['group'];
            $roleText = $row['roleText'];
            $memberCount = $row['memberCount'];
        @endphp
        <a href="{{ route('groups.chat', $group) }}"
           class="ec-entity-card ec-entity-card--interactive"
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
                    <span class="ec-entity-card__meta-item"><i class="fas fa-user-shield" aria-hidden="true"></i>{{ $roleText }}</span>
                    <span class="ec-entity-card__status ec-entity-card__status--active">فعال</span>
                    <span class="ec-entity-card__meta-item"><i class="fas fa-users" aria-hidden="true"></i>{{ $memberCount }} عضو</span>
                </span>
            </span>
            <span class="ec-entity-card__affordance" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
        </a>
    @empty
        <div class="ec-empty-state">هیچ گروه مدیریتی یافت نشد</div>
    @endforelse
</div>

<div class="data-table-container hidden lg:block" data-desktop-group-table>
    <table class="data-table">
        <thead>
            <tr>
                <th></th>
                <th>نام مجمع</th>
                <th>سمت</th>
                <th>وضعیت</th>
                <th>تعداد اعضاء</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupRows as $row)
                @php
                    $group = $row['group'];
                    $roleText = $row['roleText'];
                    $memberCount = $row['memberCount'];
                @endphp
                <tr>
                    <td><i class="fas fa-user-shield table-icon"></i></td>
                    <td>
                        <a href="{{ route('groups.chat', $group) }}" class="text-earth-green hover:text-dark-green transition">
                            {{ $group->name }}
                        </a>
                    </td>
                    <td>{{ $roleText }}</td>
                    <td><span class="status-badge active-status">فعال</span></td>
                    <td>{{ $memberCount }} عضو</td>
                    <td>
                        <a href="{{ route('groups.chat', $group) }}" class="text-ocean-blue hover:text-dark-blue transition">
                            <i class="fas fa-comments"></i> ورود به گروه
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">هیچ گروه مدیریتی یافت نشد</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
