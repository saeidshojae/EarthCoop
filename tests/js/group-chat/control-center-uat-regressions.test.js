import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const panel = readFileSync('resources/views/groups/partials/group_info_panel.blade.php', 'utf8');
const polish = readFileSync('resources/views/groups/partials/group_control_center_polish.blade.php', 'utf8');
const editModal = readFileSync('resources/views/groups/modals/group_edit_form.blade.php', 'utf8');
const pageChrome = readFileSync('resources/views/groups/partials/page_chrome_runtime.blade.php', 'utf8');
const hero = readFileSync('resources/views/groups/partials/group_hero.blade.php', 'utf8');
const controller = readFileSync('app/Http/Controllers/Group/GroupController.php', 'utf8');

test('primary control-center tabs use full centered card geometry', () => {
    assert.match(polish, /#groupInfoPanel \.panel-tabs \.tab \{[\s\S]*?width: 100%[\s\S]*?min-height: 44px[\s\S]*?text-align: center/);
    assert.match(polish, /#groupInfoPanel \.panel-tabs \.tab\.active \{[\s\S]*?border:/);
});

test('content primary action keeps a clear green treatment', () => {
    assert.match(polish, /#groupInfoPanel \.panel-action-btn--primary \{[\s\S]*?background: #10b981 !important[\s\S]*?color: #fff !important/);
});

test('group edit opens as a true overlay above the control center', () => {
    assert.match(editModal, /class="group-edit-modal"/);
    assert.match(editModal, /class="group-edit-modal__backdrop"/);
    assert.match(editModal, /class="group-edit-modal__dialog"/);
    assert.match(polish, /\.group-edit-modal \{[\s\S]*?position: fixed[\s\S]*?z-index: 1400/);
    assert.match(pageChrome, /groupEditForm\.style\.display = visible \? 'flex' : 'none'/);
});

test('floating Najm Hoda launcher cannot cover an open control center', () => {
    assert.match(polish, /body:has\(#groupInfoPanel\.is-open\) \.najm-hoda-widget \{[\s\S]*?visibility: hidden !important[\s\S]*?pointer-events: none !important/);
});

test('group exit action is placed before the my-groups list rather than after all tabs', () => {
    const exit = panel.indexOf('control-center-exit-row');
    const list = panel.indexOf('id="groupsList"');
    assert.ok(exit > -1 && list > -1 && exit < list);
    assert.doesNotMatch(panel, /<footer class="control-center-footer">/);
});

test('operational stats use the canonical blog media column and independent query builders', () => {
    assert.match(controller, /whereNotNull\('img'\)/);
    assert.doesNotMatch(controller, /whereNotNull\('image'\)/);
    assert.match(controller, /\(clone \$messagesQuery\)->whereDate/);
    assert.match(controller, /\(clone \$postsQuery\)->whereMonth/);
    assert.match(controller, /\(clone \$pollsQuery\)->where\('end_time'/);
    assert.match(controller, /\(clone \$electionsQuery\)->where\('end_time'/);
});

test('stats failures shown to users are Persian-safe instead of leaking SQL details', () => {
    assert.match(panel, /throw new Error\('بارگذاری آمار گروه با خطا مواجه شد\.'\)/);
    assert.match(panel, /errorText\.textContent='بارگذاری آمار گروه با خطا مواجه شد\. لطفاً دوباره تلاش کنید\.'/);
    assert.doesNotMatch(panel, /errorText\.textContent=exception\.message/);
});

test('desktop group hero owns balanced action spacing explicitly', () => {
    assert.match(hero, /group-hero__desktop/);
    assert.match(hero, /group-hero__desktop-actions/);
    assert.match(polish, /\.group-hero__desktop \{[\s\S]*?padding-inline: 2rem !important/);
    assert.match(polish, /\.group-hero__desktop-actions \{[\s\S]*?margin-inline-start: 1rem/);
});
