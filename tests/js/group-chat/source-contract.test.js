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
    'resources/views/groups/partials/group_hero.blade.php',
    'resources/views/groups/partials/action_menu_dismissal.blade.php',
    'resources/views/groups/partials/chat_search_runtime.blade.php',
    'resources/views/groups/partials/pin_runtime.blade.php',
    'resources/views/groups/partials/scroll_unread_runtime.blade.php',
    'resources/views/groups/partials/message_edit_runtime.blade.php',
    'resources/views/groups/partials/ckeditor_runtime.blade.php',
    'resources/views/groups/partials/legacy_message_runtime.blade.php',
    'resources/views/groups/partials/page_chrome_runtime.blade.php',
    'resources/views/groups/modals/group_edit_form.blade.php',
    'resources/views/groups/partials/styles/base_styles.blade.php',
    'resources/views/groups/partials/styles/message_edit_styles.blade.php',
    'resources/views/groups/partials/styles/auxiliary_styles.blade.php',
    'resources/views/groups/partials/composer_actions_runtime.blade.php',
    'resources/views/groups/partials/post_submission_runtime.blade.php',
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
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const legacyMessageRuntime = readFileSync('resources/views/groups/partials/legacy_message_runtime.blade.php', 'utf8');

    for (const action of ['delete-message', 'report-message']) {
        assert.match(runtime, new RegExp(`data-group-chat-action="${action}"`));
        assert.match(message, new RegExp(`data-group-chat-action="${action}"`));
        assert.match(legacyMessageRuntime, new RegExp(`data-group-chat-action="${action}"`));
    }
    assert.doesNotMatch(runtime, /querySelector\(["']\.btn-(?:delete|report)["']\)\?\.addEventListener/);
    assert.doesNotMatch(
        readFileSync('resources/views/groups/chat.blade.php', 'utf8'),
        /querySelector\(["']\.btn-(?:delete|report)["']\)\?\.addEventListener/
    );
    assert.doesNotMatch(runtime, /initializeMessageActions/);
    assert.doesNotMatch(blade, /initializeMessageActions/);
    assert.doesNotMatch(legacyMessageRuntime, /initializeMessageActions/);
    assert.doesNotMatch(blade, /btn-(?:delete|report):not\(\[data-group-chat-action\]\)/);
});

test('unread polling and observers are owned by the page lifecycle', () => {
    const index = readFileSync('resources/js/group-chat/index.js', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/scroll_unread_runtime.blade.php', 'utf8');

    assert.match(index, /window\.GroupChatLifecycle\s*=\s*pageLifecycle/);
    assert.match(runtime, /lifecycle\.interval\(refreshUnreadCount, 15000\)/);
    assert.match(runtime, /lifecycle\.add\(\(\) => observer\.disconnect\(\)\)/);
    assert.doesNotMatch(runtime, /setInterval\(refreshUnreadCount/);
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

test('scroll and unread runtime is loaded through its dedicated partial', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/scroll_unread_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.scroll_unread_runtime'\)/);
    assert.doesNotMatch(blade, /function initializeGroupChatScrollManager\(/);
    assert.match(runtime, /function initializeGroupChatScrollManager\(/);
    assert.match(runtime, /function restoreInitialPosition\(/);
    assert.match(runtime, /function renderUnreadIndicators\(/);
});

test('composer actions runtime is loaded through its dedicated partial', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/composer_actions_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.composer_actions_runtime'\)/);
    assert.doesNotMatch(blade, /const plusButton = document\.getElementById\('chatCreateToggle'\)/);
    assert.match(runtime, /const plusButton = document\.getElementById\('chatCreateToggle'\)/);
    assert.match(runtime, /const audioUploadTrigger = document\.getElementById\('audio-upload-trigger'\)/);
    assert.match(runtime, /const createPostBtn = document\.getElementById\('create-post-btn'\)/);
    assert.match(runtime, /const createPollBtn = document\.getElementById\('create-poll-btn'\)/);
    assert.match(runtime, /window\.__groupChatComposerActionsInitialized/);
    assert.match(runtime, /lifecycle\.on\(textarea, 'input'/);
    assert.match(runtime, /lifecycle\.on\(document, 'click'/);
    assert.match(runtime, /lifecycle\.on\(document, 'keydown'/);
    assert.equal((runtime.match(/\.addEventListener\(/g) || []).length, 1);
    assert.match(runtime, /document\.addEventListener\('DOMContentLoaded', initializeComposerActionsRuntime, \{ once: true \}\)/);
    assert.equal((runtime.match(/lifecycle\.timeout\(/g) || []).length, 2);
    assert.doesNotMatch(runtime, /(^|[^.])setTimeout\(/m);
    assert.match(runtime, /lifecycle\.add\(function\(\)/);
});

test('post submission runtime is extracted without patching openBlogBox', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/post_submission_runtime.blade.php', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.post_submission_runtime'\)/);
    assert.doesNotMatch(blade, /function interceptPostForm\(/);
    assert.match(runtime, /function initializePostSubmissionRuntime\(/);
    assert.match(runtime, /window\.__groupChatPostSubmissionInitialized/);
    assert.match(runtime, /lifecycle\.on\(postForm, 'submit'/);
    assert.equal((runtime.match(/\.addEventListener\(/g) || []).length, 1);
    assert.match(runtime, /document\.addEventListener\('DOMContentLoaded', initializePostSubmissionRuntime, \{ once: true \}\)/);
    assert.doesNotMatch(runtime, /Object\.defineProperty\(window, 'openBlogBox'/);
    assert.doesNotMatch(runtime, /setTimeout\(/);
    assert.doesNotMatch(runtime, /_ajaxIntercepted/);
    assert.match(runtime, /GroupChatFeedBridge/);
    assert.match(runtime, /feedBridge\.create\('post', data\.post, 'local-post-submit'\)/);
    assert.doesNotMatch(runtime, /appendChild\(/);
    assert.doesNotMatch(runtime, /_init(?:PostMenus|ReactionButtons)/);
    assert.doesNotMatch(runtime, /_lastKnownPostId/);
    assert.match(groupChat, /window\.GroupChatFeedBridge\s*=\s*Object\.freeze/);
    assert.match(groupChat, /applyFeedItemThroughPipeline\(contentType, 'create', payload, source\)/);
    assert.match(groupChat, /updateLastPostCursor\(payload\?\.post_id \|\| payload\?\.content_id \|\| payload\?\.id\)/);
    assert.match(groupChat, /mutate\(contentType, operation, payload, source = 'local'\)/);
    assert.match(groupChat, /GroupChatFeedBridge\.create\('post', p, 'polling-fallback'\)/);
    assert.match(groupChat, /GroupChatFeedBridge\.mutate\('post', 'delete', \{ id: pid \}, 'polling-fallback'\)/);
    assert.match(groupChat, /GroupChatFeedBridge\.mutate\('post', 'update', p, 'polling-fallback'\)/);
    assert.match(groupChat, /GroupChatFeedBridge\.mutate\('post', 'delete', \{ id: pid \}, 'reconcile-fallback'\)/);
    assert.match(groupChat, /GroupChatFeedBridge\.mutate\('post', 'delete', \{ id: postId \}, 'local-post-delete'\)/);
    assert.match(groupChat, /GroupChatFeedBridge\.mutate\('post', 'update', updatedPost, 'local-post-edit'\)/);
    assert.match(groupChat, /updatePostFieldsDom\(\{ \.\.\.item, id \}\)/);
    assert.doesNotMatch(groupChat, /function updateBlogUI\(/);
    assert.doesNotMatch(groupChat, /wrapperEl\.replaceWith\(/);
    assert.doesNotMatch(groupChat, /_lastKnownPostId/);
});

test('post menus and reactions use lifecycle-owned event delegation', () => {
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');

    assert.match(groupChat, /window\.__groupChatPostInteractionsDelegated/);
    assert.match(groupChat, /actionMenuLifecycle\.on\(document, 'click'/);
    assert.match(groupChat, /actionMenuLifecycle\.on\(document, 'keydown'/);
    assert.match(groupChat, /actionMenuLifecycle\.on\(window, 'resize'/);
    assert.match(groupChat, /actionMenuLifecycle\.on\(document, 'scroll'/);
    assert.match(groupChat, /\.reaction-buttons \.btn-like, \.reaction-buttons \.btn-dislike/);
    assert.match(groupChat, /toggle\?\.closest\('\[data-action-menu\]'\)/);
    assert.match(groupChat, /actionItem\?\.classList\.contains\('btn-reaction'\)/);
    assert.match(groupChat, /actionMenuLifecycle\.add\(function\(\)/);
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
    assert.match(runtime, /window\.__groupChatMessageEditInitialized/);
    assert.match(runtime, /lifecycle\.on\(document, 'click'/);
    assert.match(runtime, /lifecycle\.on\(btnSave, 'click'/);
    assert.match(runtime, /lifecycle\.on\(document, 'keydown'/);
    assert.match(runtime, /lifecycle\.add\(function\(\)/);
    assert.equal((runtime.match(/\.addEventListener\(/g) || []).length, 1);
    assert.match(runtime, /document\.addEventListener\('DOMContentLoaded', initializeMessageEditRuntime, \{ once: true \}\)/);
    assert.doesNotMatch(runtime, /btnSave\.addEventListener/);
});

test('ckeditor runtime is extracted and lifecycle-owned', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/ckeditor_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.ckeditor_runtime'\)/);
    assert.doesNotMatch(blade, /function installCkeditorChatConfig\(/);
    assert.match(runtime, /function initializeGroupChatCkeditorRuntime\(\)/);
    assert.match(runtime, /window\.__groupChatCkeditorInitialized/);
    assert.match(runtime, /lifecycle\.interval\(function\(\)/);
    assert.match(runtime, /lifecycle\.clearInterval\(ckeditorWait\)/);
    assert.match(runtime, /ckeditor\.instances\?\.post_editor/);
    assert.match(runtime, /instance\.destroy\(true\)/);
    assert.match(runtime, /lifecycle\.add\(function\(\)/);
    assert.doesNotMatch(runtime, /(^|[^.])setInterval\(/m);
    assert.equal((runtime.match(/\.addEventListener\(/g) || []).length, 1);
});

test('legacy message runtime is loaded through its dedicated partial', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/legacy_message_runtime.blade.php', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.legacy_message_runtime'\)/);
    assert.doesNotMatch(blade, /function addMessageToChat\(/);
    assert.doesNotMatch(blade, /function updateMessageContent\(/);
    assert.match(runtime, /const GROUP_ID = \{\{ \$group->id \}\}/);
    assert.match(runtime, /function setLastReadState\(messageId, notify = true\)/);
    assert.match(runtime, /function updateLastReadMessage\(messageId\)/);
    assert.match(runtime, /function addMessageToChat\(messageData\)/);
    assert.match(runtime, /function updateMessageContent\(messageBubble, newContent, isEdited\)/);
    assert.match(runtime, /function escapeHtml\(text\)/);
    assert.match(runtime, /function initializeLegacyMessageLifecycle\(\)/);
    assert.match(runtime, /window\.__groupChatLegacyMessageLifecycleInitialized/);
    assert.match(runtime, /lifecycle\.on\(document, 'click'/);
    assert.match(runtime, /lifecycle\.clearTimeout\(lastReadUpdateTimeout\)/);
    assert.match(runtime, /lifecycle\.timeout\.bind\(lifecycle\)/);
    assert.doesNotMatch(runtime, /profileLink\.addEventListener/);
    assert.match(runtime, /function initializeLegacyCategoryBlogs\(\)/);
    assert.match(runtime, /window\.__groupChatCategoryBlogsInitialized/);
    assert.match(runtime, /lifecycle\.on\(document, 'keydown'/);
    assert.match(runtime, /const openCategory = e\.target\.closest\?\.\('\.open-category-blogs'\)/);
    assert.match(runtime, /activeRequest\.abort\(\)/);
    assert.doesNotMatch(runtime, /\$\(document\)\.on/);
    assert.doesNotMatch(runtime, /openCategory\.addEventListener/);
});

test('page chrome runtime owns group edit and one-shot page effects', () => {
    const blade = readFileSync('resources/views/groups/chat.blade.php', 'utf8');
    const runtime = readFileSync('resources/views/groups/partials/page_chrome_runtime.blade.php', 'utf8');
    const groupEdit = readFileSync('resources/views/groups/modals/group_edit_form.blade.php', 'utf8');
    const groupChat = readFileSync('public/js/group-chat.js', 'utf8');

    assert.match(blade, /@include\('groups\.partials\.page_chrome_runtime'\)/);
    assert.doesNotMatch(blade, /function openGroupEdit\(/);
    assert.doesNotMatch(blade, /function cancelGroupEdit\(/);
    assert.match(runtime, /window\.GroupChatPageChrome = Object\.freeze/);
    assert.match(runtime, /lifecycle\.on\(window, 'load'/);
    assert.match(runtime, /delete window\.GroupChatPageChrome/);
    assert.match(groupEdit, /data-group-chat-action="cancel-group-edit"/);
    assert.doesNotMatch(groupEdit, /onclick=/);
    assert.match(groupChat, /GroupChatPageChrome\.openGroupEdit\(\)/);
    assert.match(groupChat, /GroupChatPageChrome\.cancelGroupEdit\(\)/);
    assert.match(runtime, /showEditPollBox\(pollId\)/);
    assert.match(runtime, /querySelectorAll\('\[id\^="edit-poll-box-"\]'\)/);
    assert.match(groupChat, /GroupChatPageChrome\.showEditPollBox\(Number\(target\.dataset\.pollId\)\)/);
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
    assert.match(readFileSync('public/js/group-chat.js', 'utf8'), /GroupChatPageChrome\.toggleGroupHero\(\)/);
});
