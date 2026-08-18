import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
const arbiter = readFileSync('resources/js/group-chat/network-arbiter.js', 'utf8');
const app = readFileSync('resources/js/app.js', 'utf8');

test('canonical sync exclusively owns HTTP polling when syncUrl exists', () => {
    assert.match(realtime, /const hasCanonicalSync = Boolean\(window\.GroupChatConfig\?\.syncUrl\)/);
    assert.match(realtime, /if \(hasCanonicalSync\) \{[\s\S]*?lifecycle\.interval\(pollSync, syncInterval\)/);
    assert.match(realtime, /else \{[\s\S]*?lifecycle\.interval\(pollMessages, 3000\)/);
    assert.doesNotMatch(realtime, /lifecycle\.interval\(pollMessages, 1000\)/);
});

test('group chat api does not amplify polling through per-request retries', () => {
    assert.match(index, /retries:\s*0/);
});

test('background reads are single-flight and mutation-prioritized', () => {
    assert.match(arbiter, /backgroundFlights:\s*new Map\(\)/);
    assert.match(arbiter, /const existing = state\.backgroundFlights\.get\(flightKey\)/);
    assert.match(arbiter, /backgroundControllers\.forEach\(controller => controller\.abort\('mutation-priority'\)\)/);
    assert.match(arbiter, /unread-count\|session-participation/);
});

test('localhost unregisters service workers instead of registering one', () => {
    assert.match(app, /localDevelopmentHost/);
    assert.match(app, /navigator\.serviceWorker\.getRegistrations\(\)/);
    assert.match(app, /registration => registration\.unregister\(\)/);
    assert.match(app, /else if \('serviceWorker' in navigator && window\.isSecureContext\)/);
});
