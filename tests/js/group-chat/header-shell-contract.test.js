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
