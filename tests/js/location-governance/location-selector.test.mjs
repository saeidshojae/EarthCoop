import test from 'node:test';
import assert from 'node:assert/strict';

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
        selectionValues({ id: 41, identity: 'location:41', is_residence_endpoint: true }),
        { locationId: '41', proposalId: '' },
    );

    assert.deepEqual(
        selectionValues({ id: 17, identity: 'proposal:17', selectable: true }),
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
