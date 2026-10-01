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

export const enhanceLegacyBirthDate = (root = document, locale = document.documentElement.lang || 'fa') => {
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

export const enhanceTemporalInputs = (root = document) => {
    const locale = document.documentElement.lang || 'fa';
    enhanceLegacyBirthDate(root, locale);
};

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => enhanceTemporalInputs(document), { once: true });
    } else {
        enhanceTemporalInputs(document);
    }
}
