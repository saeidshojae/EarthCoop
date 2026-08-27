import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const chat = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
const shell = readFileSync('resources/views/groups/partials/group_control_center_shell.blade.php', 'utf8');
const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');

test('chat mounts the adaptive control center shell on the canonical legacy panel', () => {
    assert.match(chat, /@include\('groups\.partials\.group_info_panel'/);
    assert.match(chat, /@include\('groups\.partials\.group_control_center_shell'\)/);
    assert.match(shell, /#groupInfoPanel\.group-info-panel/);
});

test('control center has dialog semantics and adaptive mobile/desktop geometry', () => {
    assert.match(shell, /setAttribute\('role', 'dialog'\)/);
    assert.match(shell, /setAttribute\('aria-modal', 'true'\)/);
    assert.match(shell, /setAttribute\('aria-labelledby', 'groupControlCenterTitle'\)/);
    assert.match(shell, /max-height: min\(90dvh, 760px\)/);
    assert.match(shell, /width: min\(960px, calc\(100vw - 3rem\)\)/);
    assert.match(shell, /border-radius: 28px 28px 0 0/);
});

test('control center opens at every viewport size and restores accessible state on close', () => {
    assert.doesNotMatch(actions, /window\.innerWidth\s*>=\s*1024/);
    assert.match(actions, /panel\.setAttribute\('aria-hidden', 'false'\)/);
    assert.match(actions, /panel\?\.setAttribute\('aria-hidden', 'true'\)/);
    assert.match(actions, /lastGroupInfoTrigger/);
    assert.match(actions, /groupInfoBackdrop.*closeGroupInfo/s);
});

test('expanded mobile group hero stays compact instead of consuming the viewport', () => {
    assert.match(shell, /\[data-group-hero-content\][\s\S]*?max-height: min\(42dvh, 300px\)/);
    assert.match(shell, /\[data-group-hero-content\] \.grid\.grid-cols-1[\s\S]*?grid-template-columns: repeat\(3, minmax\(0, 1fr\)\)/);
    assert.match(shell, /-webkit-line-clamp: 2/);
});
