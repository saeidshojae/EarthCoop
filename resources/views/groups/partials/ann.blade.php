<article class="post-card announcement-card" id="ann-{{ $item->id }}" data-content-key="announcement:{{ $item->id }}">
    <span id="announcement-{{ $item->id }}" class="d-block" aria-hidden="true"></span>

    <div class="announcement-card__meta text-center mb-3">
        <span class="badge bg-success-subtle text-success-emphasis">اطلاعیه رسمی</span>
        @if((bool) ($item->should_pin ?? false))
            <span class="badge bg-warning-subtle text-warning-emphasis"><i class="fas fa-thumbtack"></i> سنجاق‌شده</span>
        @endif
        <div class="small text-muted mt-2">منتشرشده توسط تیم مدیریت EarthCoop</div>
    </div>

    <h3 class="text-center mb-4">{{ $item->title }}</h3>

    @if(!empty($item->image))
        <div class="announcement-card__media text-center mb-4">
            <img
                src="{{ asset($item->image) }}"
                alt="تصویر اطلاعیه {{ $item->title }}"
                loading="lazy"
                style="max-width: 100%; max-height: 420px; object-fit: contain; border-radius: 1rem;"
            >
        </div>
    @endif

    <div class="announcement-card__content text-center" style="direction: rtl;">
        {!! $item->content !!}
    </div>
</article>
