import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const contact = readFileSync('resources/views/pages/templates/contact.blade.php', 'utf8');

test('Contact hero is mobile-first and centers its primary actions', () => {
    assert.match(contact, /\.contact-hero-actions\s*\{[^}]*display:\s*grid[^}]*grid-template-columns:\s*1fr[^}]*margin:\s*1\.1rem auto 0/s);
    assert.match(contact, /@media\s*\(min-width:\s*640px\)[\s\S]*?\.contact-hero-actions\s*\{[^}]*display:\s*flex[^}]*justify-content:\s*center/s);
    assert.match(contact, /\.contact-hero-copy\s*\{[^}]*text-align:\s*center/s);
});

test('Contact layout avoids narrow-screen horizontal clipping', () => {
    assert.match(contact, /\.contact-page-container\s*\{[^}]*width:\s*min\(100% - 1\.25rem,\s*72rem\)/s);
    assert.match(contact, /\.contact-page-shell\s*\{[^}]*overflow-x:\s*clip/s);
    assert.match(contact, /\.contact-info-card__copy\s*\{[^}]*min-width:\s*0/s);
    assert.match(contact, /\.contact-info-card__value\s*\{[^}]*overflow-wrap:\s*anywhere/s);
    assert.doesNotMatch(contact, /overflow-x:\s*auto/);
});

test('Contact form and actions retain mobile touch sizing and reduced-motion support', () => {
    assert.match(contact, /\.contact-hero-action\s*\{[^}]*min-height:\s*48px/s);
    assert.match(contact, /\.contact-form-submit button\s*\{[^}]*width:\s*100%[^}]*min-height:\s*48px/s);
    assert.match(contact, /@media\s*\(max-width:\s*639\.98px\)[\s\S]*?\.contact-form-group input,[\s\S]*?font-size:\s*16px/s);
    assert.match(contact, /@media\s*\(prefers-reduced-motion:\s*reduce\)/);
});
