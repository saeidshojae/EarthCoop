<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
@foreach($combined as $item)
    @include('groups.partials.' . $item->type, ['item' => $item, 'group' => $group, 'userVote' => $userVote])
@endforeach
