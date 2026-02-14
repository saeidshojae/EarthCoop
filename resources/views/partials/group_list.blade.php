<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if($groups->isEmpty())
    <div class="text-center py-8 text-gray-500">
        <i class="fas fa-info-circle text-4xl mb-4 text-gray-400"></i>
        <p class="text-lg">گروهی یافت نشد</p>
    </div>
@else
    <ul class="tab-content-list">
        @foreach($groups as $group)
            @php
                $isFrozen = ($user->address->status ?? null) != 1 || ($user->experience_status ?? null) != 1 || ($user->occupational_status ?? null) != 1;
                $pivot = \App\Models\GroupUser::where('group_id', $group->id)
                    ->where('user_id', auth()->user()->id)
                    ->first();
                $memberRole = $pivot ? match($pivot->role) {
                    0 => 'ناظر',
                    1 => 'فعال',
                    2 => 'بازرس',
                    3 => 'مدیر',
                    4 => 'مهمان',
                    5 => 'فعال ۲',
                    'observer' => 'ناظر',
                    'active' => 'فعال',
                    'inspector' => 'بازرس',
                    'manager' => 'مدیر',
                    'guest' => 'مهمان',
                    default => 'نامشخص',
                } : null;
                $fColorAvatar = rand(1,255);
                $sColorAvatar = rand(1,255);
                $tColorAvatar = rand(1,255);
            @endphp
            <li class="tab-content-item {{ $isFrozen ? 'opacity-50' : '' }}">
                <div class="group-avatar">
                    @if($group->avatar)
                        <img src="{{ asset('images/groups/' . $group->avatar) }}" alt="{{ $group->name }}">
                    @else
                        <div class="default-avatar" style="background: linear-gradient(90deg, rgba({{ $fColorAvatar }},{{ $sColorAvatar }},{{ $tColorAvatar }},.8) 0%, rgba({{ $fColorAvatar }},{{ $sColorAvatar }},{{ $tColorAvatar }},.5) 100%); width: 100%; height: 100%; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center;">
                            {{ mb_substr($group->name, 0, 2) }}
                        </div>
                    @endif
                </div>
                <div class="flex-grow">
                    <a href="{{ !$isFrozen ? route('groups.chat', $group->id) : '#' }}"
                       class="block {{ !$isFrozen ? 'hover:text-earth-green' : '' }} transition text-gentle-black no-underline">
                        <div class="font-semibold text-base">{{ $group->name }}</div>
                        @if($memberRole)
                            <div class="text-right text-sm text-earth-green mt-1">{{ $memberRole }}</div>
                        @endif
                    </a>
                </div>
                <i class="fas fa-chevron-left text-earth-green"></i>
            </li>
        @endforeach
    </ul>
@endif

