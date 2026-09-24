import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
  settlementSearchUrl,
  settlementChildrenUrl,
  settlementSelectableForClaim,
  settlementNeighborhoodProposalPayload,
} from '../../../resources/js/registration-settlement-bridge.js';

test('settlement bridge stays parent-scoped and exposes child URL', () => {
  assert.equal(settlementSearchUrl(42, 'وری'), '/location/reference-settlements?' + new URLSearchParams({ parent_location_id: '42', q: 'وری' }).toString());
  assert.equal(settlementChildrenUrl('IR-1404-99001'), '/location/reference-settlements/IR-1404-99001/children');
});
test('claimability never implies governance authorization', () => {
  assert.equal(settlementSelectableForClaim({ classification:'unverified_settlement', residential_eligibility:'unverified', governance_authorized:false, operational_promotion_allowed:false }), true);
  assert.equal(settlementSelectableForClaim({ classification:'verified_residential_village', residential_eligibility:'verified', governance_authorized:false, operational_promotion_allowed:false }), true);
  assert.equal(settlementSelectableForClaim({ classification:'verified_nonresidential_place', residential_eligibility:'ineligible', governance_authorized:false, operational_promotion_allowed:false }), false);
  assert.equal(settlementSelectableForClaim({ classification:'verified_residential_village', residential_eligibility:'verified', governance_authorized:true, operational_promotion_allowed:false }), false);
});
test('neighborhood proposal keeps reference settlement parent', () => {
  assert.deepEqual(settlementNeighborhoodProposalPayload(77, 12, 'محله وری'), {
    parent_reference_settlement_id:77, location_type_id:12, canonical_name:'محله وری', localized_names:{fa:'محله وری'}
  });
});


test('bridge supports profile/admin contexts and carries persisted deep proposal path', () => {
  const source = readFileSync(new URL('../../../resources/js/registration-settlement-bridge.js', import.meta.url), 'utf8');
  assert.match(source, /admin-user-residence/);
  assert.match(source, /referenceSettlementCurrentProposalPath/);
  assert.match(source, /earthcoop-location-reference-selected/);
  assert.match(source, /referenceBranchActive/);
});
