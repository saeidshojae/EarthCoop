import test from 'node:test';
import assert from 'node:assert/strict';
import {
  settlementSearchUrl,
  settlementSelectableForClaim,
} from '../../../resources/js/registration-settlement-bridge.js';

test('settlement search is scoped by canonical parent location id', () => {
  assert.equal(
    settlementSearchUrl(42, 'آبادی نمونه'),
    '/location/reference-settlements?' + new URLSearchParams({ parent_location_id: '42', q: 'آبادی نمونه' }).toString()
  );
  assert.equal(
    settlementSearchUrl(42, ''),
    '/location/reference-settlements?parent_location_id=42'
  );
});

test('only geographically claimable settlement states are selectable', () => {
  assert.equal(settlementSelectableForClaim({
    classification: 'unverified_settlement',
    residential_eligibility: 'unverified',
    governance_authorized: false,
  }), true);
  assert.equal(settlementSelectableForClaim({
    classification: 'needs_review',
    residential_eligibility: 'unverified',
    governance_authorized: false,
  }), true);
  assert.equal(settlementSelectableForClaim({
    classification: 'verified_residential_village',
    residential_eligibility: 'verified',
    governance_authorized: false,
  }), true);
  assert.equal(settlementSelectableForClaim({
    classification: 'verified_nonresidential_place',
    residential_eligibility: 'ineligible',
    governance_authorized: false,
  }), false);
  assert.equal(settlementSelectableForClaim({
    classification: 'verified_residential_village',
    residential_eligibility: 'verified',
    governance_authorized: true,
  }), false);
});
