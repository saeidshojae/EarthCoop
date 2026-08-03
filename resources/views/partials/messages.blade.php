<!-- Tailwind & Bootstrap CSS via Vite -->
@foreach($combined as $item)
    @include('groups.partials.' . $item->type, ['item' => $item, 'group' => $group, 'userVote' => $userVote])
@endforeach

