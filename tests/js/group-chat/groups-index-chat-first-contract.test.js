import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const basic = readFileSync('resources/views/groups/partials/table-basic.blade.php', 'utf8');
const managed = readFileSync('resources/views/groups/partials/table-managed.blade.php', 'utf8');

test('active group names open chat directly from groups index surfaces', () => {
    assert.match(basic, /href="\{\{ route\('groups\.chat', \$group\) \}\}"/);
    assert.match(managed, /href="\{\{ route\('groups\.chat', \$group\) \}\}"[\s\S]*?\{\{ \$group->name \}\}/);
});

test('managed group table keeps the explicit chat operation as a secondary affordance', () => {
    assert.match(managed, /route\('groups\.chat', \$group\)/);
    assert.match(managed, /ورود به گروه/);
});
