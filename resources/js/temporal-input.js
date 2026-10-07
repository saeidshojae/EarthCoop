import $ from 'jquery';

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

const LEGACY_ADMIN_DATE_FILTERS = new Map([
    ['/admin/najm-bahar/analytics', ['date_from', 'date_to']],
    ['/admin/reports', ['date_from', 'date_to']],
    ['/admin/users', ['created_from', 'created_to']],
]);

export const adminDateFilterNamesForPath = (path = '') => LEGACY_ADMIN_DATE_FILTERS.get(
    String(path || '').replace(/\/+$/, '') || '/',
) || [];

export const markLegacyAdminDateInputs = (root = document, locale = resolvedLocale()) => {
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const names = adminDateFilterNamesForPath(path);
    if (names.length === 0) return false;

    const selector = names.map((name) => `input[name="${name}"]`).join(', ');
    const inputs = [...root.querySelectorAll(selector)];
    if (inputs.length === 0) return false;

    const calendar = calendarForLocale(locale);
    inputs.forEach((input) => {
        const rawValue = input.getAttribute('value') || input.value || '';
        input.classList.remove('jalali-date');
        input.dataset.temporalDateInput = '';
        input.dataset.calendar = calendar;
        input.autocomplete = 'off';

        if (calendar === 'jalali') {
            input.type = 'text';
            input.inputMode = 'numeric';
            input.placeholder = '۱۴۰۵/۰۷/۰۹';
        } else {
            input.type = 'date';
            input.removeAttribute('inputmode');
            input.removeAttribute('placeholder');
            input.dir = 'ltr';
        }

        input.value = rawValue;
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

        const persianDateModule = await import('persian-date');
        window.persianDate = window.persianDate || persianDateModule.default || persianDateModule;
        await import('persian-datepicker/dist/js/persian-datepicker.min.js');

        return existingPersianDatepicker();
    })();

    return persianDatepickerPromise;
};

export const enhanceJalaliDateInputs = async (root = document) => {
    const inputs = [...root.querySelectorAll(
        '[data-temporal-date-input][data-calendar="jalali"], [data-temporal-date][data-calendar="jalali"]',
    )];
    if (inputs.length === 0) return false;

    const plugin = await loadBundledPersianDatepicker();
    if (typeof plugin !== 'function' || !window.jQuery) return false;

    inputs.forEach((input) => {
        const $input = temporalJQuery(input);
        if ($input.data('temporalDatepickerReady')) return;

        $input.persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: Boolean(input.value),
            autoClose: true,
            calendar: { persian: { locale: 'fa' } },
        });
        $input.data('temporalDatepickerReady', true);
    });

    return true;
};

export const enhanceTemporalInputs = async (root = document) => {
    const locale = resolvedLocale();
    enhanceLegacyBirthDate(root, locale);
    markLegacyAdminDateInputs(root, locale);

    if (calendarForLocale(locale) === 'jalali') {
        await enhanceJalaliDateInputs(root);
    }
};

if (typeof document !== 'undefined') {
    const run = () => {
        void enhanceTemporalInputs(document).catch((error) => {
            console.warn('EarthCoop temporal input enhancement failed; manual date entry remains available.', error);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
}
