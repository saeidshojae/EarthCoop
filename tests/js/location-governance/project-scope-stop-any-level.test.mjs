import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

import { projectScopeSelectionValues } from '../../../resources/js/location-selector.js';

test('project market scope may stop on global or continent governance without requiring a lower location', () => {
    assert.deepEqual(
        projectScopeSelectionValues({ id: 1, identity: 'governance:1', type_key: 'global', status: 'active' }),
        { locationId: '', governanceAreaId: '1' },
    );
    assert.deepEqual(
        projectScopeSelectionValues({ id: 2, identity: 'governance:2', type_key: 'continent', status: 'active' }),
        { locationId: '', governanceAreaId: '2' },
    );
});

test('project market scope may stop on any active intermediate Location without requiring a leaf', () => {
    for (const [id, typeKey] of [[10, 'country'], [11, 'province'], [12, 'county'], [13, 'city'], [14, 'neighborhood'], [15, 'street']]) {
        assert.deepEqual(
            projectScopeSelectionValues({ id, identity: `location:${id}`, type_key: typeKey, status: 'active', has_children: true }),
            { locationId: String(id), governanceAreaId: '' },
            `expected ${typeKey} to be a valid stopping point`,
        );
    }
});

test('project scope traversal keeps the selected parent valid while offering more precise children', () => {
    const source = readFileSync(new URL('../../../resources/js/location-selector.js', import.meta.url), 'utf8');

    assert.match(source, /گزینه‌های دقیق‌تر آماده‌اند؛ می‌توانید همین سطح را نگه دارید یا پایین‌تر بروید/);
    assert.match(source, /if \(submit && !isProjectScope\) submit\.disabled = true/);
    assert.doesNotMatch(source, /isProjectScope[^\n]*submit\.disabled\s*=\s*true/);
});
