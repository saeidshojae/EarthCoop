// Match the jQuery instance resolved by persian-datepicker@0.5.11.
// Its locked dependency is jquery@2.2.0, separate from the application's jquery@3.7.1.
import $ from 'persian-datepicker/node_modules/jquery/dist/jquery.js';

const GREGORIAN_MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

export const baseLocale = (locale = '') => String(locale || '')
    .toLowerCase()
    .split(/[-_]/, 1)[0];

export const calendarForLocale = (locale = '') => baseLocale(locale) === 'fa'
    ? 'jalali'
    : 'gregorian';

export const localeFromCookie = (cookieString = '') => {
    const pair = String(cookieString || '')
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith('earthcoop_locale='));

    if (!pair) return '';

    try {
        return decodeURIComponent(pair.slice('earthcoop_locale='.length));
    } catch {
        return '';
    }
};

export const resolvedLocale = () => {
    if (typeof document === 'undefined') return 'fa';

    return localeFromCookie(document.cookie)
        || document.documentElement.lang
        || 'fa';
};

export const gregorianBirthYears = (currentYear = new Date().getFullYear(), minimumAge = 15, span = 135) => {
    const newest = currentYear - minimumAge;
    const oldest = newest - span;
    const years = [];

    for (let year = newest; year >= oldest; year -= 1) years.push(year);

    return years;
};

const replaceOptions = (select, options, placeholder, selectedValue = '') => {
    if (!select) return;

    select.replaceChildren();
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = placeholder;
    select.appendChild(empty);

    options.forEach(({ value, label }) => {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = String(label);
        option.selected = String(value) === String(selectedValue);
        select.appendChild(option);
    });
};

export const enhanceLegacyBirthDate = (root = document, locale = resolvedLocale()) => {
    const selects = [...root.querySelectorAll('select[name="birth_date[]"]')];
    if (selects.length !== 3 || calendarForLocale(locale) !== 'gregorian') return false;

    const [daySelect, monthSelect, yearSelect] = selects;
    const selectedMonth = monthSelect.value;
    const selectedYear = yearSelect.value;

    replaceOptions(
        monthSelect,
        GREGORIAN_MONTHS.map((label, index) => ({ value: index + 1, label })),
        'Month',
        selectedMonth,
    );

    replaceOptions(
        yearSelect,
        gregorianBirthYears().map((year) => ({ value: year, label: year })),
        'Year',
        selectedYear,
    );

    const firstDayOption = daySelect.options[0];
    if (firstDayOption && firstDayOption.value === '') firstDayOption.textContent = 'Day';

    selects.forEach((select) => {
        select.dataset.temporalCalendar = 'gregorian';
        select.dir = 'ltr';
    });

    return true;
};

let persianDatepickerPromise = null;

const temporalJQuery = $;

const existingPersianDatepicker = () => temporalJQuery?.fn?.persianDatepicker;

const loadBundledPersianDatepicker = async () => {
    const existing = existingPersianDatepicker();
    if (typeof existing === 'function') return existing;
    if (persianDatepickerPromise) return persianDatepickerPromise;

    persianDatepickerPromise = (async () => {
        // Persian Datepicker is a jQuery plugin. Bind it to the jQuery instance
        // imported by this Vite chunk instead of whichever legacy global jQuery
        // happened to run first/last on the page.
        window.$ = temporalJQuery;
        window.jQuery = temporalJQuery;

        await import('../css/temporal-picker.css');
        const persianDateModule = await import('persian-date');
        window.persianDate = window.persianDate || persianDateModule.default || persianDateModule;
        await import('persian-datepicker/dist/js/persian-datepicker.min.js');

        return existingPersianDatepicker();
    })();

    return persianDatepickerPromise;
};

export const jalaliPickerOptions = (input) => {
    const isDateTime = input.matches('[data-temporal-datetime-input]');
    const options = {
        format: isDateTime ? 'YYYY/MM/DD HH:mm' : 'YYYY/MM/DD',
        initialValue: Boolean(input.value),
        autoClose: true,
        observer: true,
        viewMode: input.name === 'birth_date' && !input.value ? 'year' : 'day',
        calendar: { persian: { locale: 'fa' } },
    };

    if (isDateTime) {
        options.timePicker = {
            enabled: true,
            meridiem: { enabled: false },
        };
    }

    return options;
};

const bindPickerTrigger = (input, picker) => {
    const field = input.closest('[data-temporal-picker-field]');
    const trigger = field?.querySelector('[data-temporal-picker-trigger]');
    if (!trigger || trigger.dataset.temporalPickerTriggerReady === 'true') return;

    trigger.addEventListener('click', () => {
        if (input.disabled) return;

        if (picker && typeof picker.show === 'function') {
            picker.show();
            return;
        }

        input.focus();
        temporalJQuery(input).trigger('click');
    });

    trigger.dataset.temporalPickerTriggerReady = 'true';
};

export const enhanceJalaliDateInputs = async (root = document) => {
    const inputs = [...root.querySelectorAll(
        '[data-temporal-date-input][data-calendar="jalali"], [data-temporal-datetime-input][data-calendar="jalali"]',
    )];
    if (inputs.length === 0) return false;

    const plugin = await loadBundledPersianDatepicker();
    if (typeof plugin !== 'function' || !window.jQuery) return false;

    inputs.forEach((input) => {
        const $input = temporalJQuery(input);
        if ($input.data('temporalDatepickerReady')) {
            bindPickerTrigger(input, $input.data('temporalDatepickerInstance'));
            return;
        }

        const picker = $input.persianDatepicker(jalaliPickerOptions(input));
        $input.data('temporalDatepickerReady', true);
        $input.data('temporalDatepickerInstance', picker);
        input.dataset.temporalPickerReady = 'true';
        bindPickerTrigger(input, picker);
    });

    return true;
};

export const enhanceTemporalInputs = async (root = document) => {
    const locale = resolvedLocale();
    enhanceLegacyBirthDate(root, locale);
    if (calendarForLocale(locale) === 'jalali') {
        await enhanceJalaliDateInputs(root);
    }
};

if (typeof document !== 'undefined') {
    const run = (root = document) => {
        void enhanceTemporalInputs(root).catch((error) => {
            console.warn('EarthCoop temporal input enhancement failed; manual date entry remains available.', error);
        });
    };

    const observeDynamicTemporalInputs = () => {
        if (typeof MutationObserver === 'undefined' || !document.body) return;

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (!(node instanceof Element)) continue;

                    if (
                        node.matches?.('[data-temporal-date-input], [data-temporal-datetime-input]')
                        || node.querySelector?.('[data-temporal-date-input], [data-temporal-datetime-input]')
                    ) {
                        run(node.matches?.('[data-temporal-date-input], [data-temporal-datetime-input]') ? node.parentElement || node : node);
                    }
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    };

    const boot = () => {
        run(document);
        observeDynamicTemporalInputs();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
}
