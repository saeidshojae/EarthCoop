import test from 'node:test';
import assert from 'node:assert/strict';

import {
    adminDateFilterNamesForPath,
    baseLocale,
    calendarForLocale,
    gregorianBirthYears,
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

test('admin temporal runtime knows each legacy date-filter contract', () => {
    assert.deepEqual(adminDateFilterNamesForPath('/admin/najm-bahar/analytics'), ['date_from', 'date_to']);
    assert.deepEqual(adminDateFilterNamesForPath('/admin/reports/'), ['date_from', 'date_to']);
    assert.deepEqual(adminDateFilterNamesForPath('/admin/users'), ['created_from', 'created_to']);
    assert.deepEqual(adminDateFilterNamesForPath('/admin/unknown'), []);
});
