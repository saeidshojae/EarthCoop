import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const source = readFileSync(path.resolve(here, '../../../resources/js/registration-location-ux.js'), 'utf8');

test('persisted residence replay runs for canonical-only paths as well as proposal paths', () => {
    assert.match(source, /if \(!levels \|\| !currentPath\.length\) return;/);
    assert.doesNotMatch(source, /if \(!levels \|\| !currentPath\.length \|\| !currentProposalId\) return;/);
    assert.match(source, /dispatchEvent\(new Event\('change', \{ bubbles: true \}\)\)/);
});

test('persisted residence replay preserves the correct terminal identity kind', () => {
    assert.match(source, /const terminalIdentity = currentPath\[currentPath\.length - 1\]/);
    assert.match(source, /terminalIdentity\.startsWith\('proposal:'\)/);
    assert.match(source, /locationInput\.value = currentLocationId/);
    assert.match(source, /proposalInput\.value = currentProposalId/);
});

test('visible residence path contains only actual location picker selections', () => {
    assert.match(source, /querySelectorAll\('\[data-location-select\]'\)/);
    assert.doesNotMatch(source, /querySelectorAll\('select'\)/);
});


test('pending residence path uses one compact review indicator instead of repeating review text per proposal level', () => {
    assert.match(source, /pendingCount/);
    assert.match(source, /در انتظار بررسی/);
    assert.doesNotMatch(source, /\`\$\{item\.label\} \(در انتظار بررسی\)\`/);
});

test('persisted deep residence replay can pass through type-first micro choice gates', () => {
    assert.match(source, /data-location-type-choice-key/);
    assert.match(source, /identityTypeKey/);
    assert.match(source, /button\.click\(\)/);
});
