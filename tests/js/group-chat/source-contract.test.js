import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const files = [
    'public/js/group-chat.js',
    'public/js/chat-features.js',
    'public/js/voice-recorder.js',
    'resources/views/groups/chat.blade.php',
    'resources/views/groups/comment.blade.php',
    'resources/views/groups/partials/message.blade.php',
    'resources/views/groups/partials/post.blade.php',
    'resources/views/groups/partials/poll.blade.php',
    'resources/views/groups/partials/comment.blade.php',
    'resources/views/groups/partials/header.blade.php',
    'resources/views/groups/partials/group_info_panel.blade.php',
    'resources/views/groups/partials/action_menu_dismissal.blade.php',
    'resources/views/groups/partials/chat_search_runtime.blade.php',
    'resources/views/groups/partials/pin_runtime.blade.php',
];

test('group chat templates and runtime do not contain inline event handlers', () => {
    for (const file of files) {
        const source = readFileSync(file, 'utf8');
        assert.doesNotMatch(source, /\son(?:click|mouseover|mouseout)\s*=/i, file);
    }
});

test('group chat sources do not call blocking browser dialogs', () => {
    for (const file of files) {
        const source = readFileSync(file, 'utf8')
            .replace(/GroupChatFeedback\.(?:confirm|prompt)/g, 'FeedbackMethod')
            .replace(/\/\/.*alert.*$/gm, '');
        assert.doesNotMatch(source, /(^|[^.\w])(alert|confirm|prompt)\s*\(/m, file);
    }
});

test('chat page loads runtime through its dedicated partial', () => {
    const source = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    assert.match(source, /@include\('groups\.partials\.chat_runtime'\)/);
    assert.doesNotMatch(source, /asset\('js\/group-chat\.js'\)/);
});

test('sidecar runtimes expose explicit ownership APIs', () => {
    const features = readFileSync('public/js/chat-features.js', 'utf8');
    const voice = readFileSync('public/js/voice-recorder.js', 'utf8');
    assert.match(features, /window\.GroupChatFeatures\s*=\s*Object\.freeze/);
    assert.match(voice, /window\.GroupVoiceRecorder\s*=\s*Object\.freeze/);
    assert.match(voice, /function installOptimisticVoiceBridge/);
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    assert.doesNotMatch(blade, /Voice Optimistic Override/);
    assert.match(blade, /@include\('groups\.partials\.management_modals'\)/);
});

test('message delete and report actions use the delegated action bridge', () => {
    const runtime = readFileSync('public/js/group-chat.js', 'utf8');
    const message = readFileSync('resources/views/groups/partials/message.blade.php', 'utf8');

    for (const action of ['delete-message', 'report-message']) {
        assert.match(runtime, new RegExp(`data-group-chat-action="${action}"`));
        assert.match(message, new RegExp(`data-group-chat-action="${action}"`));
    }
    assert.doesNotMatch(runtime, /querySelector\(["']\.btn-(?:delete|report)["']\)\?\.addEventListener/);
    assert.doesNotMatch(
        readFileSync('resources/views/groups/chat.blade.php', 'utf8'),
        /querySelector\(["']\.btn-(?:delete|report)["']\)\?\.addEventListener/
    );
});

test('unread polling and observers are owned by the page lifecycle', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');

    assert.match(index, /window\.GroupChatLifecycle\s*=\s*pageLifecycle/);
    assert.match(blade, /lifecycle\.interval\(refreshUnreadCount, 15000\)/);
    assert.match(blade, /lifecycle\.add\(\(\) => observer\.disconnect\(\)\)/);
    assert.doesNotMatch(blade, /setInterval\(refreshUnreadCount/);
});

test('realtime retries and fallback pollers are owned by the page lifecycle', () => {
    const runtime = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(runtime, /realtimeLifecycle\.timeout\(syncGroupDelta/);
    assert.match(runtime, /pollingInterval\s*=\s*realtimeLifecycle\.interval/);
    assert.match(runtime, /realtimeState\.postTimer\s*=\s*realtimeLifecycle\.interval/);
    assert.match(runtime, /realtimeState\.reconcileTimer\s*=\s*realtimeLifecycle\.interval/);
    assert.match(runtime, /realtimeLifecycle\.on\(window, 'online'/);
    assert.doesNotMatch(runtime, /pollingInterval\s*=\s*setInterval/);
    assert.doesNotMatch(runtime, /window\.setTimeout\(syncGroupDelta/);
    assert.doesNotMatch(runtime, /window\.addEventListener\('(online|offline)'/);
});

test('poll countdown and voice recorder release their owned resources', () => {
    const runtime = readFileSync('public/js/group-chat.js', 'utf8');
    const voice = readFileSync('public/js/voice-recorder.js', 'utf8');

    assert.match(runtime, /lifecycle\?\.interval\(updateTimer, 1000\)/);
    assert.match(runtime, /lifecycle\?\.clearInterval\(intervalId\)/);
    assert.match(runtime, /timer\.dataset\.timerSet = 'complete'/);
    assert.match(voice, /recordingTimer\s*=\s*createOwnedInterval/);
    assert.match(voice, /voiceLifecycle\?\.add\(destroyVoiceRecorder\)/);
    assert.match(voice, /destroyVoiceRecorder[\s\S]*stopTimer\(\)/);
    assert.match(voice, /audioStream\.getTracks\(\)\.forEach\(track => track\.stop\(\)\)/);
    assert.doesNotMatch(voice, /recordingTimer\s*=\s*setInterval/);
    assert.doesNotMatch(voice, /window\.addEventListener\('beforeunload'/);
});

test('private mention and voice state are not exposed as window globals', () => {
    const features = readFileSync('public/js/chat-features.js', 'utf8');
    const voice = readFileSync('public/js/voice-recorder.js', 'utf8');

    assert.doesNotMatch(features, /window\.mentionSearchTimeout/);
    for (const name of ['stopRecordingButton', 'recordedAudioBlob', '_voiceTempId', '_voiceBlobUrl']) {
        assert.doesNotMatch(voice, new RegExp(`window\\.${name.replace('$', '\\$')}`));
    }
    assert.match(voice, /getBlob:\s*\(\) => recordedAudioBlob \|\| null/);
});

test('action-menu dismissal is extracted and lifecycle-owned', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const partial = readFileSync('resources/views/groups/partials/action_menu_dismissal.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.action_menu_dismissal'\)/);
    assert.match(partial, /lifecycle\.on\(document, 'click'/);
    assert.match(partial, /lifecycle\.on\(document, 'keydown'/);
    assert.doesNotMatch(partial, /document\.addEventListener/);
});

test('chat search runtime is loaded through its dedicated partial', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const search = readFileSync('resources/views/groups/partials/chat_search_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.chat_search_runtime'\)/);
    assert.doesNotMatch(blade, /function fetchPage\(reset = false\)/);
    assert.match(search, /function fetchPage\(reset = false\)/);
    assert.match(search, /gc-search-wrap/);
    assert.match(search, /window\.__groupChatSearchInitialized/);
    assert.match(search, /lifecycle\.on\(input, 'input'/);
    assert.match(search, /lifecycle\.on\(listEl, 'click'/);
    assert.doesNotMatch(search, /window\.__(?:setSearching|ensureSearchOpen)/);
    assert.doesNotMatch(search, /\.addEventListener\(/);
});

test('pin runtime is extracted and targets the requested message id', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const pin = readFileSync('resources/views/groups/partials/pin_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.pin_runtime'\)/);
    assert.doesNotMatch(blade, /function pinMessage\(/);
    assert.match(pin, /function pinMessage\(messageId\)/);
    assert.match(pin, /function unpinMessage\(messageId\)/);
    assert.equal((pin.match(/`msg-\$\{messageId\}`/g) || []).length, 2);
    assert.doesNotMatch(pin, /`msg-\$\{id\}`/);
});
