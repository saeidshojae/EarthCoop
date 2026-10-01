<input
    type="{{ $inputType() }}"
    id="{{ $resolvedId() }}"
    name="{{ $name }}"
    value="{{ $inputValue() }}"
    data-temporal-date-input
    data-calendar="{{ $calendar() }}"
    @if($calendar() === 'jalali')
        inputmode="numeric"
        autocomplete="off"
        placeholder="۱۴۰۵/۰۷/۰۹"
        pattern="[۰-۹0-9]{4}/[۰-۹0-9]{2}/[۰-۹0-9]{2}"
        aria-describedby="{{ $resolvedId() }}-format-hint"
    @endif
    @if($required) required @endif
    {{ $attributes }}
/>
@if($calendar() === 'jalali')
    <small id="{{ $resolvedId() }}-format-hint" class="text-muted" data-temporal-date-hint>
        تاریخ را به صورت سال/ماه/روز وارد کنید؛ انتخاب‌گر تاریخ در صورت فعال بودن جاوااسکریپت در دسترس است.
    </small>
@endif
