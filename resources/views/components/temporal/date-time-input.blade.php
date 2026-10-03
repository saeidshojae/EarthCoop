<input
    type="{{ $inputType() }}"
    id="{{ $resolvedId() }}"
    name="{{ $name }}"
    value="{{ $inputValue() }}"
    data-temporal-datetime-input
    data-calendar="{{ $calendar() }}"
    @if($calendar() === 'jalali')
        inputmode="numeric"
        autocomplete="off"
        placeholder="۱۴۰۵/۰۷/۰۹ ۱۰:۳۰"
        pattern="[۰-۹٠-٩0-9]{4}/[۰-۹٠-٩0-9]{2}/[۰-۹٠-٩0-9]{2} [۰-۹٠-٩0-9]{2}:[۰-۹٠-٩0-9]{2}"
        aria-describedby="{{ $resolvedId() }}-format-hint"
    @else
        dir="ltr"
    @endif
    @if($required) required @endif
    {{ $attributes }}
/>
@if($calendar() === 'jalali')
    <small id="{{ $resolvedId() }}-format-hint" class="text-muted" data-temporal-datetime-hint>
        تاریخ و ساعت را به صورت سال/ماه/روز ساعت:دقیقه وارد کنید.
    </small>
@endif
