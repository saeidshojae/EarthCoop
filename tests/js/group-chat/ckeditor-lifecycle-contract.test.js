import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('ckeditor runtime waits for group chat lifecycle before registering modal listener', () => {
    const runtime = readFileSync('resources/views/groups/partials/ckeditor_runtime.blade.php', 'utf8');

    assert.match(runtime, /function waitForGroupChatLifecycle\(/);
    assert.match(runtime, /window\.requestAnimationFrame\(waitForGroupChatLifecycle\)/);
    assert.match(runtime, /initializeGroupChatCkeditorRuntime\(lifecycle\)/);
    assert.doesNotMatch(runtime, /const lifecycle = window\.GroupChatLifecycle;\s*if \(!lifecycle \|\| lifecycle\.destroyed\) return;/);
});
