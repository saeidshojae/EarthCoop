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
    rural_district: 'دهستان', village: 'روستا', urban_region: 'منطقه', neighborhood: 'محله',
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
  assert.ok(selectorSource.includes('/location/proposals/${encodeURIComponent(proposalId)}/children'));
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

test('mixed micro children expose type-first controls before location choices', () => {
  const coreSource = fs.readFileSync(new URL('../../../resources/js/location-selector-core.js', import.meta.url), 'utf8');
  assert.match(coreSource, /dataset\.locationTypeChoice/);
  assert.match(coreSource, /نوع ادامه مسیر/);
  assert.match(coreSource, /filterPayloadByType/);
  assert.match(selectorSource, /dataset\.locationTypeChoice/);
});

test('proposal cancel is UI-only and never invokes proposal creation', () => {
  const coreSource = fs.readFileSync(new URL('../../../resources/js/location-selector-core.js', import.meta.url), 'utf8');
  const cancelHandler = coreSource.match(/cancel\.addEventListener\('click',[\s\S]*?\}\);\s*submit\.addEventListener/)?.[0] || '';
  assert.match(cancelHandler, /panel\.classList\.add\('d-none'\)/);
  assert.match(cancelHandler, /aria-expanded', 'false'/);
  assert.match(cancelHandler, /feedback\.textContent = ''/);
  assert.doesNotMatch(cancelHandler, /fetch\(/);
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

test('registration and profile residence path uses the unified Persian region label', () => {
    const source = uxSource;
    assert.match(source, /urban_region:\s*'منطقه'/);
    assert.doesNotMatch(source, /urban_region:\s*'منطقه شهری'/);
});

test('new canonical-parent proposal is inserted into the current selector and selected immediately', () => {
  assert.match(selectorSource, /select\.appendChild\(option\)/);
  assert.match(selectorSource, /select\.value = option\.value/);
  assert.match(selectorSource, /setSelection\(host, result\.id, result\.type_key \|\| option\.dataset\.typeKey \|\| ''\)/);
});


test('pending proposals continue through their own children endpoint', () => {
  assert.match(selectorSource, /parent_location_proposal_id/);
  assert.ok(selectorSource.includes('/location/proposals/${encodeURIComponent(proposalId)}/children'));
  assert.match(selectorSource, /appendPendingLevel\(host, payload, depth \+ 1, `proposal:\$\{proposalId\}`\)/);
  assert.doesNotMatch(selectorSource, /if \(selected\.picker_kind === 'proposal'\) return/);
});

test('structural state UI supports canonical and pending parents', () => {
  assert.match(selectorSource, /`\/location\/proposals\/\$\{encodeURIComponent\(proposalId\)\}\/structure-claims`/);
  assert.match(selectorSource, /addPendingStructuralPanel/);
});


test('pending structural choices render even when no child proposal type is currently available', () => {
  assert.match(selectorSource, /hasStructuralChoices/);
  assert.match(selectorSource, /!allProposals\.length && !allTypes\.length && hasStructuralChoices/);
  assert.match(selectorSource, /addPendingStructuralPanel\(host, wrapper, payload, `proposal:\$\{proposalId\}`, depth \+ 1\)/);
});

test('registration stops after a pending neighborhood while ordinary residence may continue deeper', () => {
  assert.match(selectorSource, /isRegistration && selectedTypeKey === 'neighborhood'/);
  assert.match(selectorSource, /مکان و حکمرانی من/);
  assert.match(selectorSource, /return;[\s\S]*status\(host, 'در حال دریافت گزینه‌های سطح بعد/);
});
