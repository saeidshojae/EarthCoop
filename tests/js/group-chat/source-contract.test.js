import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const collectBladeFiles = directory => readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const path = join(directory, entry.name);
    return entry.isDirectory() ? collectBladeFiles(path) : (entry.name.endsWith('.blade.php') ? [path] : []);
});

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
    'resources/views/groups/partials/group_hero.blade.php',
    'resources/views/groups/partials/chat_search_runtime.blade.php',
    'resources/views/groups/partials/scroll_unread_runtime.blade.php',
    'resources/views/groups/partials/message_edit_runtime.blade.php',
    'resources/views/groups/partials/ckeditor_runtime.blade.php',
    'resources/views/groups/partials/page_chrome_runtime.blade.php',
    'resources/views/groups/modals/group_edit_form.blade.php',
    'resources/views/groups/partials/styles/base_styles.blade.php',
    'resources/views/groups/partials/styles/message_edit_styles.blade.php',
    'resources/views/groups/partials/styles/auxiliary_styles.blade.php',
    'resources/views/groups/modals/post_form.blade.php',
    'resources/views/groups/modals/poll_form.blade.php',
    'resources/views/groups/modals/election_form.blade.php',
];

test('group chat templates and runtime do not contain inline event handlers', () => {
    for (const file of files) {
        const source = readFileSync(file, 'utf8');
        assert.doesNotMatch(source, /\son(?:click|mouseover|mouseout)\s*=/i, file);
    }
});

test('session lifecycle is synchronized through realtime with polling fallback', () => {
    const runtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
    const session = readFileSync('resources/js/group-chat/session-state.js', 'utf8');
    assert.match(runtime, /session_started/);
    assert.match(runtime, /session_ended/);
    assert.match(runtime, /app\.sessionState\?\.receive/);
    assert.match(session, /group-chat:composer-replaced/);
    assert.match(session, /lifecycle\.interval\(reconcile, 15000\)/);
});

