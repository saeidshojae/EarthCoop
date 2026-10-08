import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

test('proposed legal contracts are present and remain distinct from current database agreements', () => {
    const terms = read('resources/views/terms.blade.php');
    const najm = read('resources/views/najm-bahar/agreement.blade.php');

    assert.match(terms, /legal\/earthcoop-membership-statute\.fa\.md/);
    assert.match(terms, /legal\/earthcoop-terms-of-use\.fa\.md/);
    assert.match(najm, /legal\/najm-bahar-agreement\.fa\.md/);
    assert.match(terms, /\$terms->isNotEmpty\(\)/);
    assert.match(najm, /\$agreements->isNotEmpty\(\)/);
    assert.match(terms, /پیش‌نویس آماده تصویب/);
    assert.match(najm, /پیش‌نویس آماده تصویب/);
});

test('legal contract text uses safe Markdown and registered docs routes', () => {
    const terms = read('resources/views/terms.blade.php');
    const najm = read('resources/views/najm-bahar/agreement.blade.php');
    for (const view of [terms, najm]) {
        assert.match(view, /Str::markdown/);
        assert.match(view, /'html_input'\s*=>\s*'strip'/);
        assert.match(view, /'allow_unsafe_links'\s*=>\s*false/);
        assert.match(view, /config\('docs-links/);
    }
});

test('gold standard is valuation measure not physical collateral', () => {
    const najm = read('resources/legal/najm-bahar-agreement.fa.md');
    const membership = read('resources/legal/earthcoop-membership-statute.fa.md');
    assert.match(najm, /طلا فقط سنجه قراردادی/);
    assert.match(najm, /۱۰٬۰۰۰ بهار/);
    assert.match(membership, /شراکت جهانی بالقوه و عضویت بالفعل/);
    assert.match(membership, /شرکت‌های فناوری/);
});
