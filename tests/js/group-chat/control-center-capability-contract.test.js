import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

const hero = read('resources/views/groups/partials/group_hero.blade.php');
const panel = read('resources/views/groups/partials/group_info_panel.blade.php');
const dashboard = read('resources/views/groups/show.blade.php');
const webRoutes = read('routes/web.php');
const secretariatRoutes = read('routes/secretariat.php');

test('legacy group controls remain present until control center parity is proven', () => {
    for (const action of [
        'open-group-info',
        'open-blog',
        'open-poll',
        'open-election',
        'open-election-admin',
        'manage-members',
        'manage-reports',
        'group-settings',
    ]) {
        assert.match(hero, new RegExp(`data-chat-page-action=["']${action}["']`), action);
    }

    for (const hook of [
        'addUserButton',
        'addChatRequestButton',
        'data-session-toggle',
        'data-session-admin-open',
    ]) {
        assert.match(panel, new RegExp(hook), hook);
    }
});

test('legacy panel tab inventory remains available during migration', () => {
    for (const tab of ['group', 'members', 'admins', 'post', 'poll', 'election']) {
        assert.match(panel, new RegExp(`data-tab=["']${tab}["']`), tab);
    }
    assert.match(panel, /data-tab=["']stats["']/);
});

test('group panel search affordances remain available during migration', () => {
    for (const id of ['groupSearch', 'searchType', 'membersSearch', 'searchManagers']) {
        assert.match(panel, new RegExp(`id=["']${id}["']`), id);
    }

    assert.match(panel, /\/api\/groups\/search\?q=/);
    assert.match(panel, /type=\$\{type\}/);
    assert.match(panel, /data-name/);
    assert.match(panel, /data-role/);
    assert.match(panel, /data-email/);
    assert.match(panel, /data-manager-search-text/);
});

test('group dashboard retains chat, finance, assistant and activity-filter affordances during migration', () => {
    assert.match(dashboard, /route\('groups\.chat', \$group\)/);
    assert.match(dashboard, /route\('groups\.najm-bahar\.dashboard', \$group\)/);
    assert.match(dashboard, /route\('groups\.najm-bahar\.reports', \$group\)/);
    assert.match(dashboard, /route\('groups\.najm-hoda\.panel', \$group\)/);
    assert.match(dashboard, /id=["']activityFilter["']/);
});

test('canonical group chat and secretariat entries are route-backed', () => {
    assert.match(webRoutes, /name\('chat'\)/);
    assert.match(secretariatRoutes, /\/secretariat\/groups\/\{group\}/);
    assert.match(secretariatRoutes, /name\('secretariat\.group'\)/);
});
