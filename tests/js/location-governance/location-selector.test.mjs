import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

import {
    normalizePickerPayload,
    projectScopePayload,
    projectScopeSelectionValues,
    pickerLevelLabel,
    selectionValues,
    shouldRenderNextLevel,
} from '../../../resources/js/location-selector.js';

const selectorSource = () => [
    '../../../resources/js/location-selector-core.js',
    '../../../resources/js/location-selector.js',
].map((path) => readFileSync(new URL(path, import.meta.url), 'utf8')).join('\n');

test('normalizes active locations, open proposals, and allowed types without fixed depth', () => {
    const streetPayload = normalizePickerPayload({ data: [], proposals: [], allowed_types: [
        { id: 12, key: 'alley', label: 'Alley', proposal_allowed: true },
        { id: 13, key: 'complex', label: 'Residential complex', proposal_allowed: true },
    ] });
    assert.equal(shouldRenderNextLevel(streetPayload), true);
    assert.deepEqual(streetPayload.allowedTypes.map((type) => type.key), ['alley', 'complex']);
    const alleyPayload = normalizePickerPayload({ data: [], proposals: [], allowed_types: [{ id: 13, key: 'complex', label: 'Residential complex', proposal_allowed: true }] });
    assert.equal(shouldRenderNextLevel(alleyPayload), true);
    assert.deepEqual(alleyPayload.allowedTypes.map((type) => type.key), ['complex']);
});

test('maps canonical and proposal identities to mutually exclusive hidden values', () => {
    assert.deepEqual(selectionValues({ id: 41, identity: 'location:41', is_residence_endpoint: true, status: 'active' }), { locationId: '41', proposalId: '' });
    assert.deepEqual(selectionValues({ id: 17, identity: 'proposal:17', status: 'pending', selectable: true }), { locationId: '', proposalId: '17' });
});

test('project scope reuses canonical traversal but excludes pending proposals and proposal creation', () => {
    const normalized = normalizePickerPayload({
        data: [{ id: 70, identity: 'location:70', type_key: 'city', label: 'City', status: 'active' }],
        proposals: [{ id: 71, identity: 'proposal:71', type_key: 'neighborhood', label: 'Pending neighborhood', status: 'pending', selectable: true }],
        allowed_types: [{ id: 72, key: 'neighborhood', label: 'Neighborhood', proposal_allowed: true }, { id: 73, key: 'campus', label: 'Campus', proposal_allowed: false }],
    });
    const scoped = projectScopePayload(normalized);
    assert.deepEqual(scoped.locations.map((item) => item.id), [70]);
    assert.deepEqual(scoped.proposals, []);
    assert.deepEqual(scoped.allowedTypes.map((type) => type.key), ['neighborhood', 'campus']);
    assert.equal(scoped.allowedTypes.every((type) => type.proposal_allowed === false), true);
});

test('project scope supports governance-only global and continent selections before entering the Location tree', () => {
    assert.deepEqual(projectScopeSelectionValues({ id: 5, identity: 'governance:5', status: 'active' }), { locationId: '', governanceAreaId: '5' });
    assert.deepEqual(projectScopeSelectionValues({ id: 7, identity: 'location:7', governance_area_id: 9, status: 'active' }), { locationId: '7', governanceAreaId: '' });
});

test('picker level labels describe server-driven location types instead of anonymous depth numbers', () => {
    assert.equal(pickerLevelLabel({ locations: [{ type_key: 'global' }] }, 0), 'جهانی');
    assert.equal(pickerLevelLabel({ locations: [{ type_key: 'continent' }] }, 1), 'قاره');
    assert.equal(pickerLevelLabel({ locations: [{ type_key: 'country' }] }, 2), 'کشور');
    assert.equal(pickerLevelLabel({ locations: [{ type_key: 'city' }, { type_key: 'rural_district' }] }, 5), 'شهر / دهستان');
    assert.equal(pickerLevelLabel({ locations: [{ type_key: 'campus' }] }, 9), 'سطح مکانی 10');
});

test('open proposals count as a renderable next level even when no approved child exists', () => {
    const payload = normalizePickerPayload({ data: [], proposals: [{ id: 7, identity: 'proposal:7', type_key: 'complex', label: 'Pending complex', status: 'pending', selectable: true }], allowed_types: [] });
    assert.equal(shouldRenderNextLevel(payload), true);
    assert.equal(payload.proposals[0].identity, 'proposal:7');
});

test('terminal proposals and inactive locations are removed from stale picker payloads', () => {
    const payload = normalizePickerPayload({
        data: [{ id: 1, identity: 'location:1', label: 'Active', status: 'active' }, { id: 2, identity: 'location:2', label: 'Inactive', status: 'inactive' }],
        proposals: [
            { id: 11, identity: 'proposal:11', label: 'Pending', status: 'pending', selectable: true },
            { id: 12, identity: 'proposal:12', label: 'Approved', status: 'approved', selectable: false },
            { id: 13, identity: 'proposal:13', label: 'Rejected', status: 'rejected', selectable: false },
            { id: 14, identity: 'proposal:14', label: 'Merged', status: 'merged', selectable: false },
        ], allowed_types: [],
    });
    assert.deepEqual(payload.locations.map((item) => item.id), [1]);
    assert.deepEqual(payload.proposals.map((item) => item.id), [11]);
    const terminalOnly = normalizePickerPayload({ data: [], proposals: [{ id: 99, identity: 'proposal:99', label: 'Resolved', status: 'approved', selectable: false }], allowed_types: [] });
    assert.equal(shouldRenderNextLevel(terminalOnly), false);
});

