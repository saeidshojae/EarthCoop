<span class="temporal-picker-control" data-temporal-picker-field>
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
            pattern="[۰-۹٠-٩0-9]{4}/[۰-۹٠-٩0-9]{2}/[۰-۹٠-٩0-9]{2}"
            aria-describedby="{{ $resolvedId() }}-format-hint"
            aria-haspopup="dialog"
        @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes }}
    />
    @if($calendar() === 'jalali' && ! $disabled)
        <button
            type="button"
            class="temporal-picker-trigger"
            data-temporal-picker-trigger
            data-target="{{ $resolvedId() }}"
            aria-label="باز کردن تقویم جلالی"
            title="انتخاب تاریخ از تقویم"
        >
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
        </button>
    @endif
</span>
@if($calendar() === 'jalali')
    <small id="{{ $resolvedId() }}-format-hint" class="text-muted" data-temporal-date-hint>
        تاریخ را از تقویم انتخاب کنید؛ در صورت نیاز می‌توانید آن را به صورت سال/ماه/روز نیز وارد کنید.
    </small>
@endif