test('polling consumes the same complete group event contract as websocket', () => {
    const config = readFileSync('resources/views/groups/partials/chat_runtime.blade.php', 'utf8');
    const runtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');

    assert.match(config, /syncUrl:/);
    assert.match(config, /syncCursor:/);
    assert.match(config, /pollingIntervalMs:/);
    assert.match(runtime, /const pollSync = async/);
    assert.match(runtime, /after_cursor=\$\{state\.syncCursor\}/);
    for (const action of [
        'message_created', 'message_deleted', 'post_created', 'poll_voted',
        'comment_reaction', 'pin_updated', 'session_started', 'election_started',
    ]) {
        assert.match(runtime, new RegExp(action), action);
    }
    assert.match(runtime, /lifecycle\.on\(window, 'online'/);
    assert.match(runtime, /lifecycle\.on\(document, 'visibilitychange'/);
});

test('all group chat partials and modals use lifecycle-owned listeners and timers', () => {
    const templates = [
        ...collectBladeFiles('resources/views/groups/partials'),
        ...collectBladeFiles('resources/views/groups/modals'),
    ];

    for (const file of templates) {
        const source = readFileSync(file, 'utf8');
        assert.doesNotMatch(source, /\.addEventListener\(/, file);
        assert.doesNotMatch(source, /(^|[^.\w])(?:set|clear)(?:Timeout|Interval)\(/m, file);
        assert.doesNotMatch(source, /window\.__groupChat\w*/, file);
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

test('chat page uses only locally bundled library assets', () => {
    const chat = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const layout = readFileSync('resources/views/layouts/chat.blade.php', 'utf8');
    const app = readFileSync('resources/js/app.js', 'utf8');
    const viteCss = readFileSync('resources/css/vite.css', 'utf8');

    assert.doesNotMatch(chat, /<(?:script|link)\b[^>]+https?:\/\//i);
    assert.match(layout, /vendor\/fontawesome\/css\/all\.min\.css/);
    assert.match(app, /import \$ from "jquery"/);
    assert.match(app, /import installSelect2 from "select2"/);
    assert.match(app, /installSelect2\(window, appJQuery\)/);
    assert.match(viteCss, /select2\/dist\/css\/select2\.min\.css/);
    assert.doesNotMatch(app, /select2\/dist\/css\/select2\.min\.css/);
});

test('delta synchronization follows the server-side feature flag', () => {
    const config = readFileSync('resources/views/groups/partials/chat_runtime.blade.php', 'utf8');
    const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');

    assert.match(config, /deltaSyncEnabled: @json\(\(bool\) config\('group-chat\.features\.delta_sync_v1'/);
    assert.match(realtime, /const deltaSyncEnabled = window\.GroupChatConfig\?\.deltaSyncEnabled === true/);
    assert.match(realtime, /if \(!deltaSyncEnabled \|\| state\.syncingDelta/);
    assert.match(realtime, /if \(deltaSyncEnabled\) void syncDelta\(\)\.finally\(setHealthy\)/);
});

test('composer keeps optimistic messages until a valid stored identity arrives', () => {
    const composer = readFileSync('resources/js/group-chat/composer.js', 'utf8');
    const validation = composer.indexOf("throw new Error('Invalid stored message response')");
    const removal = composer.indexOf('document.getElementById(`msg-${temporaryId}`)?.remove()');

    assert.ok(validation > -1);
    assert.ok(removal > validation);
    assert.match(composer, /!storedMessage\?\.id \|\| !storedMessage\?\.user_id/);
});

test('legacy read tracking rejects temporary and undefined message identities', () => {
    const features = readFileSync('public/js/chat-features.js', 'utf8');
    assert.match(features, /if \(!\/\^\\d\+\$\/\.test\(key\)\) return/);
});

test('message reactions always render inside the canonical timestamp slot', () => {
    const legacy = readFileSync('resources/js/group-chat/legacy-renderers.js', 'utf8');
    const features = readFileSync('public/js/chat-features.js', 'utf8');
    const renderer = readFileSync('resources/js/group-chat/message-renderer.js', 'utf8');
    const blade = readFileSync('resources/views/groups/partials/message.blade.php', 'utf8');

    assert.match(legacy, /\.message-bubble\[data-message-id=/);
    assert.match(legacy, /querySelector\('\.message-reactions-slot'\)/);
    assert.match(features, /\.message-bubble\[data-message-id=/);
    assert.match(features, /querySelector\('\.message-reactions-slot'\)/);
    assert.match(renderer, /class="message-reactions-slot"/);
    assert.match(blade, /class="message-reactions-slot"/);
});

test('message metadata keeps read receipts last and updates edit time in menus', () => {
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const legacy = readFileSync('resources/js/group-chat/legacy-renderers.js', 'utf8');
    const renderer = readFileSync('resources/js/group-chat/message-renderer.js', 'utf8');
    const blade = readFileSync('resources/views/groups/partials/message.blade.php', 'utf8');
    const editRuntime = readFileSync('resources/views/groups/partials/message_edit_runtime.blade.php', 'utf8');

    assert.match(actions, /if \(!event\.target\.closest\?\.\('\[data-action-menu\]\.is-open'\)\) closeAll\(\)/);
    assert.match(legacy, /receipt\.before\(badge\)/);
    assert.match(legacy, /menu-meta-time__item--edited/);
    assert.ok(renderer.indexOf('message-edit-status') < renderer.lastIndexOf('read-receipt'));
    assert.ok(blade.lastIndexOf('message-edit-status') < blade.lastIndexOf('read-receipt'));
    assert.match(editRuntime, /responseData = responseData\?\.data \?\? responseData/);
});

test('text and voice replies share safe previews and cancellable composer state', () => {
    const composer = readFileSync('resources/js/group-chat/composer.js', 'utf8');
    const renderer = readFileSync('resources/js/group-chat/message-renderer.js', 'utf8');
    const message = readFileSync('resources/views/groups/partials/message.blade.php', 'utf8');
    const voice = readFileSync('public/js/voice-recorder.js', 'utf8');

    assert.match(composer, /data-legacy-chat-action="cancel-reply" aria-label="لغو پاسخ"/);
    assert.match(composer, /container\.style\.display = 'flex'/);
    assert.match(renderer, /parentPreview = stripHtml\(String\(message\.parent_content\)\)/);
    assert.match(renderer, /querySelectorAll\('br'\)\.forEach\(node => node\.replaceWith\(' '\)\)/);
    assert.match(message, /html_entity_decode\(\(string\) \$replyText/);
    assert.match(message, /strip_tags\(\$replyText\)/);
    assert.match(voice, /formData\.append\('parent_id', composerReply\.id\)/);
    assert.match(voice, /parent_sender: composerReply\?\.sender/);
    assert.match(voice, /window\.GroupChat\?\.composer\?\.cancelReply\?\.\(\)/);
});

test('all group content uses the same rtl metadata order', () => {
    const message = readFileSync('resources/views/groups/partials/message.blade.php', 'utf8');
    const post = readFileSync('resources/views/groups/partials/post.blade.php', 'utf8');
    const poll = readFileSync('resources/views/groups/partials/poll.blade.php', 'utf8');
    const renderer = readFileSync('resources/js/group-chat/message-renderer.js', 'utf8');
    const styles = readFileSync('resources/views/groups/partials/styles/base_styles.blade.php', 'utf8');

    for (const source of [message, post, poll, renderer]) assert.match(source, /content-meta-line/);
    assert.match(post, /content-edit-status/);
    assert.match(poll, /content-edit-status/);
    assert.match(styles, /direction: rtl !important/);
    assert.match(styles, /grid-template-columns: max-content max-content minmax\(0, 1fr\) max-content/);
    assert.match(styles, /\.message-timestamp\.content-meta-line \{[\s\S]*?width: calc\(100% \+ 20px\);[\s\S]*?padding-inline: 2px !important/);
    assert.match(styles, /content-meta-time \{[\s\S]*?grid-column: 1/);
    assert.match(styles, /content-meta-time \{[\s\S]*?grid-column: 1;[\s\S]*?grid-row: 1/);
    assert.match(styles, /content-edit-status \{[\s\S]*?grid-column: 2/);
    assert.match(styles, /content-edit-status \{[\s\S]*?grid-column: 2;[\s\S]*?grid-row: 1/);
    assert.match(styles, /reaction-buttons \{[\s\S]*?grid-column: 3/);
    assert.match(styles, /reaction-buttons \{[\s\S]*?grid-column: 3;[\s\S]*?grid-row: 1/);
    assert.match(styles, /content-read-receipt \{[\s\S]*?grid-column: 4/);
    assert.match(styles, /content-read-receipt \{[\s\S]*?grid-column: 4;[\s\S]*?grid-row: 1;[\s\S]*?justify-self: start/);
    assert.match(styles, /message-reactions \{[\s\S]*?flex-wrap: nowrap !important/);
    assert.match(styles, /min-width: min\(245px, calc\(100vw - 92px\)\) !important/);
});

test('realtime runtime is pinned and starts without package downloads', () => {
    const manifest = JSON.parse(readFileSync('package.json', 'utf8'));
    const launcher = readFileSync('scripts/start-soketi.ps1', 'utf8');
    const smoke = readFileSync('scripts/realtime-smoke.cjs', 'utf8');

    assert.equal(manifest.devDependencies['@soketi/soketi'], '1.6.1');
    assert.equal(manifest.devDependencies.node, '18.20.8');
    assert.match(manifest.scripts.realtime, /start-soketi\.ps1/);
    assert.match(manifest.scripts['realtime:smoke'], /node_modules\\node\\bin\\node\.exe scripts\/realtime-smoke\.cjs/);
    assert.doesNotMatch(launcher, /(?:^|[;&|]\s*)npx\s|npm\s+(?:exec|ci)\b/im);
    assert.match(launcher, /node_modules\\node\\bin\\node\.exe/);
    assert.match(launcher, /node_modules\\@soketi\\soketi\\bin\\server\.js/);
    assert.match(smoke, /PUSHER_CONNECTED/);
});

test('sidecar runtimes expose explicit ownership APIs', () => {
    const features = readFileSync('public/js/chat-features.js', 'utf8');
    const voice = readFileSync('public/js/voice-recorder.js', 'utf8');
    assert.match(features, /window\.GroupChatFeatures\s*=\s*Object\.freeze/);
    assert.match(voice, /window\.GroupVoiceRecorder\s*=\s*Object\.freeze/);
    assert.match(voice, /function installOptimisticVoiceBridge/);
    assert.match(voice, /window\.GroupChat\?\.feed/);
    assert.match(voice, /canonicalFeed\.apply\(\[\{ \.\.\.message, content_type: 'message' \}\], source\)/);
    assert.match(voice, /clearOptimisticVoice\(result\.message\)/);
    assert.doesNotMatch(voice, /typeof appendMessage === 'function'/);
    assert.match(voice, /start: startRecording/);
    assert.match(voice, /stop: stopRecording/);
    assert.doesNotMatch(readFileSync('public/js/group-chat.js', 'utf8'), /function (?:startRecording|stopRecording)\(/);
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    assert.doesNotMatch(blade, /Voice Optimistic Override/);
    assert.match(blade, /@include\('groups\.partials\.management_modals'\)/);
});

test('message delete and report actions use the delegated action bridge', () => {
    const runtime = readFileSync('public/js/group-chat.js', 'utf8');
    const messageRenderer = readFileSync('resources/js/group-chat/message-renderer.js', 'utf8');
    const message = readFileSync('resources/views/groups/partials/message.blade.php', 'utf8');
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');

    for (const action of ['delete-message', 'report-message']) {
        assert.match(messageRenderer, new RegExp(`data-group-chat-action="${action}"`));
        assert.match(message, new RegExp(`data-group-chat-action="${action}"`));
    }
    assert.match(messageRenderer, /message\?\.id \?\? message\?\.content_id \?\? message\?\.message_id/);
    assert.match(messageRenderer, /userId == null[\s\S]*return false/);
    assert.doesNotMatch(message, /\$message\b/);
    assert.match(message, /data-group-chat-action="delete-message" data-message-id="\{\{ \$item->id \}\}"/);
    assert.match(message, /data-group-chat-action="report-message" data-message-id="\{\{ \$item->id \}\}"/);
    assert.doesNotMatch(runtime, /querySelector\(["']\.btn-(?:delete|report)["']\)\?\.addEventListener/);
    assert.doesNotMatch(
        readFileSync('resources/views/groups/chat.blade.php', 'utf8'),
        /querySelector\(["']\.btn-(?:delete|report)["']\)\?\.addEventListener/
    );
    assert.doesNotMatch(runtime, /initializeMessageActions/);
    assert.doesNotMatch(blade, /initializeMessageActions/);
    assert.doesNotMatch(blade, /btn-(?:delete|report):not\(\[data-group-chat-action\]\)/);
});

test('unread polling and observers are owned by the page lifecycle', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/scroll_unread_runtime.blade.php', 'utf8');
    const unread = readFileSync('resources/js/group-chat/unread.js', 'utf8');
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(index, /window\.GroupChatLifecycle\s*=\s*pageLifecycle/);
    assert.match(index, /app\.unread\.initialize\(\)/);
    assert.match(runtime, /lifecycle\.interval\(refreshUnreadCount, 15000\)/);
    assert.match(runtime, /lifecycle\.add\(\(\) => observer\.disconnect\(\)\)/);
    assert.match(unread, /api\.json\(descriptor\.url, \{ method: 'POST' \}\)/);
    assert.match(unread, /feed\.markRead\(descriptor\.type, descriptor\.id\)/);
    assert.match(unread, /lifecycle\.add\(\(\) => \{/);
    assert.doesNotMatch(legacy, /new IntersectionObserver/);
    assert.doesNotMatch(legacy, /\/(?:blog|poll)\/\$\{[^}]+\}\/mark-read/);
    assert.doesNotMatch(runtime, /setInterval\(refreshUnreadCount/);
});

test('realtime retries and fallback pollers are owned by the page lifecycle', () => {
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');
    const runtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');

    assert.match(index, /createRealtimeRuntime\(\{ app, \.\.\.options, groupId:/);
    assert.match(index, /app\.installRealtime\(\{ debug \}\)/);
    assert.doesNotMatch(legacy, /function (?:getGroupRealtimeState|initGroupRealtimeListeners|startPolling|syncGroupDelta)\(/);
    assert.match(runtime, /lifecycle\.timeout\(syncDelta, delay\)/);
    assert.match(runtime, /if \(hasCanonicalSync\)/);
    assert.match(runtime, /lifecycle\.interval\(pollSync, syncInterval\)/);
    assert.match(runtime, /lifecycle\.interval\(pollMessages, 3000\)/);
    assert.match(runtime, /lifecycle\.interval\(pollPosts, 5000\)/);
    assert.match(runtime, /lifecycle\.interval\(reconcilePosts, 15000\)/);
    assert.match(runtime, /lifecycle\.on\(window, 'online'/);
    assert.doesNotMatch(runtime, /(^|[^.\w])setInterval\(/m);
    assert.doesNotMatch(runtime, /window\.addEventListener\('(online|offline)'/);
});

test('realtime connection state stays internal and restricted mobile composer is compact', () => {
    const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
    const chat = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const styles = readFileSync('resources/views/groups/partials/styles/auxiliary_styles.blade.php', 'utf8');

    assert.doesNotMatch(realtime, /group-connection-status/);
    assert.doesNotMatch(realtime, /textContent\s*=\s*current\.connection/);
    assert.match(chat, /chat-composer-shell--restricted/);
    assert.match(styles, /\.chat-composer-shell--restricted\s*\{[\s\S]*?position:\s*fixed/);
    assert.match(styles, /\.chat-composer-shell--restricted \.chat-session-closed p\s*\{\s*display:\s*none/);
});

test('session participation requests notify moderators through realtime badge and polling fallback', () => {
    const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
    const participation = readFileSync('resources/js/group-chat/session-participation.js', 'utf8');
    const sessionState = readFileSync('resources/js/group-chat/session-state.js', 'utf8');
    const panel = readFileSync('resources/views/groups/partials/group_info_panel.blade.php', 'utf8');

    assert.match(realtime, /session_participation_requested/);
    assert.match(realtime, /sessionParticipation\?\.receiveRequest/);
    assert.match(participation, /pending_requests_count/);
    assert.match(participation, /listen\(window, 'group-chat:session-state'/);
    assert.match(participation, /consumeSessionState/);
    assert.match(sessionState, /lifecycle\.interval\(reconcile, 15000\)/);
    assert.doesNotMatch(participation, /lifecycle\.interval\(refreshPendingCount/);
    assert.match(participation, /GroupChatFeedback\?\.toast/);
    assert.match(panel, /id="sessionParticipationBadge"/);
});

test('poll countdown and voice recorder release their owned resources', () => {
    const runtime = readFileSync('public/js/group-chat.js', 'utf8');
    const polls = readFileSync('resources/js/group-chat/polls.js', 'utf8');
    const voice = readFileSync('public/js/voice-recorder.js', 'utf8');

    assert.match(polls, /lifecycle\.interval\(update, 1000\)/);
    assert.match(polls, /lifecycle\.clearInterval\(intervalId\)/);
    assert.match(polls, /timer\.dataset\.timerSet = 'complete'/);
    assert.doesNotMatch(runtime, /function startPollCountdowns\(/);
    assert.match(voice, /recordingTimer\s*=\s*createOwnedInterval/);
    assert.match(voice, /voiceLifecycle\?\.add\(destroyVoiceRecorder\)/);
    assert.match(voice, /destroyVoiceRecorder[\s\S]*stopTimer\(\)/);
    assert.match(voice, /audioStream\.getTracks\(\)\.forEach\(track => track\.stop\(\)\)/);
    assert.doesNotMatch(voice, /recordingTimer\s*=\s*setInterval/);
    assert.doesNotMatch(voice, /window\.addEventListener\('beforeunload'/);
});

test('voice controls keep icons centered and message edits avoid the global overlay', () => {
    const styles = readFileSync('resources/views/groups/partials/styles/base_styles.blade.php', 'utf8');
    const editRuntime = readFileSync('resources/views/groups/partials/message_edit_runtime.blade.php', 'utf8');

    assert.match(styles, /#voice-recording-modal #recording-controls button,[\s\S]*?display: inline-flex;[\s\S]*?align-items: center !important;[\s\S]*?gap: 7px !important/);
    assert.match(styles, /#voice-recording-modal button > i \{[\s\S]*?width: 1em !important;[\s\S]*?height: 1em !important;[\s\S]*?transform-origin: 50% 50% !important/);
    assert.match(styles, /#voice-recording-modal #send-recording-btn > \.fa-spinner \{[\s\S]*?voice-recorder-spin/);
    assert.doesNotMatch(editRuntime, /global-loading|showOverlay\(|hideOverlay\(/);
    assert.match(styles, /@keyframes message-edit-button-spin \{[\s\S]*?translateY\(-50%\) rotate\(360deg\)/);
    assert.match(editRuntime, /updateMessageContent\(bubbleToUpdate, optimisticContent, true\);[\s\S]*?closeModal\(\);[\s\S]*?await fetch\(requestUrl/);
    assert.match(editRuntime, /if \(!editCommitted && bubbleToUpdate\?\.isConnected\)[\s\S]*?previousContent/);
    assert.match(editRuntime, /if \(editPending\)[\s\S]*?ویرایش قبلی هنوز در حال همگام‌سازی است/);
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

test('action-menu dismissal is exclusively lifecycle-owned by Actions', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');

    assert.doesNotMatch(blade, /action_menu_dismissal/);
    assert.match(actions, /lifecycle\.on\(root, 'click'/);
    assert.match(actions, /lifecycle\.on\(root, 'keydown'/);
    assert.match(actions, /const closeAll = \(\) =>/);
});

test('chat search runtime is loaded through its dedicated partial', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const search = readFileSync('resources/views/groups/partials/chat_search_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.chat_search_runtime'\)/);
    assert.doesNotMatch(blade, /function fetchPage\(reset = false\)/);
    assert.match(search, /function fetchPage\(reset = false\)/);
    assert.match(search, /gc-search-wrap/);
    assert.match(search, /window\.GroupChatSearch = Object\.freeze/);
    assert.match(search, /delete window\.GroupChatSearch/);
    assert.match(readFileSync('resources/js/group-chat/actions.js', 'utf8'), /window\.GroupChatSearch\?\.\[method\]/);
    assert.doesNotMatch(readFileSync('public/js/group-chat.js', 'utf8'), /function (?:openChatSearch|closeChatSearch)\(/);
    assert.match(search, /<script type="module">/);
    assert.match(search, /lifecycle\.on\(input, 'input'/);
    assert.match(search, /lifecycle\.on\(listEl, 'click'/);
    assert.doesNotMatch(search, /window\.__(?:setSearching|ensureSearchOpen)/);
    assert.doesNotMatch(search, /\.addEventListener\(/);
    assert.doesNotMatch(search, /(^|[^.\w])set(?:Timeout|Interval)\(/m);
    assert.doesNotMatch(search, /window\.__groupChatSearchInitialized/);
});

test('pin operations are owned by modular Actions and ApiClient', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const pins = readFileSync('resources/js/group-chat/pins.js', 'utf8');

    assert.doesNotMatch(blade, /pin_runtime/);
    assert.match(pins, /actions\.register\('pin-content'/);
    assert.match(pins, /actions\.register\('unpin-content'/);
    assert.match(pins, /api\.json\(window\.GroupChatConfig\.pinsUrl/);
});

test('scroll and unread runtime is loaded through its dedicated partial', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/scroll_unread_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.scroll_unread_runtime'\)/);
    assert.doesNotMatch(blade, /function initializeGroupChatScrollManager\(/);
    assert.match(runtime, /function initializeGroupChatScrollManager\(/);
    assert.match(runtime, /function restoreInitialPosition\(/);
    assert.match(runtime, /function renderUnreadIndicators\(/);
});

test('floating chat scroll button follows direction and navigates both endpoints', () => {
    const runtime = readFileSync('resources/views/groups/partials/scroll_unread_runtime.blade.php', 'utf8');
    assert.match(runtime, /setScrollButtonMode\('bottom'\)/);
    assert.match(runtime, /delta < 0 && currentScrollTop > 72/);
    assert.match(runtime, /setScrollButtonMode\('top'\)/);
    assert.match(runtime, /scrollToBeginning\(true\)/);
    assert.match(runtime, /scrollToLatest\(true\)/);
    assert.match(runtime, /scrollButtonAwake = false/);
    assert.match(runtime, /lifecycle\.timeout\(function\(\) \{[\s\S]*scrollButtonAwake = false;[\s\S]*\}, 1800\)/);
});

test('mobile group hero is edge-to-edge with balanced avatar spacing', () => {
    const styles = readFileSync('resources/views/groups/partials/styles/auxiliary_styles.blade.php', 'utf8');
    assert.match(styles, /\[data-group-hero\]\.group-info-card[\s\S]*width: calc\(100% \+ 2rem\)/);
    assert.match(styles, /min-height: 86px/);
    assert.match(styles, /group-hero__avatar--mobile[\s\S]*padding: 3px/);
});

test('composer actions and modal state are owned by the modular Composer', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/js/group-chat/composer.js', 'utf8');
    const post = readFileSync('resources/views/groups/modals/post_form.blade.php', 'utf8');
    const poll = readFileSync('resources/views/groups/modals/poll_form.blade.php', 'utf8');

    assert.doesNotMatch(blade, /composer_actions_runtime/);
    assert.doesNotMatch(blade, /const plusButton = document\.getElementById\('chatCreateToggle'\)/);
    assert.match(runtime, /const plusButton = document\.getElementById\('chatCreateToggle'\)/);
    assert.match(runtime, /lifecycle\.on\(textarea, 'input'/);
    assert.match(runtime, /lifecycle\.on\(document, 'click'/);
    assert.match(runtime, /lifecycle\.on\(document, 'keydown'/);
    assert.match(runtime, /store\.setState\(\{ composerModal: open \? type : null \}\)/);
    assert.match(runtime, /actions\.register\('open-blog', openPost\)/);
    assert.match(post, /data-composer-modal="post"/);
    assert.match(poll, /data-composer-modal="poll"/);
    assert.doesNotMatch(post + poll, /\son(?:click|change)=/i);
});

test('post submission runtime is extracted without patching openBlogBox', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const composer = readFileSync('resources/js/group-chat/composer.js', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');
    const adapters = readFileSync('resources/js/group-chat/legacy-renderers.js', 'utf8');
    const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
    const operations = readFileSync('resources/js/group-chat/operations.js', 'utf8');
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');

    assert.doesNotMatch(blade, /post_submission_runtime/);
    assert.doesNotMatch(blade, /function interceptPostForm\(/);
    assert.match(composer, /initializePostSubmission\(\{ feedBridge \}\)/);
    assert.match(composer, /lifecycle\.on\(form, 'submit'/);
    assert.match(composer, /api\.json\(form\.action/);
    assert.match(composer, /feedBridge\.create\('post', data\.post, 'local-post-submit'\)/);
    assert.doesNotMatch(composer, /\.addEventListener\(/);
    assert.match(index, /app\.installLegacyRenderers\(\{ updateLastPostCursor:/);
    assert.match(adapters, /const bridge = Object\.freeze/);
    assert.match(adapters, /app\.feedBridge = bridge/);
    assert.doesNotMatch(groupChat, /window\.GroupChat(?:LegacyMessageMutations|LegacyFeedRenderers|FeedBridge)/);
    assert.match(adapters, /app\.feed\.apply/);
    assert.match(adapters, /callbacks\.updateLastPostCursor/);
    assert.match(adapters, /mutate\(type, action, payload, source = 'local'\)/);
    assert.match(realtime, /feedBridge\.create\('post', item, 'polling-fallback'\)/);
    assert.match(realtime, /feedBridge\.mutate\('post', 'delete', \{ id \}, 'polling-fallback'\)/);
    assert.match(realtime, /feedBridge\.mutate\('post', 'update', item, 'polling-fallback'\)/);
    assert.match(realtime, /feedBridge\.mutate\('post', 'delete', \{ id \}, 'reconcile-fallback'\)/);
    assert.match(operations, /feed\.mutate\(\{ content_type: 'post', id, action: 'delete' \}, 'local-post-delete'\)/);
    assert.match(operations, /feed\.mutate\(\{ \.\.\.post, content_type: 'post', id, action: 'update' \}, 'local-post-edit'\)/);
    assert.match(adapters, /const updatePostFields = item =>/);
    assert.doesNotMatch(groupChat, /function updateBlogUI\(/);
    assert.doesNotMatch(groupChat, /wrapperEl\.replaceWith\(/);
    assert.doesNotMatch(groupChat, /_lastKnownPostId/);
});

test('post menus and reactions use lifecycle-owned event delegation', () => {
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const features = readFileSync('public/js/chat-features.js', 'utf8');
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');

    assert.match(actions, /lifecycle\.on\(root, 'click'/);
    assert.match(actions, /lifecycle\.on\(root, 'keydown'/);
    assert.match(actions, /lifecycle\.on\(window, 'resize'/);
    assert.match(actions, /lifecycle\.on\(root, 'scroll'/);
    assert.match(actions, /\.reaction-buttons \.btn-like, \.reaction-buttons \.btn-dislike/);
    assert.match(actions, /event\.target\.closest\?\.\('\.action-menu__toggle'\)/);
    assert.match(actions, /menuAction && !menuAction\.classList\.contains\('btn-reaction'\)/);
    assert.match(actions, /const reactToPost = async/);
    assert.match(actions, /api\.json\(`\/blogs\/\$\{blogId\}\/react`/);
    assert.doesNotMatch(groupChat, /function sendReaction|setPostReactionHandler/);
    assert.match(features, /GroupChat\?\.actions\?\.closeAllActionMenus/);
    assert.doesNotMatch(groupChat, /window\.closeAllActionMenus|__groupChatPostInteractionsDelegated/);
    assert.doesNotMatch(groupChat, /_initPostMenus/);
    assert.doesNotMatch(groupChat, /_initReactionButtons/);
    assert.doesNotMatch(groupChat, /_menuInit|_reactionInit/);
    assert.doesNotMatch(groupChat, /messageRow\.querySelector\('\[data-action-menu\]'\)/);
    assert.doesNotMatch(blade, /messageRow\.querySelector\('\[data-action-menu\]'\)/);
});

test('post editing uses plain text, closes its backdrop, and keeps metadata last', () => {
    const post = readFileSync('resources/views/groups/partials/post.blade.php', 'utf8');
    const operations = readFileSync('resources/js/group-chat/operations.js', 'utf8');
    const styles = readFileSync('resources/views/groups/partials/styles/base_styles.blade.php', 'utf8');
    const controller = readFileSync('app/Http/Controllers/Group/BlogController.php', 'utf8');

    assert.match(post, /editablePostContent = html_entity_decode/);
    assert.match(post, /trim\(strip_tags\(\$editablePostContent\)\)/);
    assert.match(post, /post-edit-modal__close/);
    assert.match(post, /post-edit-modal__dismiss/);
    assert.ok(post.indexOf('post-card__comments--cta') < post.indexOf('post-card__footer content-meta-line'));
    assert.match(styles, /\.post-edit-modal__close \{[\s\S]*?color: #64748b !important/);
    assert.match(styles, /\.post-edit-modal__close:hover,[\s\S]*?color: #1f2937 !important/);
    assert.match(styles, /\.post-edit-modal__dismiss \{[\s\S]*?color: #64748b !important/);
    assert.match(styles, /\.post-edit-modal__close \{[\s\S]*?position: absolute !important;[\s\S]*?left: 16px !important;[\s\S]*?right: auto !important/);
    assert.match(controller, /str_replace\(\["\\r\\n", "\\r"\], "\\n", \$submittedContent\)/);
    assert.match(controller, /str_replace\("\\n", '<br>', e\(\$submittedContent\)\)/);
    assert.doesNotMatch(controller, /nl2br\(e\(\$submittedContent\)/);
    assert.match(post, /<br\\s\*\\\/\?>\[ \\t\]\*\(\?:\\r\\n\|\\r\|\\n\)\?/);
    assert.match(operations, /await closePostEditModal\(id\);[\s\S]*?feed\.mutate/);
    assert.match(operations, /document\.querySelectorAll\('\.modal-backdrop'\)\.forEach\(element => element\.remove\(\)\)/);
});

test('post comment counts sync across the comment page and group feed', () => {
    const commentPage = readFileSync('resources/views/groups/comment.blade.php', 'utf8');
    const post = readFileSync('resources/views/groups/partials/post.blade.php', 'utf8');
    const renderers = readFileSync('resources/js/group-chat/legacy-renderers.js', 'utf8');
    const controller = readFileSync('app/Http/Controllers/Group/CommentController.php', 'utf8');
    const reactionBlock = commentPage.slice(
        commentPage.indexOf('function sendReaction(type)'),
        commentPage.indexOf('// Comment reaction functions'),
    );

    assert.doesNotMatch(reactionBlock, /showOverlay\(\)/);
    assert.match(reactionBlock, /const previousLikes = Number/);
    assert.match(commentPage, /commentsCount\.textContent = String\(data\.comments_count\)/);
    assert.match(post, /post-card__comments-count/);
    assert.match(renderers, /#blog-\$\{postId\} \.post-card__comments-count/);
    assert.match(controller, /'comment_created'/);
    assert.match(controller, /'comments_count' => \$commentsCount/);
});

test('message edit runtime is extracted and lifecycle-owned', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/message_edit_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.message_edit_runtime'\)/);
    assert.doesNotMatch(blade, /const modal = document\.getElementById\('editModal'\)/);
    assert.match(runtime, /function initializeMessageEditRuntime\(\)/);
    assert.match(runtime, /<script type="module">/);
    assert.match(runtime, /lifecycle\.on\(document, 'click'/);
    assert.match(runtime, /lifecycle\.on\(btnSave, 'click'/);
    assert.match(runtime, /lifecycle\.on\(document, 'keydown'/);
    assert.match(runtime, /lifecycle\.add\(function\(\)/);
    assert.doesNotMatch(runtime, /\.addEventListener\(/);
    assert.match(runtime, /initializeMessageEditRuntime\(\);/);
    assert.doesNotMatch(runtime, /window\.__groupChatMessageEditInitialized/);
    assert.doesNotMatch(runtime, /btnSave\.addEventListener/);
});

test('ckeditor runtime is extracted and lifecycle-owned', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/ckeditor_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.ckeditor_runtime'\)/);
    assert.doesNotMatch(blade, /function installCkeditorChatConfig\(/);
    assert.match(runtime, /function initializeGroupChatCkeditorRuntime\(\)/);
    assert.match(runtime, /<script type="module">/);
    assert.match(runtime, /lifecycle\.interval\(function\(\)/);
    assert.match(runtime, /lifecycle\.clearInterval\(ckeditorWait\)/);
    assert.match(runtime, /ckeditor\.instances\?\.post_editor/);
    assert.match(runtime, /instance\.destroy\(true\)/);
    assert.match(runtime, /lifecycle\.add\(function\(\)/);
    assert.doesNotMatch(runtime, /(^|[^.])setInterval\(/m);
    assert.doesNotMatch(runtime, /\.addEventListener\(/);
    assert.doesNotMatch(runtime, /window\.__groupChatCkeditorInitialized/);
});

test('legacy message runtime is retired behind modular owners', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const unread = readFileSync('resources/js/group-chat/unread.js', 'utf8');
    const category = readFileSync('resources/js/group-chat/category-browser.js', 'utf8');
    const edit = readFileSync('resources/views/groups/partials/message_edit_runtime.blade.php', 'utf8');

    assert.doesNotMatch(blade, /legacy_message_runtime/);
    assert.match(unread, /updateLastMessage\(messageId\)/);
    assert.match(category, /export function createCategoryBrowser/);
    assert.match(category, /lifecycle\.on\(document, 'click'/);
    assert.match(category, /style\.setProperty\('display', 'flex'\)/);
    assert.match(category, /event\.target === modal/);
    assert.match(category, /const cache = new Map\(\)/);
    assert.match(category, /category-browser__view/);
    assert.match(edit, /window\.GroupChat\.feed\.mutate/);
});

test('page chrome runtime owns group edit and one-shot page effects', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/page_chrome_runtime.blade.php', 'utf8');
    const groupEdit = readFileSync('resources/views/groups/modals/group_edit_form.blade.php', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.page_chrome_runtime'\)/);
    assert.doesNotMatch(blade, /function openGroupEdit\(/);
    assert.doesNotMatch(blade, /function cancelGroupEdit\(/);
    assert.match(runtime, /window\.GroupChatPageChrome = Object\.freeze/);
    assert.match(runtime, /lifecycle\.on\(window, 'load'/);
    assert.match(runtime, /querySelectorAll\('\[data-group-chat-flash\]'\)/);
    assert.match(runtime, /flash\.classList\.add\('group-chat-flash--leaving'\)/);
    assert.match(runtime, /lifecycle\.timeout\(function\(\) \{ flash\.remove\(\); \}, 260\)/);
    assert.match(runtime, /delete window\.GroupChatPageChrome/);
    assert.match(groupEdit, /data-group-chat-action="cancel-group-edit"/);
    assert.doesNotMatch(groupEdit, /onclick=/);
    assert.doesNotMatch(groupChat, /handleDelegatedLegacyChatAction/);
    assert.match(actions, /'open-group-edit': 'openGroupEdit'/);
    assert.match(actions, /'cancel-group-edit': 'cancelGroupEdit'/);
    assert.match(runtime, /showEditPollBox\(pollId\)/);
    assert.match(runtime, /querySelectorAll\('\[id\^="edit-poll-box-"\]'\)/);
    assert.match(actions, /'edit-poll': 'showEditPollBox'/);
    assert.doesNotMatch(blade, /function (?:togglePollMenu|showEditPollBox|confirmDelete)\(/);
});

test('chat page styles are extracted in cascade order', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const includes = [
        "@include('groups.partials.styles.base_styles')",
        "@include('groups.partials.styles.message_edit_styles')",
        "@include('groups.partials.styles.auxiliary_styles')",
    ];

    assert.doesNotMatch(blade, /<style>/);
    assert.ok(includes.every(include => blade.includes(include)));
    assert.ok(includes.every((include, index) => index === 0 || blade.indexOf(includes[index - 1]) < blade.indexOf(include)));
});

test('group hero markup is loaded through its dedicated partial', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const hero = readFileSync('resources/views/groups/partials/group_hero.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.group_hero'\)/);
    assert.doesNotMatch(blade, /class="[^"]*group-info-card/);
    assert.match(hero, /class="[^"]*group-info-card/);
    assert.match(hero, /data-chat-page-action="open-group-info"/);
    assert.match(hero, /data-chat-page-action="open-election"/);
    assert.match(hero, /route\('groups\.najm-bahar\.reports', \$group\)/);
    assert.doesNotMatch(hero, /data-chat-page-action="open-blog"/);
    assert.doesNotMatch(hero, /data-chat-page-action="open-poll"/);
    assert.match(hero, /data-group-chat-action="toggle-group-hero"/);
    assert.match(hero, /aria-expanded="false"/);
    assert.match(hero, /group-hero__avatar w-16 h-16/);
    const chatCss = readFileSync('public/Css/group-chat.css', 'utf8');
    assert.match(chatCss, /\.group-hero__avatar \{[\s\S]*?flex: 0 0 4rem;[\s\S]*?aspect-ratio: 1 \/ 1;/);
    assert.match(chatCss, /\.group-hero__avatar img \{[\s\S]*?object-fit: cover;/);
    assert.doesNotMatch(hero, /(?:@click|x-data|x-show|x-cloak|:class)=?/);
    assert.match(readFileSync('resources/views/groups/partials/page_chrome_runtime.blade.php', 'utf8'), /toggleGroupHero\(\)/);
    assert.match(readFileSync('resources/js/group-chat/actions.js', 'utf8'), /'toggle-group-hero': 'toggleGroupHero'/);
});

test('all declarative chat actions use the lifecycle-owned modular dispatcher', () => {
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(actions, /lifecycle\.on\(root, 'click'/);
    assert.match(actions, /\[data-group-chat-action\], \[data-legacy-chat-action\], \[data-chat-page-action\]/);
    assert.match(index, /const pageActions = createActions\(\{ lifecycle: pageLifecycle \}\)/);
    assert.ok(index.indexOf('const pageActions') < index.indexOf('if (window.groupId)'));
    assert.doesNotMatch(groupChat, /handleDelegatedLegacyChatAction/);
    assert.doesNotMatch(groupChat, /function (?:openGroupInfo|closeGroupInfo)\(/);
    assert.match(actions, /const openGroupInfo = \(\) =>/);
    assert.match(actions, /const closeGroupInfo = \(\) =>/);
});

test('canonical modular runtime is not bypassed by the migration feature flag', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');
    const adapters = readFileSync('resources/js/group-chat/legacy-renderers.js', 'utf8');
    const composer = readFileSync('resources/js/group-chat/composer.js', 'utf8');

    assert.match(index, /if \(window\.groupId\)/);
    assert.doesNotMatch(index, /if \(window\.__groupChatModularFrontend/);
    assert.doesNotMatch(groupChat, /window\.__groupChatModularFrontend && window\.GroupChat/);
    assert.match(composer, /feed\.apply\(\[\{/);
    assert.match(composer, /\], 'optimistic'\)/);
    assert.match(adapters, /app\.feed\.apply\(/);
    assert.match(adapters, /app\.feed\.mutate\(/);
});

test('renderer adapters and feed bridge are owned by a modular registry', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const adapters = readFileSync('resources/js/group-chat/legacy-renderers.js', 'utf8');
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');
    const messages = readFileSync('resources/js/group-chat/message-renderer.js', 'utf8');

    assert.match(index, /installLegacyRenderers\(\{ app, callbacks \}\)/);
    assert.match(adapters, /render: renderMessage/);
    assert.match(messages, /export function renderMessage/);
    assert.doesNotMatch(legacy, /function (?:renderMessageThroughPipeline|appendMessage)\(/);
    assert.match(adapters, /app\.renderer\.register\('message'/);
    assert.match(adapters, /Object\.entries\(adapters\)\.forEach/);
    assert.match(adapters, /app\.feed\.apply/);
    assert.match(adapters, /app\.feed\.mutate/);
    assert.match(index, /app\.installLegacyRenderers\(\{ updateLastPostCursor:/);
    assert.doesNotMatch(legacy, /legacyMessageMutations|legacyFeedRenderers|registerLegacyRenderers/);
    assert.doesNotMatch(legacy, /function (?:appendRenderedFeedHtml|replaceRenderedFeedHtml|removeMessageDom)\(/);
});

test('poll operations are owned by the modular Polls runtime', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const polls = readFileSync('resources/js/group-chat/polls.js', 'utf8');
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');
    const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');

    assert.match(index, /import \{ createPolls \} from '\.\/polls\.js'/);
    assert.match(index, /app\.polls = createPolls\(/);
    assert.match(polls, /actions\.register\('submit-vote'/);
    assert.match(polls, /actions\.register\('delete-poll'/);
    assert.match(polls, /lifecycle\.on\(document, 'submit'/);
    assert.match(polls, /feed\.apply\(\[item\], 'local-poll-create'\)/);
    assert.match(polls, /feed\.mutate\(item, 'local-poll-edit'\)/);
    assert.doesNotMatch(actions, /'submit-vote': \['submitVote'\]|'delete-poll': \['deletePoll'/);
    assert.doesNotMatch(groupChat, /function (?:submitVote|updatePollUI)\(|window\.deletePoll/);
    assert.match(realtime, /feedBridge\.mutate\('poll', 'vote', poll, 'websocket-poll'\)/);
});

test('poll votes toggle off and remote updates preserve each viewer selection', () => {
    const polls = readFileSync('resources/js/group-chat/polls.js', 'utf8');
    const controller = readFileSync('app/Http/Controllers/Group/PollController.php', 'utf8');
    const css = readFileSync('public/Css/group-chat.css', 'utf8');

    assert.doesNotMatch(polls, /if \(target\.classList\.contains\('voted'\)\) return/);
    assert.match(polls, /hasOwnProperty\.call\(pollData, 'user_option_id'\)/);
    assert.match(polls, /data\.vote_removed \? 'رای شما برداشته شد'/);
    assert.match(controller, /\$existing && \(int\) \$existing->option_id === \$selectedOptionId/);
    assert.match(controller, /'user_option_id' => \$voteRemoved \? null/);
    assert.match(controller, /unset\(\$broadcastPayload\['user_option_id'\]\)/);
    assert.doesNotMatch(css, /\.poll-card__hero \.action-menu \{[\s\S]*?inset-inline-start: 1rem/);
});

test('election page state and actions are lifecycle-owned', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const elections = readFileSync('resources/js/group-chat/elections.js', 'utf8');
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(index, /createElections/);
    assert.match(elections, /actions\.register\('open-election', open\)/);
    assert.match(elections, /actions\.register\('close-election', close\)/);
    assert.match(elections, /actions\.register\('open-election-admin', openAdmin\)/);
    assert.match(elections, /actions\.register\('close-election-admin', closeAdmin\)/);
    assert.match(elections, /store\.setState\(\{ electionOpen: true \}\)/);
    assert.match(elections, /lifecycle\.timeout\(/);
    assert.match(elections, /lifecycle\.on\(document, 'keydown'/);
    assert.doesNotMatch(actions, /'open-election': \['openElectionBox'\]|'close-election': \['closeElectionBox'/);
    assert.doesNotMatch(groupChat, /function (?:openElectionBox|closeElectionBox|openElection2Box)\(/);
    assert.doesNotMatch(groupChat, /function cancelelectionForm\(/);
    assert.doesNotMatch(groupChat, /\$\('#electionForm'\)\.on/);
});

test('election admin modal is centered and election cards are distinct from surveys', () => {
    const elections = readFileSync('resources/js/group-chat/elections.js', 'utf8');
    const form = readFileSync('resources/views/groups/modals/election_form.blade.php', 'utf8');
    const poll = readFileSync('resources/views/groups/partials/poll.blade.php', 'utf8');
    const css = readFileSync('public/Css/group-chat.css', 'utf8');

    assert.match(elections, /modal\.style\.display = 'flex'/);
    assert.match(elections, /document\.body\.appendChild\(modal\)/);
    assert.match(elections, /document\.body\.style\.overflow = 'hidden'/);
    assert.match(form, /class="modal-shell election-admin-modal"/);
    assert.match(form, /aria-labelledby="electionAdminModalTitle"/);
    assert.match(css, /#electionOptionsBox \{[\s\S]*?inset: 0;[\s\S]*?align-items: center;[\s\S]*?justify-content: center;/);
    assert.match(css, /#electionOptionsBox \.modal-shell__dialog \{[\s\S]*?width: min\(720px, 100%\)/);
    assert.match(poll, /\$isElection = \(int\) \(\$item->main_type/);
    assert.match(poll, /'انتخابات تخصصی' : 'انتخابات عمومی'/);
    assert.match(poll, /poll-card--election/);
    assert.match(css, /\.poll-card--election \{/);
});

test('group info tabs are modular, scoped, and store-backed', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const tabs = readFileSync('resources/js/group-chat/tabs.js', 'utf8');
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(index, /createTabs\(\{ store, lifecycle \}\)/);
    assert.match(tabs, /activeInfoTab/);
    assert.match(tabs, /\.panel-tabs \.tab\[data-tab\]/);
    assert.match(tabs, /\.panel-tab-contents > \.tab-content/);
    assert.doesNotMatch(legacy, /Tabs script loaded/);
});

test('group info and election panel handlers are lifecycle-owned and declarative', () => {
    const panel = readFileSync('resources/views/groups/partials/group_info_panel.blade.php', 'utf8');
    const election = readFileSync('resources/views/groups/modals/election_modal.blade.php', 'utf8');
    const elections = readFileSync('resources/js/group-chat/elections.js', 'utf8');
    assert.match(panel, /<script type="module">/);
    assert.match(panel, /const groupInfoLifecycle = window\.GroupChatLifecycle/);
    assert.match(panel, /window\.GroupInfoPanel = Object\.freeze/);
    assert.doesNotMatch(panel, /\.addEventListener\(/);
    assert.doesNotMatch(panel, /(^|[^.\w])set(?:Timeout|Interval)\(/m);
    assert.doesNotMatch(panel, /window\.(?:cancelAddGuests|cancelManagerChat|loadGroupStats)\s*=/);
    assert.doesNotMatch(panel + election, /\son(?:click|submit|change|input)=/i);
    assert.doesNotMatch(election, /(^|[^.\w])set(?:Timeout|Interval)\(/m);
    assert.doesNotMatch(election, /\.addEventListener\(/);
    assert.doesNotMatch(election, /window\.(?:electionAllOptions|openCandidatesModal|openGuidelineModal|openTopVotesModal|profileUrlOf|applyFilters|updateElectionSelect2)\s*=/);
    assert.match(election, /window\.GroupElectionModal = \{/);
    for (const action of ['election-content', 'open-election-candidates', 'open-election-guideline', 'open-election-top-votes']) {
        assert.match(elections, new RegExp(`actions\\.register\\('${action}'`));
    }
});

test('manager chat request modal uses scoped responsive cards without duplicated flash messages', () => {
    const panel = readFileSync('resources/views/groups/partials/group_info_panel.blade.php', 'utf8');
    const request = readFileSync('resources/views/chat_request.blade.php', 'utf8');

    assert.match(panel, /manager-request-card__identity/);
    assert.match(panel, /manager-request-form__submit/);
    assert.match(panel, /data-manager-search-text/);
    assert.match(panel, /#chatRequestModal \.panel-modal__dialog/);
    assert.match(panel, /data-manager-chat-tab="outgoing"/);
    assert.match(panel, /data-manager-chat-tab="incoming"/);
    assert.match(panel, /data-manager-chat-pane="incoming"/);
    assert.doesNotMatch(panel, /@include\('chat_request', \['user' => auth\(\)->user\(\)\]\)/);
    assert.match(panel, /@media \(max-width: 767px\)[\s\S]*?\.manager-item \{ grid-template-columns: 1fr;/);
    assert.doesNotMatch(request, /session\('success'\)/);
    assert.doesNotMatch(request, /session\('error'\)/);
});

test('poll skill-list UI is modular and store-backed', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const skills = readFileSync('resources/js/group-chat/skill-lists.js', 'utf8');
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(index, /createSkillLists\(\{ api, actions, store, lifecycle \}\)/);
    assert.match(skills, /openSkillListId/);
    assert.match(skills, /actions\.register\('toggle-skill-list'/);
    assert.match(skills, /api\.json\(element\.dataset\.expertsUrl\)/);
    assert.match(skills, /actions\.register\('delegate-vote'/);
    assert.doesNotMatch(skills, /getElementById\('back'\)/);
    assert.match(skills, /lifecycle\.add\(close\)/);
    assert.doesNotMatch(actions, /'toggle-skill-list': \['toggleSkillList'/);
    assert.doesNotMatch(legacy, /function (?:toggleSkillList|closeSkill|reapplySkillListState)\(/);
});

test('typing indicator is store-backed and lifecycle-owned', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const typing = readFileSync('resources/js/group-chat/typing.js', 'utf8');
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(index, /createTyping\(\{ store, lifecycle, authUserId:/);
    assert.match(typing, /typingUsers/);
    assert.match(typing, /store\.subscribe/);
    assert.match(typing, /lifecycle\.timeout\(clear, 3000\)/);
    const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
    assert.match(realtime, /app\.typing\?\.apply\(payload\)/);
    assert.doesNotMatch(legacy, /remoteTypingUsers|typingClearTimer|function renderTypingIndicator/);
});

test('legacy group runtime has no active raw page listeners or timers', () => {
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8').replace(/^\s*\/\/.*$/gm, '');
    const unread = readFileSync('resources/js/group-chat/unread.js', 'utf8');
    const composer = readFileSync('resources/js/group-chat/composer.js', 'utf8');
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');

    assert.doesNotMatch(groupChat, /\.addEventListener\(/);
    assert.doesNotMatch(groupChat, /(^|[^.\w])set(?:Timeout|Interval)\(/m);
    assert.doesNotMatch(groupChat, /(^|[^.\w])clear(?:Timeout|Interval)\(/m);
    assert.match(composer, /lifecycle\.on\(form, 'submit'/);
    assert.match(index, /app\.composer\.initializeSubmission/);
    assert.doesNotMatch(groupChat, /legacyLifecycle\.on\(form, 'submit'/);
    assert.match(unread, /lifecycle\.add\(\(\) => \{/);
    assert.doesNotMatch(groupChat, /window\.groupChat(?:Notify|Confirm|Prompt)/);
    assert.doesNotMatch(groupChat, /window\.replyToMessageFromButton/);
    assert.match(composer, /actions\.register\('reply'/);
    assert.match(composer, /actions\.register\('cancel-reply'/);
    assert.match(composer, /composerReply/);
    assert.doesNotMatch(groupChat.replace(/\/\*[\s\S]*?\*\//g, ''), /function (?:replyToMessage|replyToMessageFromButton|cancelReply)\(/);
    for (const file of files) {
        assert.doesNotMatch(readFileSync(file, 'utf8'), /window\.groupChat(?:Notify|Confirm|Prompt)/, file);
    }
});

test('message, post, and chat management operations are modular actions', () => {
    const operations = readFileSync('resources/js/group-chat/operations.js', 'utf8');
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');
    const post = readFileSync('resources/views/groups/partials/post.blade.php', 'utf8');

    for (const action of ['delete-message', 'report-message', 'delete-post', 'clear-chat', 'delete-chat', 'report-user', 'submit-report']) {
        assert.match(operations, new RegExp(`(?:register|actions\\.register)\\('${action}'`));
    }
    assert.match(index, /createOperations\(\{ api, store, feed, actions, lifecycle/);
    assert.match(operations, /lifecycle\.on\(document, 'submit'/);
    assert.match(post, /data-post-edit-form/);
    assert.doesNotMatch(post, /onsubmit=/);
    assert.doesNotMatch(legacy, /function (?:deleteMessage|reportMessage|deletePost|submitPostEdit|clearChatHistory|deleteChat|reportUser|submitReport)\(/);
});

test('group pins are polymorphic, realtime, and navigable without refresh', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const pins = readFileSync('resources/js/group-chat/pins.js', 'utf8');
    const realtime = readFileSync('resources/js/group-chat/realtime-runtime.js', 'utf8');
    const message = readFileSync('resources/views/groups/partials/message.blade.php', 'utf8');
    const post = readFileSync('resources/views/groups/partials/post.blade.php', 'utf8');
    const poll = readFileSync('resources/views/groups/partials/poll.blade.php', 'utf8');

    assert.match(index, /createPins\(\{ api, actions, lifecycle, groupId:/);
    assert.match(pins, /actions\.register\('pin-content'/);
    assert.match(pins, /actions\.register\('unpin-content'/);
    assert.match(pins, /actions\.register\('previous-pin'/);
    assert.match(pins, /actions\.register\('next-pin'/);
    assert.match(pins, /scrollIntoView\(\{ behavior: 'smooth'/);
    assert.match(realtime, /action === 'pin_updated'/);
    for (const [source, type] of [[message, 'message'], [post, 'post'], [poll, 'poll']]) {
        assert.match(source, new RegExp(`data-content-type="${type}"`));
        assert.match(source, /data-chat-page-action="pin-content"/);
    }
});

test('chat canvas keeps first content spaced and uses the local responsive background', () => {
    const styles = readFileSync('resources/views/groups/partials/styles/auxiliary_styles.blade.php', 'utf8');

    assert.match(styles, /#chat-box\.chat-body\s*\{[\s\S]*?padding:\s*clamp\(/);
    assert.match(styles, /url\('\/images\/EarthCoopChatBacgrand\.png'\)/);
    assert.match(styles, /background-size:\s*cover/);
    assert.match(styles, /@media \(max-width: 767px\)[\s\S]*?#chat-box\.chat-body[\s\S]*?background-size:\s*cover/);
    assert.doesNotMatch(styles, /background-size:\s*auto 100%/);
});
