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
