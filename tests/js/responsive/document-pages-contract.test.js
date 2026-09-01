import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

test('terms and Najm Bahar agreement are covered by the shared responsive document stylesheet', () => {
    const terms = read('resources/views/terms.blade.php');
    const agreement = read('resources/views/najm-bahar/agreement.blade.php');
    const vite = read('resources/css/vite.css');
    const responsive = read('resources/css/document-pages.css');

    assert.match(terms, /accordion-wrapper/);
    assert.match(agreement, /agreement-card/);
    assert.match(vite, /@import\s+"\.\/document-pages\.css"/);
    assert.match(responsive, /\.accordion-wrapper/);
    assert.match(responsive, /\.agreement-card/);
    assert.match(responsive, /@media\s*\(max-width:\s*768px\)/);
});

test('mobile document contract preserves width across nested legal sections and rich text', () => {
    const responsive = read('resources/css/document-pages.css');

    assert.match(responsive, /--ec-document-gutter:\s*12px/);
    assert.match(responsive, /min-width:\s*0/);
    assert.match(responsive, /overflow-wrap:\s*anywhere/);
    assert.match(responsive, /max-width:\s*100%/);
    assert.match(responsive, /\.agreement-subsection/);
    assert.match(responsive, /\.nested-content/);
    assert.match(responsive, /\.prose/);
});
