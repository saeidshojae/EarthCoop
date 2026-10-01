import test from 'node:test';
import assert from 'node:assert/strict';

import {
    baseLocale,
    calendarForLocale,
    gregorianBirthYears,
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

test('Gregorian birth year range begins at exact minimum-age year', () => {
    const years = gregorianBirthYears(2026, 15, 135);

    assert.equal(years[0], 2011);
    assert.equal(years.at(-1), 1876);
    assert.equal(years.length, 136);
});
