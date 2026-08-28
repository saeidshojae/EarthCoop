import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

const index = read('resources/views/groups/index.blade.php');
const basic = read('resources/views/groups/partials/table-basic.blade.php');
const managed = read('resources/views/groups/partials/table-managed.blade.php');
const unifiedLayout = read('resources/views/layouts/unified.blade.php');
const responsive = read('public/Css/responsive-system.css');

test('responsive system exposes scoped opt-in primitives without blanket global rewrites', () => {
    for (const selector of [
        '.ec-page-shell',
        '.ec-surface',
        '.ec-page-title',
        '.ec-section-title',
        '.ec-page-hero',
        '.ec-entity-list',
        '.ec-entity-card',
        '.ec-entity-card__avatar',
        '.ec-entity-card__body',
        '.ec-entity-card__title',
        '.ec-entity-card__meta',
    ]) {
        assert.match(responsive, new RegExp(selector.replace('.', '\\.')));
    }

    assert.match(unifiedLayout, /Css\/responsive-system\.css/);
    assert.doesNotMatch(responsive, /(^|\})\s*\.container\s*\{/m);
    assert.doesNotMatch(responsive, /(^|\})\s*(?:h1|h2|h3)\s*\{/m);
    assert.doesNotMatch(responsive, /(^|\})\s*table\s*\{/m);
});

test('My Groups opts into responsive page shell surface and title primitives', () => {
    assert.match(index, /groups-page-shell[^"']*ec-page-shell|ec-page-shell[^"']*groups-page-shell/);
    assert.match(index, /dashboard-content[^"']*ec-surface|ec-surface[^"']*dashboard-content/);
    assert.match(index, /<h2[^>]*ec-page-title/);
    assert.match(index, /@media\s*\(max-width:\s*767px\)/);
});

test('basic groups keep desktop table and expose a mobile-native entity list', () => {
    assert.match(basic, /data-desktop-group-table/);
    assert.match(basic, /data-mobile-group-list/);
    assert.match(basic, /ec-entity-card/);
    assert.match(basic, /\$group->avatar_url/);
    assert.match(basic, /groups\.chat/);
    assert.match(basic, /groups\.relogout/);
    assert.match(basic, /\$roleText/);
    assert.match(basic, /\$statusLabel/);
    assert.match(basic, /users_count|users\(\)->count\(\)/);
});

test('managed groups keep desktop table and expose a mobile-native entity list', () => {
    assert.match(managed, /data-desktop-group-table/);
    assert.match(managed, /data-mobile-group-list/);
    assert.match(managed, /ec-entity-card/);
    assert.match(managed, /\$group->avatar_url/);
    assert.match(managed, /groups\.chat/);
    assert.match(managed, /users_count|users\(\)->count\(\)/);
});

test('mobile entity-list contract avoids horizontal-scroll dependency and protects Persian titles', () => {
    assert.match(responsive, /\.ec-entity-card__body\s*\{[^}]*min-width:\s*0/s);
    assert.match(responsive, /\.ec-entity-card__title\s*\{[^}]*word-break:\s*normal/s);
    assert.doesNotMatch(responsive, /\.ec-entity-list\s*\{[^}]*overflow-x:\s*auto/s);
    assert.match(responsive, /@media\s*\(max-width:\s*767px\)[\s\S]*?\.ec-page-shell\s*\{[^}]*padding-inline:\s*(?:0?\.75rem|0?\.875rem|1rem)/);
    assert.match(responsive, /@media\s*\(max-width:\s*767px\)[\s\S]*?\.ec-page-title\s*\{[^}]*font-size:/);
});
