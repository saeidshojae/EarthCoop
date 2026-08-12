<article class="group-session-notice group-session-notice--{{ $item->event_status }}" data-session-id="{{ $item->id }}" data-session-event="session_{{ $item->event_status === 'active' ? 'started' : 'ended' }}">
    <span class="group-session-notice__icon"><i class="fas {{ $item->event_status === 'active' ? 'fa-lock' : 'fa-check' }}"></i></span>
    <div class="group-session-notice__body">
        <strong>{{ $item->event_status === 'active' ? 'جلسه آغاز شد' : 'جلسه پایان یافت' }} — {{ $item->title }}</strong>
        @if($item->subject)<p><b>موضوع:</b> {{ $item->subject }}</p>@endif
        @if($item->agenda)<p><b>دستور جلسه:</b> {!! nl2br(e($item->agenda)) !!}</p>@endif
        <small>{{ $item->event_at?->format('H:i Y/m/d') }}</small>
    </div>
</article>
