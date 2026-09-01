import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

const index = read('resources/views/groups/index.blade.php');
const basic = read('resources/views/groups/partials/table-basic.blade.php');
const managed = read('resources/views/groups/partials/table-managed.blade.php');
const unifiedLayout = read('resources/views/layouts/unified.blade.php');
const responsive = read('public/Css/responsive-system.css');
const responsiveRuntime = read('public/js/responsive-system.js');

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
    assert.match(unifiedLayout, /js\/responsive-system\.js/);
    assert.doesNotMatch(responsive, /(^|\})\s*\.container\s*\{/m);
    assert.doesNotMatch(responsive, /(^|\})\s*(?:h1|h2|h3)\s*\{/m);
    assert.doesNotMatch(responsive, /(^|\})\s*table\s*\{/m);
});

test('My Groups uses its existing semantic root as an explicit responsive opt-in scope', () => {
    assert.match(index, /class="groups-page-shell[^"]*"/);
    assert.match(index, /class="dashboard-content"/);
    assert.match(index, /<h2>\{\{ __\('navigation\.footer_my_groups'\) \}\}<\/h2>/);
    assert.match(responsive, /\.groups-page-shell \.dashboard-content/);
    assert.match(responsive, /\.groups-page-shell \.groups-section h2/);
    assert.match(responsive, /@media\s*\(max-width:\s*767px\)[\s\S]*?\.groups-page-shell/);
});

test('basic groups keep desktop table and expose a mobile-native entity list', () => {
    assert.match(basic, /data-desktop-group-table/);
    assert.match(basic, /data-mobile-group-list/);
    assert.match(basic, /data-mobile-filter-target/);
    assert.match(basic, /ec-entity-card/);
    assert.match(basic, /\$group->avatar_url/);
    assert.match(basic, /groups\.chat/);
    assert.match(basic, /groups\.relogout/);
    assert.match(basic, /\$roleText/);
    assert.match(basic, /\$statusLabel/);
});

test('managed groups keep desktop table and expose a mobile-native entity list', () => {
    assert.match(managed, /data-desktop-group-table/);
    assert.match(managed, /data-mobile-group-list/);
    assert.match(managed, /ec-entity-card/);
    assert.match(managed, /\$group->avatar_url/);
    assert.match(managed, /groups\.chat/);
});

test('My Groups list presenters bulk-load member counts and specialty relations instead of per-card queries', () => {
    for (const source of [basic, managed]) {
        assert.match(source, /whereIn\('group_id',\s*\$groups->pluck\('id'\)\)/);
        assert.match(source, /COUNT\(\*\) as aggregate/);
        assert.doesNotMatch(source, /users\(\)->count\(\)/);
    }
    assert.match(basic, /\$groups->loadMissing\(\['specialty',\s*'experience'\]\)/);
});

test('mobile and desktop group representations have an explicit cascade-safe breakpoint contract', () => {
    assert.match(responsive, /\.groups-page-shell \[data-mobile-group-list\]\s*\{[^}]*display:\s*none/s);
    assert.match(responsive, /\.groups-page-shell \[data-desktop-group-table\]\s*\{[^}]*display:\s*block/s);
    assert.match(responsive, /@media\s*\(max-width:\s*1023px\)[\s\S]*?\.groups-page-shell \[data-mobile-group-list\]\s*\{[^}]*display:\s*grid/s);
    assert.match(responsive, /@media\s*\(max-width:\s*1023px\)[\s\S]*?\.groups-page-shell \[data-desktop-group-table\]\s*\{[^}]*display:\s*none/s);
});

test('mobile entity-list contract avoids horizontal-scroll dependency and protects Persian titles', () => {
    assert.match(responsive, /\.ec-entity-card__body\s*\{[^}]*min-width:\s*0/s);
    assert.match(responsive, /\.ec-entity-card__title\s*\{[^}]*word-break:\s*normal/s);
    assert.doesNotMatch(responsive, /\.ec-entity-list\s*\{[^}]*overflow-x:\s*auto/s);
    assert.match(responsive, /@media\s*\(max-width:\s*767px\)[\s\S]*?\.ec-page-shell\s*\{[^}]*padding-inline:\s*(?:0?\.75rem|0?\.875rem|1rem)/);
    assert.match(responsive, /@media\s*\(max-width:\s*767px\)[\s\S]*?\.ec-page-title\s*\{[^}]*font-size:/);
});

test('responsive filter bridge updates cards and tables inside the clicked local scope', () => {
    assert.match(responsiveRuntime, /\.filter-buttons\[data-target\]/);
    assert.match(responsiveRuntime, /data-mobile-filter-target/);
    assert.match(responsiveRuntime, /querySelectorAll\('\[data-filter-value\]'\)/);
    assert.match(responsiveRuntime, /closest\('\.filter-buttons\[data-target\]'\)/);
    assert.doesNotMatch(responsiveRuntime, /document\.getElementById\(targetId\)/);
});

test('responsive filter bridge captures specialty filter clicks before legacy local handlers can stop bubbling', () => {
    assert.match(index, /event\.stopPropagation\(\)/);
    assert.match(
        responsiveRuntime,
        /document\.addEventListener\('click',\s*event\s*=>\s*\{[\s\S]*?applyFilter\(filterContainer,\s*button\.dataset\.filter\s*\|\|\s*'all'\);[\s\S]*?\},\s*true\);/
    );
});
