import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const panel = readFileSync('resources/views/groups/partials/group_info_panel.blade.php', 'utf8');

test('group control center exposes the four canonical tabs', () => {
    for (const tab of ['content', 'members', 'governance', 'tools']) {
        assert.match(panel, new RegExp(`data-control-center-tab="${tab}"`), tab);
    }
});

test('group control center preserves contextual search contracts', () => {
    assert.match(panel, /data-control-center-search="content"/);
    assert.match(panel, /data-control-center-search="members"/);
    assert.match(panel, /data-name=/);
    assert.match(panel, /data-role=/);
    assert.match(panel, /data-email=/);
});

test('group control center keeps canonical tool destinations discoverable', () => {
    assert.match(panel, /route\('groups\.show'/, 'group dashboard');
    assert.match(panel, /route\('secretariat\.group'/, 'group secretariat');
    assert.match(panel, /route\('groups\.najm-hoda\.panel'/, 'Najm Hoda group panel');
    assert.match(panel, /route\('groups\.najm-bahar\.dashboard'/, 'Najm Bahar group dashboard');
});

test('group control center preserves current high-value action handlers', () => {
    for (const action of [
        'open-blog',
        'open-poll',
        'open-election-admin',
        'open-group-edit',
    ]) {
        assert.match(panel, new RegExp(`data-chat-page-action="${action}"`), action);
    }
    assert.match(panel, /data-session-toggle/);
    assert.match(panel, /data-session-admin-open/);
    assert.match(panel, /id="addUserButton"/);
    assert.match(panel, /id="addChatRequestButton"/);
});
