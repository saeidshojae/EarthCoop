import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

test('group chat shell reuses the unified site header without legacy duplicate navigation chrome', () => {
    const layout = read('resources/views/layouts/chat.blade.php');

    assert.match(layout, /components\.header-unified/);
    assert.match(layout, /headerContext['"]?\s*=>\s*['"]chat['"]/);
    assert.doesNotMatch(layout, /class=["'][^"']*chat-mini-header/);
    assert.doesNotMatch(layout, /class=["'][^"']*chat-menu-sidebar/);
    assert.doesNotMatch(layout, /id=["']header-observer["']/);
    assert.doesNotMatch(layout, /IntersectionObserver/);
});

test('group chat shell starts with unified site header visually hidden and no reserved spacer', () => {
    const layout = read('resources/views/layouts/chat.blade.php');

    assert.match(layout, /header\.site-header-unified\[data-header-context=["']chat["']\]/);
    assert.match(layout, /transform:\s*translateY\(-100%\)/);
    assert.match(layout, /\.site-header-spacer[^}]*height:\s*0\s*!important/s);
    assert.match(layout, /--chat-site-header-offset/);
});

test('group chat roots clip horizontal overflow without becoming a scroll container', () => {
    const layout = read('resources/views/layouts/chat.blade.php');
    const rootRule = layout.match(/html,\s*\n\s*body\s*\{([\s\S]*?)\n\s*\}/)?.[1] ?? '';

    assert.ok(rootRule, 'expected html/body root rule');
    assert.match(rootRule, /overflow-x\s*:\s*clip\s*!important/);
    assert.match(rootRule, /overflow-y\s*:\s*visible\s*!important/);
    assert.doesNotMatch(rootRule, /overflow-x\s*:\s*hidden\s*!important/);
});

test('group chat body does not become a competing vertical scroll container', () => {
    const layout = read('resources/views/layouts/chat.blade.php');
    const chatBodyRule = layout.match(/body\.chat-layout\s*\{([\s\S]*?)\n\s*\}/)?.[1] ?? '';

    assert.ok(chatBodyRule, 'expected body.chat-layout rule');
    assert.doesNotMatch(chatBodyRule, /overflow-y\s*:\s*auto\s*!important/);
});

test('group hero remains sticky beneath the optional unified header', () => {
    const controller = read('resources/js/group-chat-header-controller.js');

    assert.match(controller, /gestureSurface\.style\.setProperty\(['"]position['"],\s*['"]sticky['"]/);
    assert.match(controller, /gestureSurface\.style\.setProperty\(['"]top['"],\s*['"]var\(--chat-site-header-offset, 0px\)['"]/);
});

test('group chat header gestures are scoped to the group hero rather than page scrolling', () => {
    const pageEntry = read('resources/js/group-chat-page.js');
    const controller = read('resources/js/group-chat-header-controller.js');

    assert.match(pageEntry, /group-chat-header-controller\.js/);
    assert.match(pageEntry, /createGroupChatHeaderController/);
    assert.match(controller, /CHAT_HEADER_GESTURE_THRESHOLD\s*=\s*10/);
    assert.match(controller, /querySelector\(['"]\[data-group-hero\]['"]\)/);
    assert.match(controller, /gestureSurface\.addEventListener\('wheel'/);
    assert.match(controller, /gestureSurface\.addEventListener\('touchstart'/);
    assert.match(controller, /gestureSurface\.addEventListener\('touchmove'/);
    assert.doesNotMatch(controller, /runtimeWindow\.addEventListener\('scroll'/);
    assert.doesNotMatch(controller, /runtimeWindow\.addEventListener\('wheel'/);
    assert.doesNotMatch(controller, /runtimeWindow\.addEventListener\('touchstart'/);
    assert.doesNotMatch(controller, /runtimeWindow\.addEventListener\('touchmove'/);
    assert.match(controller, /chat-site-header-visible/);
});

test('chat header auto-hide is blocked only by an actually expanded header control', () => {
    const controller = read('resources/js/group-chat-header-controller.js');

    assert.match(controller, /querySelector\(['"]\[aria-expanded=[\\"']true[\\"']\]['"]\)/);
    assert.doesNotMatch(controller, /querySelectorAll\(['"]\[x-show\]['"]\)/);
});
