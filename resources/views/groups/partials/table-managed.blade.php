@php
    $groups = $groups ?? collect();
@endphp

<div class="data-table-container">
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
            @forelse($groups as $group)
                <tr>
                    <td><i class="fas fa-user-shield table-icon"></i></td>
                    <td>
                        <a href="{{ route('groups.show', $group) }}"
                           class="text-earth-green hover:text-dark-green transition">
                            {{ $group->name }}
                        </a>
                    </td>
                    <td>
                        @php
                            $pivot = $group->pivot ?? \App\Models\GroupUser::where('group_id', $group->id)
                                ->where('user_id', auth()->id())
                                ->first();
                            $roleLabels = [
                                0 => 'ناظر',
                                1 => 'فعال',
                                2 => 'بازرس',
                                3 => 'مدیر',
                                4 => 'مهمان',
                            ];
                        @endphp
                        {{ $roleLabels[$pivot->role ?? 3] ?? 'مدیر' }}
                    </td>
                    <td>
                        <span class="status-badge active-status">
                            فعال
                        </span>
                    </td>
                    <td>{{ $group->users()->count() }} عضو</td>
                    <td>
                        <a href="{{ route('groups.chat', $group) }}"
                           class="text-ocean-blue hover:text-dark-blue transition">
                            <i class="fas fa-comments"></i> ورود به گروه
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-8 text-gray-500">
                        هیچ گروه مدیریتی یافت نشد
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

