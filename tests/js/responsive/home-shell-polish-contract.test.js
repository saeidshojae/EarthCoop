import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');
const sidebar = read('resources/views/partials/sidebar-unified.blade.php');
const hoda = read('resources/views/components/najm-hoda-widget.blade.php');
const layout = read('resources/views/layouts/unified.blade.php');
const polish = read('resources/views/components/home-shell-polish.blade.php');

test('Home sidebar exposes lightweight semantic sections without changing established destinations', () => {
    for (const section of ['network', 'governance', 'economy', 'account-support']) {
        assert.match(polish, new RegExp(`key:\\s*["']${section}["']`));
    }
    assert.match(polish, /sidebar-section-label/);
    assert.match(polish, /dataset\.sidebarSection/);
    assert.match(layout, /routeIs\(['"]home['"]\)[\s\S]*components\.home-shell-polish/s);

    for (const route of [
        'notifications.index',
        'chat-requests.index',
        'groups.index',
        'location-governance.me',
        'history.index',
        'history.election',
        'history.election-history',
        'history.poll',
        'najm-bahar.dashboard',
        'my-invation-code',
        'profile.edit',
    ]) {
        assert.match(sidebar, new RegExp(`route\\(['"]${route.replaceAll('.', '\\.')}`));
    }
});

test('Najm Hoda mobile launcher respects safe areas and keeps a compact Home footprint', () => {
    assert.match(hoda, /data-route-name=/);
    assert.match(polish, /#najm-hoda-widget\[data-route-name=["']home["']\]/);
    assert.match(polish, /env\(safe-area-inset-bottom\)/);
    assert.match(polish, /#najm-hoda-widget\[data-route-name=["']home["']\][\s\S]*?\.najm-hoda-toggle-btn\s*\{[^}]*width:\s*(?:44|46|48)px[^}]*height:\s*(?:44|46|48)px/s);
    assert.match(hoda, /\.najm-hoda-toggle-btn\s*\{[^}]*width:\s*(?:5[2-9]|6\d)px[^}]*height:\s*(?:5[2-9]|6\d)px/s);
    assert.match(polish, /bottom:\s*calc\([^;]*env\(safe-area-inset-bottom\)[^;]*\)/);
});