test('selection values reject stale terminal proposal and inactive canonical identities', () => {
    assert.deepEqual(selectionValues({ id: 12, identity: 'proposal:12', status: 'approved', selectable: false }), { locationId: '', proposalId: '' });
    assert.deepEqual(selectionValues({ id: 2, identity: 'location:2', status: 'inactive', is_residence_endpoint: true }), { locationId: '', proposalId: '' });
});

test('dynamic selector exposes visible labels and explicit loading error and stale states', () => {
    const source = selectorSource();
    assert.match(source, /document\.createElement\(['"]label['"]\)/);
    assert.match(source, /aria-busy/);
    assert.match(source, /data-location-state|dataset\.locationState/);
    assert.match(source, /stale/);
});

test('proposal network failure path preserves the typed draft and prior valid selection', () => {
    const source = readFileSync(new URL('../../../resources/js/location-selector.js', import.meta.url), 'utf8');
    const catchMatch = source.match(/catch \(error\) \{([\s\S]*?)\}\s*finally/);
    assert.ok(catchMatch, 'proposal submit catch/finally block must remain explicit');
    assert.doesNotMatch(catchMatch[1], /nameInput\.value\s*=\s*['"]/);
    assert.doesNotMatch(catchMatch[1], /clearSelection\s*\(/);
});

test('alternate schema branches remain server driven and never hard-code Iran micro-location order', () => {
    const payload = normalizePickerPayload({ data: [{ id: 70, identity: 'location:70', type_key: 'building', label: 'Building endpoint', status: 'active', is_residence_endpoint: true }], proposals: [], allowed_types: [{ id: 71, key: 'campus', label: 'Campus', proposal_allowed: false }] });
    assert.equal(shouldRenderNextLevel(payload), true);
    assert.deepEqual(payload.locations.map((item) => item.type_key), ['building']);
    assert.deepEqual(payload.allowedTypes.map((type) => type.key), ['campus']);
    const source = selectorSource();
    assert.doesNotMatch(source, /street\s*[-=>]+\s*alley|alley\s*[-=>]+\s*complex/i);
});

test('project scope and residence bootstrap from their governance bridges before canonical location traversal', () => {
    const source = selectorSource();
    assert.match(source, /\/location\/project-scope\/options\/root/);
    assert.match(source, /selected\.children_url/);
    assert.match(source, /\/location\/residence\/options\/root/);
});

test('empty and stale states are explicit rather than silently clearing a valid form state', () => {
    const source = selectorSource();
    assert.match(source, /['"]loading['"]/);
    assert.match(source, /['"]empty['"]/);
    assert.match(source, /['"]error['"]/);
    assert.match(source, /['"]stale['"]/);
    assert.match(source, /aria-live/);
});

test('project scope edit hydrates the saved canonical path instead of only preserving hidden ids', () => {
    const source = selectorSource();
    assert.match(source, /hydrateProjectScopePath/);
    assert.match(source, /initialLocationId|initialGovernanceAreaId/);
    assert.match(source, /select\.value\s*=\s*selected\.identity/);
});


test('cancelling a proposal panel is side-effect free and cannot synthesize pending levels', () => {
    const source = readFileSync(new URL('../../../resources/js/location-selector.js', import.meta.url), 'utf8');
    assert.doesNotMatch(source, /new MutationObserver\s*\(/, 'panel visibility must not be used as a proxy for proposal creation');
    assert.match(source, /location-proposal-created/, 'deeper traversal must react only to an explicit successful proposal-created event');
});

test('structural residence payload stays separate from project scope and uses localized claim copy', () => {
    const normalized = normalizePickerPayload({
        data: [], proposals: [], allowed_types: [],
        effective_allowed_types: [{ id: 31, key: 'street', label: 'خیابان', proposal_allowed: true }],
        structural_choices: [{ claim_type: 'no_neighborhood', status: 'pending', claim_id: 8 }],
        official_governance_base: false,
    });
    assert.deepEqual(normalized.effectiveAllowedTypes.map((type) => type.key), ['street']);
    assert.equal(normalized.structuralChoices[0].claim_type, 'no_neighborhood');
    const scoped = projectScopePayload(normalized);
    assert.deepEqual(scoped.structuralChoices, []);
    assert.deepEqual(scoped.effectiveAllowedTypes, []);
    const source = selectorSource();
    assert.match(source, /محله|منطقه شهری/);
    assert.doesNotMatch(source, />\s*(?:neighborhood|street|alley|building|complex)\s*</);
});

test('single proposal type does not render a redundant visible type selector', () => {
    const source = readFileSync(new URL('../../../resources/js/location-selector-core.js', import.meta.url), 'utf8');
    assert.match(source, /proposableTypes\.length\s*===\s*1/);
    assert.match(source, /typeSelect\.classList\.add\(['"]d-none['"]\)|typeSelect\.hidden\s*=\s*true/);
});
