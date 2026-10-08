import test from 'node:test';
import assert from 'node:assert/strict';

import {
    baseLocale,
    calendarForLocale,
    gregorianBirthYears,
    jalaliPickerOptions,
    localeFromCookie,
    resolvedLocale,
    storedBirthDateParts,
} from '../../../resources/js/temporal-input.js';

test('base locale normalizes regional variants', () => {
    assert.equal(baseLocale('fa-IR'), 'fa');
    assert.equal(baseLocale('en_GB'), 'en');
    assert.equal(baseLocale('AR'), 'ar');
});

test('only Persian defaults to Jalali calendar', () => {
    assert.equal(calendarForLocale('fa'), 'jalali');
    assert.equal(calendarForLocale('fa-IR'), 'jalali');
    assert.equal(calendarForLocale('en'), 'gregorian');
    assert.equal(calendarForLocale('ar'), 'gregorian');
    assert.equal(calendarForLocale('de'), 'gregorian');
});

test('server locale cookie can override stale hard-coded html lang', () => {
    assert.equal(localeFromCookie('foo=bar; earthcoop_locale=en; theme=dark'), 'en');
    assert.equal(localeFromCookie('earthcoop_locale=fa-IR'), 'fa-IR');
    assert.equal(localeFromCookie('foo=bar'), '');
});

test('encrypted Laravel locale cookie falls back to valid HTML language', () => {
    const priorDocument = globalThis.document;
    globalThis.document = {
        cookie: 'earthcoop_locale=eyJpdiI6ImVuY3J5cHRlZCIsInZhbHVlIjoiLi4uIn0%3D',
        documentElement: { lang: 'fa' },
    };
    try {
        assert.equal(resolvedLocale(), 'fa');
        assert.equal(calendarForLocale(resolvedLocale()), 'jalali');
        globalThis.document.cookie = 'earthcoop_locale=en';
        assert.equal(resolvedLocale(), 'en');
    } finally {
        if (priorDocument === undefined) delete globalThis.document;
        else globalThis.document = priorDocument;
    }
});

test('Gregorian birth year range begins at exact minimum-age year', () => {
    const years = gregorianBirthYears(2026, 15, 135);

    assert.equal(years[0], 2011);
    assert.equal(years.at(-1), 1876);
    assert.equal(years.length, 136);
});


test('Jalali date picker options are date-only by default', () => {
    const input = {
        name: 'event_date',
        value: '۱۴۰۵/۰۷/۰۹',
        matches: (selector) => selector === '[data-temporal-datetime-input]' ? false : false,
    };

    const options = jalaliPickerOptions(input);

    assert.equal(options.format, 'YYYY/MM/DD');
    assert.equal(options.initialValue, false);
    assert.equal(options.initialValueType, 'persian');
    assert.equal(options.autoClose, true);
    assert.equal(options.observer, true);
    assert.equal(options.viewMode, 'day');
    assert.equal(options.timePicker, undefined);
});

test('Jalali datetime picker enables 24-hour time selection', () => {
    const input = {
        name: 'start_time',
        value: '',
        matches: (selector) => selector === '[data-temporal-datetime-input]',
    };

    const options = jalaliPickerOptions(input);

    assert.equal(options.format, 'YYYY/MM/DD HH:mm');
    assert.equal(options.initialValue, false);
    assert.equal(options.initialValueType, 'persian');
    assert.equal(options.timePicker.enabled, true);
    assert.equal(options.timePicker.meridiem.enabled, false);
});

test('prefilled birth date does not allow the plugin to reset it on initialization', () => {
    const input = {
        name: 'birth_date',
        value: '۱۳۸۰/۰۱/۱۲',
        matches: () => false,
    };

    const options = jalaliPickerOptions(input);
    assert.equal(options.initialValue, false);
    assert.equal(options.initialValueType, 'persian');
    assert.equal(options.viewMode, 'day');
});

test('stored birth date parses Persian, Arabic and ASCII digits without changing the input', () => {
    for (const date of ['۱۳۸۰/۰۱/۱۲', '١٣٨٠/٠١/١٢', '1380/01/12']) {
        const input = { name: 'birth_date', value: date, matches: () => false };
        assert.deepEqual(storedBirthDateParts(input), [1380, 1, 12]);
        assert.equal(input.value, date);
    }
});

test('birth-date navigation excludes other dates, datetimes and malformed input', () => {
    const input = (name, value, isDateTime = false) => ({
        name, value, matches: () => isDateTime,
    });
    assert.equal(storedBirthDateParts(input('event_date', '۱۳۸۰/۰۱/۱۲')), null);
    assert.equal(storedBirthDateParts(input('birth_date', '۱۳۸۰/۰۱/۱۲ ۱۲:۳۰', true)), null);
    assert.equal(storedBirthDateParts(input('birth_date', '')), null);
    assert.equal(storedBirthDateParts(input('birth_date', '۱۳۸۰/۱۳/۱۲')), null);
    assert.equal(storedBirthDateParts(input('birth_date', '۱۳۸۰/۰۷/۳۱')), null);
});

test('empty birth-date picker opens in year mode for fast navigation', () => {
    const input = {
        name: 'birth_date',
        value: '',
        matches: () => false,
    };

    const options = jalaliPickerOptions(input);

    assert.equal(options.viewMode, 'year');
});
