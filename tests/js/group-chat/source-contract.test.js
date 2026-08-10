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

test('sidecar runtimes expose explicit ownership APIs', () => {
    const features = readFileSync('public/js/chat-features.js', 'utf8');
    const voice = readFileSync('public/js/voice-recorder.js', 'utf8');
    assert.match(features, /window\.GroupChatFeatures\s*=\s*Object\.freeze/);
    assert.match(voice, /window\.GroupVoiceRecorder\s*=\s*Object\.freeze/);
    assert.match(voice, /function installOptimisticVoiceBridge/);
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

    assert.match(index, /createRealtimeRuntime\(\{ app, groupId:/);
    assert.match(index, /app\.installRealtime\(\{ debug \}\)/);
    assert.doesNotMatch(legacy, /function (?:getGroupRealtimeState|initGroupRealtimeListeners|startPolling|syncGroupDelta)\(/);
    assert.match(runtime, /lifecycle\.timeout\(syncDelta, delay\)/);
    assert.match(runtime, /lifecycle\.interval\(pollMessages, 1000\)/);
    assert.match(runtime, /lifecycle\.interval\(pollPosts, 3000\)/);
    assert.match(runtime, /lifecycle\.interval\(reconcilePosts, 10000\)/);
    assert.match(runtime, /lifecycle\.on\(window, 'online'/);
    assert.doesNotMatch(runtime, /(^|[^.\w])setInterval\(/m);
    assert.doesNotMatch(runtime, /window\.addEventListener\('(online|offline)'/);
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
    const operations = readFileSync('resources/js/group-chat/operations.js', 'utf8');

    assert.doesNotMatch(blade, /pin_runtime/);
    assert.match(operations, /register\('pin', togglePin\(true\)\)/);
    assert.match(operations, /register\('unpin', togglePin\(false\)\)/);
    assert.match(operations, /api\.json\(`\/groups\/messages\/\$\{id\}\/\$\{pinned \? 'pin' : 'unpin'\}`/);
    assert.match(operations, /pinnedMessages:/);
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
    assert.match(hero, /data-chat-page-action="open-blog"/);
    assert.match(hero, /data-chat-page-action="open-poll"/);
    assert.match(hero, /data-group-chat-action="toggle-group-hero"/);
    assert.match(hero, /aria-expanded="false"/);
    assert.doesNotMatch(hero, /(?:@click|x-data|x-show|x-cloak|:class)=?/);
    assert.match(readFileSync('resources/views/groups/partials/page_chrome_runtime.blade.php', 'utf8'), /toggleGroupHero\(\)/);
    assert.match(readFileSync('resources/js/group-chat/actions.js', 'utf8'), /'toggle-group-hero': 'toggleGroupHero'/);
});

test('all declarative chat actions use the lifecycle-owned modular dispatcher', () => {
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');
    const adapters = readFileSync('resources/js/group-chat/legacy-renderers.js', 'utf8');
    const composer = readFileSync('resources/js/group-chat/composer.js', 'utf8');

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

test('poll skill-list UI is modular and store-backed', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const skills = readFileSync('resources/js/group-chat/skill-lists.js', 'utf8');
    const actions = readFileSync('resources/js/group-chat/actions.js', 'utf8');
    const legacy = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(index, /createSkillLists\(\{ actions, store, lifecycle \}\)/);
    assert.match(skills, /openSkillListId/);
    assert.match(skills, /actions\.register\('toggle-skill-list'/);
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
