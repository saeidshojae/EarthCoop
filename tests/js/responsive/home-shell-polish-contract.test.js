import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');
const sidebar = read('resources/views/partials/sidebar-unified.blade.php');
const hoda = read('resources/views/components/najm-hoda-widget.blade.php');
const layout = read('resources/views/layouts/unified.blade.php');
const polish = read('resources/views/components/home-shell-polish.blade.php');
const membershipRuntime = read('resources/js/najm-bahar-membership-source.js');

test('Shared sidebar owns semantic sections on every page instead of Home injecting them with JavaScript', () => {
    for (const section of [
        ['network', 'شبکه و ارتباطات'],
        ['governance', 'حکمرانی و مشارکت'],
        ['economy', 'اقتصاد'],
        ['account-support', 'حساب و راهنما'],
    ]) {
        assert.match(sidebar, new RegExp(`data-sidebar-section=["']${section[0]}["']`));
        assert.match(sidebar, new RegExp(section[1]));
    }

    assert.doesNotMatch(polish, /installHomeSidebarSections/);
    assert.doesNotMatch(polish, /dataset\.sidebarSection/);

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

test('Home membership fee recommendation deep-links to the existing Najm Bahar modal', () => {
    assert.match(polish, /next_action\.fragment/);
    assert.match(polish, /membership-fee/);
    assert.match(membershipRuntime, /window\.location\.hash\s*===\s*["']#membership-fee["']/);
    assert.match(membershipRuntime, /openMembershipModal|NajmBahar\.modal\.open\(['"]membershipFeeModal['"]\)/);
});

test('Najm Hoda mobile launcher respects safe areas and keeps a compact Home footprint', () => {
    assert.match(hoda, /data-route-name=/);
    assert.match(polish, /#najm-hoda-widget\[data-route-name=["']home["']\]/);
    assert.match(polish, /env\(safe-area-inset-bottom\)/);
    assert.match(polish, /#najm-hoda-widget\[data-route-name=["']home["']\][\s\S]*?\.najm-hoda-toggle-btn\s*\{[^}]*width:\s*(?:44|46|48)px[^}]*height:\s*(?:44|46|48)px/s);
    assert.match(hoda, /\.najm-hoda-toggle-btn\s*\{[^}]*width:\s*(?:5[2-9]|6\d)px[^}]*height:\s*(?:5[2-9]|6\d)px/s);
    assert.match(polish, /bottom:\s*calc\([^;]*env\(safe-area-inset-bottom\)[^;]*\)/);
    assert.match(layout, /routeIs\(['"]home['"]\)[\s\S]*components\.home-shell-polish/s);
});
