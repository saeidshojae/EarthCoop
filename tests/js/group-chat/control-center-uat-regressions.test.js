import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const polish = readFileSync('resources/views/groups/partials/group_control_center_polish.blade.php', 'utf8');
const editModal = readFileSync('resources/views/groups/modals/group_edit_form.blade.php', 'utf8');
const pageChrome = readFileSync('resources/views/groups/partials/page_chrome_runtime.blade.php', 'utf8');
const hero = readFileSync('resources/views/groups/partials/group_hero.blade.php', 'utf8');
const controller = readFileSync('app/Http/Controllers/Group/GroupController.php', 'utf8');
const groupModel = readFileSync('app/Models/Group.php', 'utf8');
const chatFeatures = readFileSync('public/js/chat-features.js', 'utf8');

test('primary control-center tabs use full centered card geometry', () => {
    assert.match(polish, /#groupInfoPanel \.panel-tabs \.tab \{[\s\S]*?width: 100%[\s\S]*?min-height: 44px[\s\S]*?text-align: center/);
    assert.match(polish, /#groupInfoPanel \.panel-tabs \.tab\.active \{[\s\S]*?border:/);
});

test('content primary action keeps a clear green treatment', () => {
    assert.match(polish, /#groupInfoPanel \.panel-action-btn--primary \{[\s\S]*?background: #10b981 !important[\s\S]*?color: #fff !important/);
});

test('group edit uses a fresh modal-shell id so legacy #groupEditFormBox geometry cannot match it', () => {
    assert.match(editModal, /id="groupEditModalShell"\s+class="modal-shell group-edit-modal"/);
    assert.doesNotMatch(editModal, /groupEditFormBox/);
    assert.match(pageChrome, /document\.getElementById\('groupEditModalShell'\)/);
    assert.doesNotMatch(pageChrome, /getElementById\('groupEditFormBox'\)/);
});

test('group edit reuses the proven group-settings modal shell geometry exactly', () => {
    assert.match(editModal, /class="modal-shell__dialog group-edit-modal__dialog"[\s\S]*?style="position: relative; width: min\(500px, 94vw\); background: #fff; border-radius: 28px; padding: 1\.75rem; box-shadow: 0 45px 95px -45px rgba\(15, 23, 42, 0\.6\);"/);
    assert.match(chatFeatures, /modal\.style\.cssText = 'display: flex; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 1\.5rem; direction: rtl;'/);
    assert.match(pageChrome, /groupEditForm\.style\.cssText = visible[\s\S]*?'display: flex; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 1\.5rem; direction: rtl;'[\s\S]*?: 'display: none;'/);
});

test('group edit is portaled to body and closes when the shell backdrop itself is clicked', () => {
    assert.match(pageChrome, /document\.body\.appendChild\(groupEditForm\)/);
    assert.match(pageChrome, /lifecycle\.on\(groupEditForm, 'click', event => \{[\s\S]*?if \(event\.target === groupEditForm\)[\s\S]*?setGroupEditVisible\(false\)/);
});

test('floating Najm Hoda launcher cannot cover an open control center', () => {
    assert.match(polish, /body:has\(#groupInfoPanel\.is-open\) \.najm-hoda-widget \{[\s\S]*?visibility: hidden !important[\s\S]*?pointer-events: none !important/);
});

test('group exit action is moved before the my-groups search and list', () => {
    assert.match(polish, /footer\.classList\.add\('control-center-exit-row'\)/);
    assert.match(polish, /myGroupsSection\.insertBefore\(footer, searchBlock \|\| groupsList\)/);
});

test('operational stats use canonical persisted columns and independent query builders', () => {
    assert.match(controller, /whereNotNull\('img'\)/);
    assert.doesNotMatch(controller, /whereNotNull\('image'\)/);
    assert.match(controller, /\(clone \$messagesQuery\)->whereDate/);
    assert.match(controller, /\(clone \$postsQuery\)->whereMonth/);
    assert.match(controller, /\$pollsQuery/);
    assert.match(controller, /\$electionsQuery/);
    assert.match(controller, /whereNull\('expires_at'\)[\s\S]*?orWhere\('expires_at', '>', now\(\)\)/);
    assert.match(controller, /whereNotNull\('expires_at'\)[\s\S]*?where\('expires_at', '<=', now\(\)\)/);
    assert.doesNotMatch(controller, /where\('end_time'/);
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