import assert from 'node:assert/strict';
import test from 'node:test';
import fs from 'node:fs';

import {
  localizeLocationTypeLabel,
  proposalParentPayload,
  selectionValues,
} from '../../../resources/js/location-selector.js';

const selectorSource = fs.readFileSync(new URL('../../../resources/js/location-selector.js', import.meta.url), 'utf8');
const uxSource = fs.readFileSync(new URL('../../../resources/js/registration-location-ux.js', import.meta.url), 'utf8');

test('known residence type keys render Persian labels for fa locale', () => {
  const expected = {
    country: 'کشور', province: 'استان / ایالت', county: 'شهرستان / ناحیه', section: 'بخش', city: 'شهر',
    rural_district: 'دهستان', village: 'روستا', urban_region: 'منطقه شهری', neighborhood: 'محله',
    street: 'خیابان', alley: 'کوچه', complex: 'مجتمع', building: 'ساختمان',
  };
  for (const [key, label] of Object.entries(expected)) {
    assert.equal(localizeLocationTypeLabel({ key, label: key }, 'fa'), label);
  }
  assert.equal(localizeLocationTypeLabel({ key: 'campus', label: 'Campus' }, 'fa'), 'Campus');
  assert.equal(localizeLocationTypeLabel({ key: 'alley', label: 'alley' }, 'fa'), 'کوچه');
  assert.equal(localizeLocationTypeLabel({ key: 'building', label: 'building' }, 'fa'), 'ساختمان');
  assert.equal(localizeLocationTypeLabel({ key: 'complex', label: 'complex' }, 'fa'), 'مجتمع');
});

test('proposal parent payload never fabricates a canonical location id', () => {
  assert.deepEqual(proposalParentPayload('location:12'), { parent_location_id: 12 });
  assert.deepEqual(proposalParentPayload('proposal:34'), { parent_location_proposal_id: 34 });
  assert.deepEqual(proposalParentPayload(''), {});
});

test('open proposal remains selectable while client supports loading deeper proposal children', () => {
  assert.deepEqual(selectionValues({ id: 34, identity: 'proposal:34', status: 'pending', selectable: true }), { locationId: '', proposalId: '34' });
  assert.match(selectorSource, /location\/proposals\/\$\{encodeURIComponent\(selected\.id\)\}\/children/);
  assert.doesNotMatch(selectorSource, /if \(selected\.picker_kind === 'proposal'\) return;/);
});

test('proposal affordance is compact secondary UI instead of a dominant green card', () => {
  assert.match(selectorSource, /location-proposal-toggle/);
  assert.match(selectorSource, /افزودن مکان جدید/);
  assert.match(uxSource, /location-proposal-toggle[^}]*background:\s*transparent/si);
  assert.match(uxSource, /location-proposal-shell[^}]*border:\s*0/si);
  assert.doesNotMatch(uxSource, /data-registration-proposal-visual-hint/);
});

test('proposal form hides redundant type selector when exactly one type is allowed', () => {
  assert.match(selectorSource, /types\.length\s*===\s*1/);
  assert.match(selectorSource, /افزودن.*جدید/);
  assert.match(selectorSource, /dataset\.locationProposalType/);
});

test('proposal actions expose clear mobile-first primary secondary and pending hooks', () => {
  assert.match(selectorSource, /dataset\.locationProposalSubmit/);
  assert.match(selectorSource, /dataset\.locationProposalCancel/);
  assert.match(selectorSource, /dataset\.locationPendingBadge/);
  assert.match(uxSource, /@media\s*\(max-width:\s*640px\)/);
  assert.match(uxSource, /location-proposal-actions[^}]*width:\s*100%/si);
});

test('Persian path rendering uses selected localized labels rather than canonical type names', () => {
  assert.match(uxSource, /option\.textContent/);
  assert.doesNotMatch(uxSource, /Urban region|Neighborhood|Street|Alley|Residential complex|Building/);
});


test('proposal type controls never render raw backend English labels for known Persian location types', () => {
  assert.match(selectorSource, /localizeLocationTypeLabel\(item\)/);
  assert.doesNotMatch(selectorSource, /option\.textContent\s*=\s*item\.label\s*\|\|\s*item\.key/);
});
