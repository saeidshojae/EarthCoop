import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('chat hero uses the header offset once and remains flush with the shared header', () => {
    const layout = readFileSync('resources/views/layouts/chat.blade.php', 'utf8');
    const controller = readFileSync('resources/js/group-chat-header-controller.js', 'utf8');

    assert.match(controller, /gestureSurface\.style\.setProperty\('top', 'var\(--chat-site-header-offset, 0px\)'\)/);
    assert.match(layout, /\.chat-content-wrapper\s*\{[\s\S]*?padding-top:\s*0\s*!important;/);
    assert.doesNotMatch(layout, /\.chat-content-wrapper\s*\{[\s\S]*?padding-top:\s*var\(--chat-site-header-offset/);
});
