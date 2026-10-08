import test from 'node:test';
import assert from 'node:assert/strict';

import {
    baseLocale,
    calendarForLocale,
    gregorianBirthYears,
    jalaliPickerOptions,
    localeFromCookie,
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

test('Gregorian birth year range begins at exact minimum-age year', () => {
    const years = gregorianBirthYears(2026, 15, 135);

    assert.equal(years[0], 2011);
    assert.equal(years.at(-1), 1876);
    assert.equal(years.length, 136);
});


test('Jalali date picker options are date-only by default', () => {
    const input = {
        value: '۱۴۰۵/۰۷/۰۹',
        matches: (selector) => selector === '[data-temporal-datetime-input]' ? false : false,
    };

    const options = jalaliPickerOptions(input);

    assert.equal(options.format, 'YYYY/MM/DD');
    assert.equal(options.initialValue, true);
    assert.equal(options.autoClose, true);
    assert.equal(options.observer, true);
    assert.equal(options.timePicker, undefined);
});

test('Jalali datetime picker enables 24-hour time selection', () => {
    const input = {
        value: '',
        matches: (selector) => selector === '[data-temporal-datetime-input]',
    };

    const options = jalaliPickerOptions(input);

    assert.equal(options.format, 'YYYY/MM/DD HH:mm');
    assert.equal(options.initialValue, false);
    assert.equal(options.timePicker.enabled, true);
    assert.equal(options.timePicker.meridiem.enabled, false);
});
