import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const sessionState = readFileSync('resources/js/group-chat/session-state.js', 'utf8');
const participation = readFileSync('resources/js/group-chat/session-participation.js', 'utf8');
const composer = readFileSync('resources/js/group-chat/composer.js', 'utf8');

test('session state owns the only resilience poll for participation state', () => {
    assert.match(sessionState, /api\.json\(config\.participationStateUrl\)/);
    assert.match(sessionState, /lifecycle\.interval\(reconcile, 15000\)/);
    assert.match(sessionState, /group-chat:session-state/);

    assert.doesNotMatch(participation, /api\.json\(config\.participationStateUrl\)/);
    assert.doesNotMatch(participation, /lifecycle\.interval\(/);
    assert.match(participation, /group-chat:session-state/);
});

test('message submit relies on authoritative POST authorization without a state preflight', () => {
    assert.doesNotMatch(composer, /api\.json\(window\.GroupChatConfig\?\.participationStateUrl/);
    assert.match(composer, /api\.json\(form\.action, \{ method: 'POST', body: formData \}\)/);
    assert.match(composer, /error\?\.code === 'group_session_closed'/);
    assert.match(composer, /group-chat:session-closed/);
});
