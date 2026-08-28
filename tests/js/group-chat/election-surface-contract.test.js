import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

test('group election statistics combine systemic cycles and internal group elections without conflating them', () => {
    const controller = read('app/Http/Controllers/Group/GroupController.php');
    const panel = read('resources/views/groups/partials/group_info_panel.blade.php');

    assert.match(controller, /\$systemicElectionsQuery\s*=\s*\$group->elections\(\)/);
    assert.match(controller, /\$internalElectionsQuery\s*=\s*\$group->polls\(\)->where\('main_type',\s*0\)/);
    assert.match(controller, /'systemic'\s*=>\s*\$systemicElectionsStats/);
    assert.match(controller, /'internal'\s*=>\s*\$internalElectionsStats/);
    assert.match(controller, /'total'\s*=>\s*\$systemicElectionsStats\['total'\]\s*\+\s*\$internalElectionsStats\['total'\]/);

    assert.match(panel, /\$systemicElectionCount\s*=\s*\$group2->elections\(\)->count\(\)/);
    assert.match(panel, /\$internalElectionCount\s*=\s*\$electionPolls->count\(\)/);
    assert.match(panel, /\{\{\s*\$systemicElectionCount\s*\+\s*\$internalElectionCount\s*\}\}/);
});

test('governance election area exposes distinct systemic and internal election panes', () => {
    const panel = read('resources/views/groups/partials/group_info_panel.blade.php');
    const polish = read('resources/views/groups/partials/group_control_center_polish.blade.php');

    assert.match(panel, /data-election-kind-tabs/);
    assert.match(panel, /data-election-kind-tab="systemic"/);
    assert.match(panel, /data-election-kind-tab="internal"/);
    assert.match(panel, /data-election-kind-pane="systemic"/);
    assert.match(panel, /data-election-kind-pane="internal"/);
    assert.match(panel, /data-chat-page-action="open-election"/);
    assert.match(panel, /@include\('groups\.partials\.poll',\s*\['item'\s*=>\s*\$item/);
    assert.match(polish, /data-election-kind-tab/);
});

test('group hero prioritizes systemic election participation and financial reporting over content creation', () => {
    const hero = read('resources/views/groups/partials/group_hero.blade.php');

    assert.doesNotMatch(hero, /data-chat-page-action="open-blog"/);
    assert.doesNotMatch(hero, /data-chat-page-action="open-poll"/);
    assert.match(hero, /route\('groups\.najm-bahar\.reports',\s*\$group\)/);
    assert.match(hero, /data-chat-page-action="open-election"/);
    assert.match(hero, /شرکت در انتخابات/);
    assert.match(hero, /گزارش مالی گروه/);
});

test('systemic election participation gate is derived from active membership and election block state', () => {
    const chat = read('resources/views/groups/chat.blade.php');
    const controller = read('app/Http/Controllers/Group/ChatController.php');

    assert.match(controller, /Block::where\('user_id',\s*auth\(\)->id\(\)\)->where\('position',\s*'election'\)/);
    assert.match(chat, /\$canParticipateElection\s*=\s*\$electionAvailable\s*&&\s*!\$checkBlockElection\s*&&\s*\(int\)\(\$pivotUser\?->status/);
});
