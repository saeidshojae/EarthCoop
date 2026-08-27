import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const table = readFileSync('resources/views/groups/partials/table-basic.blade.php', 'utf8');

test('accessible groups in My Groups navigate directly to group chat', () => {
    assert.match(table, /href="\{\{ route\('groups\.chat', \$group\) \}\}"/);
    assert.doesNotMatch(table, /href="\{\{ route\('groups\.show', \$group\) \}\}"/);
});
