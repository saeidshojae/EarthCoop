<div class="temporal-picker-control" data-temporal-picker-field>
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
            aria-haspopup="dialog"
        @else
            dir="ltr"
        @endif
        @if($required) required @endif
        {{ $attributes }}
    />
    @if($calendar() === 'jalali' && ! $attributes->has('disabled'))
        <button
            type="button"
            class="temporal-picker-trigger"
            data-temporal-picker-trigger
            data-target="{{ $resolvedId() }}"
            aria-label="باز کردن تقویم و انتخاب ساعت"
            title="انتخاب تاریخ و ساعت"
        >
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
        </button>
    @endif
</div>
@if($calendar() === 'jalali')
    <small id="{{ $resolvedId() }}-format-hint" class="text-muted" data-temporal-datetime-hint>
        تاریخ و ساعت را از تقویم انتخاب کنید؛ ورود دستی نیز با قالب سال/ماه/روز ساعت:دقیقه ممکن است.
    </small>
@endif
