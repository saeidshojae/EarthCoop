import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

const hero = read('resources/views/groups/partials/group_hero.blade.php');
const panel = read('resources/views/groups/partials/group_info_panel.blade.php');
const shell = read('resources/views/groups/partials/group_control_center_shell.blade.php');
const dashboard = read('resources/views/groups/show.blade.php');
const webRoutes = read('routes/web.php');
const secretariatRoutes = read('routes/secretariat.php');

test('chat hero prioritizes governance while content creation remains discoverable in control center', () => {
    assert.match(hero, /data-chat-page-action=["']open-group-info["']/);
    assert.match(hero, /data-chat-page-action=["']open-election["']/);
    assert.match(hero, /route\('groups\.najm-bahar\.reports', \$group\)/);
    assert.doesNotMatch(hero, /data-chat-page-action=["']open-blog["']/);
    assert.doesNotMatch(hero, /data-chat-page-action=["']open-poll["']/);

    for (const action of ['open-blog', 'open-poll', 'open-election', 'open-election-admin', 'manage-members', 'manage-reports', 'group-settings']) {
        assert.match(panel, new RegExp(`data-chat-page-action=["']${action}["']`), action);
    }

    for (const hook of ['addUserButton', 'addChatRequestButton', 'data-session-toggle', 'data-session-admin-open']) {
        assert.match(panel, new RegExp(hook), hook);
    }
});

test('control center uses the four canonical top-level tabs without restoring legacy panel navigation', () => {
    for (const tab of ['content', 'members', 'governance', 'tools']) {
        assert.match(panel, new RegExp(`data-control-center-tab=["']${tab}["']`), tab);
    }
    for (const legacyTab of ['group', 'admins', 'post', 'poll', 'election', 'stats']) {
        assert.doesNotMatch(panel, new RegExp(`data-tab=["']${legacyTab}["']`), legacyTab);
    }
    assert.match(shell, /secondaryTabDefinitions/);
});

test('group panel search affordances remain available with encoded canonical requests', () => {
    for (const id of ['groupSearch', 'searchType', 'membersSearch', 'searchManagers']) {
        assert.match(panel, new RegExp(`id=["']${id}["']`), id);
    }

    assert.match(panel, /\/api\/groups\/search\?q=/);
    assert.match(panel, /type=\$\{encodeURIComponent\(type\)\}/);
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
