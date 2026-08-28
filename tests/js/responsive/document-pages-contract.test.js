import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

test('terms and Najm Bahar agreement share the responsive document surface contract', () => {
    const terms = read('resources/views/terms.blade.php');
    const agreement = read('resources/views/najm-bahar/agreement.blade.php');
    const responsive = read('resources/css/responsive/document-pages.css');

    assert.match(terms, /ec-document-page/);
    assert.match(terms, /ec-document-surface/);
    assert.match(agreement, /ec-document-page/);
    assert.match(agreement, /ec-document-surface/);
    assert.match(responsive, /\.ec-document-page/);
    assert.match(responsive, /\.ec-document-richtext/);
    assert.match(responsive, /@media\s*\(max-width:\s*768px\)/);
});

test('mobile document contract preserves width across nested legal sections', () => {
    const responsive = read('resources/css/responsive/document-pages.css');

    assert.match(responsive, /--ec-document-gutter:\s*12px/);
    assert.match(responsive, /min-width:\s*0/);
    assert.match(responsive, /overflow-wrap:\s*anywhere/);
    assert.match(responsive, /max-width:\s*100%/);
    assert.match(responsive, /\.ec-document-node/);
});
