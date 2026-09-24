import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

import {
    normalizePickerPayload,
    registrationPayload,
    microContinuationTypes,
    filterPayloadByType,
    projectScopePayload,
    projectScopeSelectionValues,
    pickerLevelLabel,
    selectionValues,
    shouldRenderNextLevel,
    shouldStopRegistrationAtProposal,
    locationDisplayLabel,
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

test('structural choices keep a sparse governance level renderable even before any child exists', () => {
    const normalized = normalizePickerPayload({
        data: [],
        proposals: [],
        allowed_types: [],
        structural_choices: [{ claim_type: 'no_neighborhood', status: 'available', claim_id: null }],
    });
    assert.equal(shouldRenderNextLevel(normalized), true);
});

test('changing a micro branch clears deeper selection state before rendering the new branch', () => {
    const source = selectorSource();
    assert.match(source, /removeDeeperLevels\(depth - 1\)/);
    assert.match(source, /selectedPath\.keys\(\).*key >= depth/);
    assert.match(source, /clearSelection\(\)/);
    assert.match(source, /clearStructuralClaimsAfterDepth\(form, depth\)/);
});

test('maps canonical and proposal identities to mutually exclusive hidden values', () => {
    assert.deepEqual(selectionValues({ id: 41, identity: 'location:41', is_residence_endpoint: true, status: 'active' }), { locationId: '41', proposalId: '' });
    assert.deepEqual(selectionValues({ id: 17, identity: 'proposal:17', status: 'pending', selectable: true }), { locationId: '', proposalId: '17' });
});

test('registration traversal stops before micro locations while ordinary residence remains deep capable', () => {
    const normalized = normalizePickerPayload({
        data: [{ id: 80, identity: 'location:80', type_key: 'street', label: 'Street', status: 'active', is_residence_endpoint: true }],
        proposals: [{ id: 81, identity: 'proposal:81', type_key: 'street', label: 'Pending street', status: 'pending', selectable: true }],
        allowed_types: [{ id: 82, key: 'urban_region', label: 'Region', proposal_allowed: false }],
        effective_allowed_types: [{ id: 83, key: 'street', label: 'Street', proposal_allowed: true }],
    });

    assert.equal(shouldRenderNextLevel(normalized), true);
    const registration = registrationPayload(normalized);
    assert.deepEqual(registration.locations, []);
    assert.deepEqual(registration.proposals, []);
    assert.deepEqual(registration.allowedTypes, []);
    assert.equal(shouldRenderNextLevel(registration), false);
});

test('registration structural endpoint signal survives micro filtering and controls terminal submit state', () => {
    const normalized = normalizePickerPayload({
        data: [{ id: 301, identity: 'location:301', type_key: 'street', label: 'Street', status: 'active', is_residence_endpoint: true }],
        proposals: [],
        allowed_types: [{ id: 302, key: 'street', label: 'Street', proposal_allowed: true }],
        registration_endpoint_allowed: true,
    });

    const registration = registrationPayload(normalized);
    assert.equal(registration.registrationEndpointAllowed, true);
    assert.equal(shouldRenderNextLevel(registration), false);

    const source = selectorSource();
    assert.match(source, /item\?\.type_key !== 'neighborhood'/, 'village/city schema endpoints must not enable registration before structural validation');
    assert.match(source, /children\.registrationEndpointAllowed/, 'registration must use the server terminal signal after structural selection');
    assert.match(source, /refreshed\.registrationEndpointAllowed/, 'structural refresh must terminalize the selected parent when micro levels are filtered');
});

test('registration stops immediately after a pending neighborhood proposal becomes the residence base', () => {
    assert.equal(shouldStopRegistrationAtProposal('registration', { type_key: 'neighborhood', status: 'pending' }), true);
    assert.equal(shouldStopRegistrationAtProposal('registration', { type_key: 'street', status: 'pending' }), false);
    assert.equal(shouldStopRegistrationAtProposal('residence', { type_key: 'neighborhood', status: 'pending' }), false);

    const source = selectorSource();
    const stopIndex = source.indexOf('shouldStopRegistrationAtProposal(context, proposal)');
    const childrenFetchIndex = source.indexOf('result.children_url', stopIndex);
    assert.ok(stopIndex >= 0, 'registration proposal completion must have an explicit terminal guard');
    assert.ok(childrenFetchIndex > stopIndex, 'the terminal guard must run before any pending-child request');
});

test('registration keeps non-micro governance continuation available', () => {
    const normalized = normalizePickerPayload({
        data: [{ id: 90, identity: 'location:90', type_key: 'urban_region', label: 'Region', status: 'active' }],
        proposals: [],
        allowed_types: [{ id: 91, key: 'urban_region', label: 'Region', proposal_allowed: false }],
    });
    const registration = registrationPayload(normalized);
    assert.deepEqual(registration.locations.map((item) => item.type_key), ['urban_region']);
    assert.deepEqual(registration.allowedTypes.map((item) => item.key), ['urban_region']);
    assert.equal(shouldRenderNextLevel(registration), true);
});

test('micro residence continuation requires choosing one child type before showing mixed children', () => {
    const normalized = normalizePickerPayload({
        data: [
            { id: 101, identity: 'location:101', type_key: 'alley', label: 'کوچه دوستی', status: 'active' },
            { id: 102, identity: 'location:102', type_key: 'complex', label: 'مجتمع بهارستان', status: 'active' },
            { id: 103, identity: 'location:103', type_key: 'building', label: 'ساختمان ۳۵', status: 'active' },
        ],
        proposals: [{ id: 104, identity: 'proposal:104', type_key: 'building', label: 'ساختمان پیشنهادی', status: 'pending', selectable: true }],
        allowed_types: [
            { id: 201, key: 'alley', label: 'Alley', proposal_allowed: true },
            { id: 202, key: 'complex', label: 'Complex', proposal_allowed: true },
            { id: 203, key: 'building', label: 'Building', proposal_allowed: true },
        ],
    });

    assert.deepEqual(microContinuationTypes(normalized).map((type) => type.key), ['alley', 'complex', 'building']);
    const buildings = filterPayloadByType(normalized, 'building');
    assert.deepEqual(buildings.locations.map((item) => item.id), [103]);
    assert.deepEqual(buildings.proposals.map((item) => item.id), [104]);
    assert.deepEqual(buildings.allowedTypes.map((type) => type.key), ['building']);
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

test('breadcrumb uses typed display labels across the full residence path', () => {
    const source = selectorSource();
    assert.match(source, /displayLocationLabel|locationDisplayLabel/);
    assert.match(source, /data-location-path|locationPath/);
    assert.match(source, /TYPE_LABELS/);
});

test('residence selector renders only absence structural actions while keeping backend payload support separate', () => {
    const source = selectorSource();
    assert.match(source, /structuralChoices/);
    assert.match(source, /locations\/structure-claims/);
    assert.match(source, /no_urban_region/);
    assert.match(source, /no_neighborhood/);
    assert.doesNotMatch(source, /single_urban_region/);
    assert.doesNotMatch(source, /single_neighborhood/);
    assert.doesNotMatch(source, /چند منطقه دارد/);
    assert.doesNotMatch(source, /چند محله دارد/);
    assert.match(source, /effectiveAllowedTypes/);
});


test('typed breadcrumb normalizes urban region wording and avoids duplicate prefixes', () => {
    assert.equal(locationDisplayLabel({ type_key: 'urban_region', label: '۶' }), 'منطقه ۶');
    assert.equal(locationDisplayLabel({ type_key: 'urban_region', label: 'منطقه شهری ۶' }), 'منطقه ۶');
    assert.equal(locationDisplayLabel({ type_key: 'province', label: 'استان مازندران' }), 'استان مازندران');
    assert.equal(locationDisplayLabel({ type_key: 'street', label: 'الف' }), 'خیابان الف');
});

test('changing an ancestor selection clears remembered structural claim ids from the abandoned path', () => {
    const source = selectorSource();
    assert.match(source, /clearStructuralClaimsAfterDepth/);
    assert.match(source, /locationStructureClaimDepth/);
    assert.match(source, /clearStructuralClaimsAfterDepth\(form, depth\)/);
});

test('structural claims are owned by the selected parent depth and survive choosing its effective child', () => {
    const source = selectorSource();
    assert.match(
        source,
        /buildStructuralClaimPanel\(host, payload\.structuralChoices, parentLocationId, Math\.max\(depth - 1, 0\)/,
        'a city topology claim rendered with its neighborhood choices must be remembered at the city depth'
    );
    assert.match(
        source,
        /select\.addEventListener\(['"]change['"][\s\S]*?clearStructuralClaimsAfterDepth\(form, depth\)/,
        'choosing the effective child may clear claims owned by that child or deeper, but not the parent topology claim'
    );
});


test('only approved structural claims auto-hydrate while open claims remain an explicit user choice', () => {
    const source = selectorSource();
    assert.match(source, /statusValue === 'approved'[\s\S]*?rememberStructuralClaim\(host, claimId, depth\)/);
    assert.match(source, /OPEN_PROPOSAL_STATUSES\.has\(statusValue\)[\s\S]*?explicitlySelected/);
    assert.match(
        source,
        /OPEN_PROPOSAL_STATUSES\.has\(statusValue\)[\s\S]*?if \(explicitlySelected\) rememberStructuralClaim\(host, claimId, depth\)/,
        'an open claim may be restored only when that user explicitly selected it'
    );
    assert.doesNotMatch(
        source,
        /\(statusValue === 'approved' \|\| OPEN_PROPOSAL_STATUSES\.has\(statusValue\)\)[\s\S]{0,220}?rememberStructuralClaim\(host, claimId, depth\)/,
        'a pending community claim must not silently become the next user\'s residence choice'
    );
});

test('structural-claim navigation sends only explicitly selected absence claim ids', () => {
    const source = selectorSource();
    assert.match(source, /structuralClaimContextUrl\(baseUrl, form\)/);
    assert.match(source, /location_structure_claim_ids%5B%5D=/);
    assert.match(source, /pendingStructuralClaimContextUrl/);
    assert.match(source, /removePendingStructuralClaimIds\(host, claimIds\)/);
    assert.match(source, /location_structure_claim_ids: pendingStructuralClaimIds\(host\)/);
    assert.doesNotMatch(source, /مسیر معمولی انتخاب شد/);
});

test('structural claim UI exposes only exceptional absence actions inside the disclosure', () => {
    const source = selectorSource();
    assert.match(source, /STRUCTURAL_CLAIM_GROUPS/);
    assert.match(source, /claimTypes: \['no_urban_region'\]/);
    assert.match(source, /claimTypes: \['no_neighborhood'\]/);
    assert.match(source, /این شهر منطقه‌بندی ندارد/);
    assert.match(source, /این محدوده محله‌بندی ندارد/);
    assert.match(source, /data-location-structural-choice/);
    assert.match(source, /aria-pressed/);
    assert.doesNotMatch(source, /single_urban_region|single_neighborhood/);
});

test('canonical registration proposal events bypass legacy capture and keep server terminal authority', async () => {
    const listeners = [];
    const originalDocument = globalThis.document;
    globalThis.document = {
        addEventListener(type, handler, capture) { listeners.push({ type, handler, capture }); },
    };
    try {
        await import('../../../resources/js/location-selector.js?canonical-registration-capture-regression');
        const captured = listeners.find(({ type, capture }) => type === 'change' && capture === true);
        assert.ok(captured, 'legacy capture listener must be present for non-registration consumers');
        let intercepted = false;
        const host = { dataset: { locationSelectorContext: 'registration' } };
        const select = {
            value: 'proposal:9',
            closest(selector) { return selector === '[data-location-selector]' ? host : null; },
        };
        captured.handler({
            target: { closest(selector) { return selector === '[data-location-select]' ? select : null; } },
            preventDefault() { intercepted = true; },
            stopImmediatePropagation() { intercepted = true; },
        });
        assert.equal(intercepted, false, 'registration change must reach canonical selector');
        const source = readFileSync(new URL('../../../resources/js/location-selector.js', import.meta.url), 'utf8');
        assert.match(source, /\['project-scope', 'registration'\]\.includes\(host\.dataset\.locationPurpose \|\| host\.dataset\.locationSelectorContext\)/);
    } finally {
        if (originalDocument === undefined) delete globalThis.document;
        else globalThis.document = originalDocument;
    }
});


test('all residence contexts use one compact missing-option disclosure instead of always-visible proposal controls', () => {
    const source = selectorSource();
    assert.match(source, /dataLocationExceptionToggle|locationExceptionToggle/);
    assert.match(source, /exceptionLinkLabel/);
    assert.match(source, /منطقه من در فهرست نیست/);
    assert.match(source, /روستا یا آبادی من در فهرست نیست/);
    assert.match(source, /محله من در فهرست نیست/);
    assert.match(source, /خیابان من در فهرست نیست/);
    assert.match(source, /کوچه من در فهرست نیست/);
    assert.match(source, /مجتمع من در فهرست نیست/);
    assert.match(source, /ساختمان من در فهرست نیست/);
    assert.match(source, /earthcoop-location-exception-open/);
});

test('reference settlement neighborhood rejoins the shared deep picker through the same exception disclosure', () => {
    const source = selectorSource();
    assert.match(source, /earthcoop-location-reference-selected/);
    assert.match(source, /proposalPath/);
    assert.match(source, /reference_settlement/);
    assert.match(source, /appendLevel\(children, depth, null, false, parentProposalId\)/);
    assert.match(source, /dataLocationException|locationException|location-exception/i);
    assert.match(source, /enableReferenceSearch/);
});
