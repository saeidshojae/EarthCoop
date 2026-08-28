import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const polish = readFileSync('resources/views/groups/partials/group_control_center_polish.blade.php', 'utf8');
const editModal = readFileSync('resources/views/groups/modals/group_edit_form.blade.php', 'utf8');
const pageChrome = readFileSync('resources/views/groups/partials/page_chrome_runtime.blade.php', 'utf8');
const hero = readFileSync('resources/views/groups/partials/group_hero.blade.php', 'utf8');
const controller = readFileSync('app/Http/Controllers/Group/GroupController.php', 'utf8');
const groupModel = readFileSync('app/Models/Group.php', 'utf8');

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

test('group edit is portaled to document body so ancestor stacking contexts cannot trap it', () => {
    assert.match(pageChrome, /const groupEditOriginalParent = groupEditForm\?\.parentNode/);
    assert.match(pageChrome, /document\.body\.appendChild\(groupEditForm\)/);
    assert.match(pageChrome, /groupEditOriginalParent\.insertBefore\(groupEditForm, groupEditOriginalNextSibling\)/);
});

test('floating Najm Hoda launcher cannot cover an open control center', () => {
    assert.match(polish, /body:has\(#groupInfoPanel\.is-open\) \.najm-hoda-widget \{[\s\S]*?visibility: hidden !important[\s\S]*?pointer-events: none !important/);
});

test('group exit action is moved before the my-groups search and list', () => {
    assert.match(polish, /footer\.classList\.add\('control-center-exit-row'\)/);
    assert.match(polish, /myGroupsSection\.insertBefore\(footer, searchBlock \|\| groupsList\)/);
});

test('operational stats use the canonical blog media column and independent query builders', () => {
    assert.match(controller, /whereNotNull\('img'\)/);
    assert.doesNotMatch(controller, /whereNotNull\('image'\)/);
    assert.match(controller, /\(clone \$messagesQuery\)->whereDate/);
    assert.match(controller, /\(clone \$postsQuery\)->whereMonth/);
    assert.match(controller, /\(clone \$pollsQuery\)->where\('end_time'/);
    assert.match(controller, /\(clone \$electionsQuery\)->where\('end_time'/);
    assert.match(controller, /\(clone \$reportsQuery\)->where\('status'/);
});

test('stats failures shown to users are Persian-safe instead of leaking SQL details', () => {
    assert.match(polish, /const statsErrorText = panel\.querySelector\('#stats-error-text'\)/);
    assert.match(polish, /SQLSTATE\|Unknown column\|select\\s\|connection\|database/i);
    assert.match(polish, /'بارگذاری آمار گروه با خطا مواجه شد\. لطفاً دوباره تلاش کنید\.'/);
});

test('desktop group hero owns balanced action spacing explicitly', () => {
    assert.match(hero, /group-hero__desktop/);
    assert.match(hero, /group-hero__desktop-actions/);
    assert.match(polish, /\.group-hero__desktop \{[\s\S]*?padding-inline: 2rem !important/);
    assert.match(polish, /\.group-hero__desktop-actions \{[\s\S]*?margin-inline-start: 1rem/);
});

test('group description remains visible after the compact hero redesign', () => {
    assert.match(hero, /group-hero__description--mobile/);
    assert.match(hero, /group-hero__description--desktop/);
    assert.match(hero, /\$group->description/);
});

test('group avatar rendering uses one normalized URL contract on canonical chat surfaces', () => {
    assert.match(groupModel, /function getAvatarUrlAttribute\(\): \?string/);
    assert.match(groupModel, /images\/groups/);
    assert.match(hero, /\$group->avatar_url/);
    assert.match(editModal, /\$group->avatar_url/);
});
