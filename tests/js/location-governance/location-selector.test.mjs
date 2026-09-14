import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

import {
    normalizePickerPayload,
    selectionValues,
    shouldRenderNextLevel,
} from '../../../resources/js/location-selector.js';

test('normalizes active locations, open proposals, and allowed types without fixed depth', () => {
    const streetPayload = normalizePickerPayload({
        data: [],
        proposals: [],
        allowed_types: [
            { id: 12, key: 'alley', label: 'Alley', proposal_allowed: true },
            { id: 13, key: 'complex', label: 'Residential complex', proposal_allowed: true },
        ],
    });

    assert.equal(shouldRenderNextLevel(streetPayload), true);
    assert.deepEqual(streetPayload.allowedTypes.map((type) => type.key), ['alley', 'complex']);

    const alleyPayload = normalizePickerPayload({
        data: [],
        proposals: [],
        allowed_types: [
            { id: 13, key: 'complex', label: 'Residential complex', proposal_allowed: true },
        ],
    });

    assert.equal(shouldRenderNextLevel(alleyPayload), true);
    assert.deepEqual(alleyPayload.allowedTypes.map((type) => type.key), ['complex']);
});

test('maps canonical and proposal identities to mutually exclusive hidden values', () => {
    assert.deepEqual(
        selectionValues({ id: 41, identity: 'location:41', is_residence_endpoint: true, status: 'active' }),
        { locationId: '41', proposalId: '' },
    );

    assert.deepEqual(
        selectionValues({ id: 17, identity: 'proposal:17', status: 'pending', selectable: true }),
        { locationId: '', proposalId: '17' },
    );
});

test('open proposals count as a renderable next level even when no approved child exists', () => {
    const payload = normalizePickerPayload({
        data: [],
        proposals: [
            { id: 7, identity: 'proposal:7', type_key: 'complex', label: 'Pending complex', status: 'pending', selectable: true },
        ],
        allowed_types: [],
    });

    assert.equal(shouldRenderNextLevel(payload), true);
    assert.equal(payload.proposals[0].identity, 'proposal:7');
});

test('terminal proposals and inactive locations are removed from stale picker payloads', () => {
    const payload = normalizePickerPayload({
        data: [
            { id: 1, identity: 'location:1', label: 'Active', status: 'active' },
            { id: 2, identity: 'location:2', label: 'Inactive', status: 'inactive' },
        ],
        proposals: [
            { id: 11, identity: 'proposal:11', label: 'Pending', status: 'pending', selectable: true },
            { id: 12, identity: 'proposal:12', label: 'Approved', status: 'approved', selectable: false },
            { id: 13, identity: 'proposal:13', label: 'Rejected', status: 'rejected', selectable: false },
            { id: 14, identity: 'proposal:14', label: 'Merged', status: 'merged', selectable: false },
        ],
        allowed_types: [],
    });

    assert.deepEqual(payload.locations.map((item) => item.id), [1]);
    assert.deepEqual(payload.proposals.map((item) => item.id), [11]);

    const terminalOnly = normalizePickerPayload({
        data: [],
        proposals: [
            { id: 99, identity: 'proposal:99', label: 'Resolved', status: 'approved', selectable: false },
        ],
        allowed_types: [],
    });

    assert.equal(shouldRenderNextLevel(terminalOnly), false);
});

test('selection values reject stale terminal proposal and inactive canonical identities', () => {
    assert.deepEqual(
        selectionValues({ id: 12, identity: 'proposal:12', status: 'approved', selectable: false }),
        { locationId: '', proposalId: '' },
    );

    assert.deepEqual(
        selectionValues({ id: 2, identity: 'location:2', status: 'inactive', is_residence_endpoint: true }),
        { locationId: '', proposalId: '' },
    );
});

test('dynamic selector exposes visible labels and explicit loading error and stale states', () => {
    const source = readFileSync(new URL('../../../resources/js/location-selector.js', import.meta.url), 'utf8');

    assert.match(source, /document\.createElement\(['"]label['"]\)/);
    assert.match(source, /aria-busy/);
    assert.match(source, /data-location-state|dataset\.locationState/);
    assert.match(source, /stale/);
});

test('proposal network failure path preserves the typed draft and prior valid selection', () => {
    const source = readFileSync(new URL('../../../resources/js/location-selector.js', import.meta.url), 'utf8');
    const catchMatch = source.match(/catch \(error\) \{([\s\S]*?)\} finally/);

    assert.ok(catchMatch, 'proposal submit catch/finally block must remain explicit');
    assert.doesNotMatch(catchMatch[1], /nameInput\.value\s*=\s*['"]/);
    assert.doesNotMatch(catchMatch[1], /clearSelection\s*\(/);
});
