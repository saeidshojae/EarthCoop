import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

test('election surface bridge keeps systemic and internal elections as separate first-class sources', () => {
    const bridge = read('resources/views/groups/partials/election_surface_bridge.blade.php');
    const routeProvider = read('app/Providers/RouteServiceProvider.php');
    const routes = read('routes/group-election-surface.php');
    const controller = read('app/Http/Controllers/Group/GroupElectionSurfaceController.php');

    assert.match(routeProvider, /routes\/group-election-surface\.php/);
    assert.match(routes, /GroupElectionSurfaceController::class,\s*'stats'/);
    assert.match(controller, /\$systemicElectionsQuery\s*=\s*\$group->elections\(\)/);
    assert.match(controller, /\$internalElectionsQuery\s*=\s*\$group->polls\(\)->where\('main_type',\s*0\)/);
    assert.match(controller, /'systemic'\s*=>\s*\$systemic/);
    assert.match(controller, /'internal'\s*=>\s*\$internal/);
    assert.match(controller, /'total'\s*=>\s*\$systemic\['total'\]\s*\+\s*\$internal\['total'\]/);
    assert.match(bridge, /data-election-kind-tab="systemic"/);
    assert.match(bridge, /data-election-kind-tab="internal"/);
    assert.match(bridge, /data-election-kind-pane="systemic"/);
    assert.match(bridge, /data-election-kind-pane="internal"/);
});

test('governance election bridge keeps internal election cards and gives systemic election a live participation entry point', () => {
    const bridge = read('resources/views/groups/partials/election_surface_bridge.blade.php');
    const chat = read('resources/views/groups/chat.blade.php');

    assert.match(chat, /@include\('groups\.partials\.election_surface_bridge'\)/);
    assert.match(bridge, /data-chat-page-action="open-election"/);
    assert.match(bridge, /data-internal-election-host/);
    assert.match(bridge, /legacyElectionSection/);
    assert.match(bridge, /data-election-surface-stats-url/);
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

test('systemic election participation gate uses composer-provided active membership and batched election block state', () => {
    const chat = read('resources/views/groups/chat.blade.php');
    const provider = read('app/Providers/AppServiceProvider.php');

    assert.match(provider, /->whereIn\('position',\s*\['election',\s*'message',\s*'post',\s*'poll'\]\)/);
    assert.match(provider, /'checkBlockElection'\s*=>\s*\$blocks->get\('election'\)/);
    assert.match(chat, /\$pivotUser\s*=\s*\$pivotUser\s*\?\?\s*null/);
    assert.match(chat, /\$checkBlockElection\s*=\s*\$checkBlockElection\s*\?\?\s*null/);
    assert.match(chat, /\$canParticipateElection\s*=\s*\$electionAvailable\s*&&\s*!\$checkBlockElection\s*&&\s*\(int\)\(\$pivotUser\?->status/);
});
