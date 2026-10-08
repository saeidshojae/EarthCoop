import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(path, 'utf8');

test('published legal texts render as nested volumes and readable clauses', () => {
    const terms = read('resources/views/terms.blade.php');
    const najm = read('resources/views/najm-bahar/agreement.blade.php');
    const partial = read('resources/views/partials/legal/structured-document.blade.php');

    assert.match(terms, /published-legal-texts/);
    assert.match(najm, /published-najm-bahar-agreement/);
    assert.match(terms, /earthcoop-membership-statute\.fa\.md/);
    assert.match(terms, /earthcoop-terms-of-use\.fa\.md/);
    assert.match(najm, /najm-bahar-agreement\.fa\.md/);
    assert.match(partial, /<details class="ec-legal-clause"/);
    assert.match(partial, /preg_split/);
    assert.match(partial, /'html_input'\s*=>\s*'strip'/);
    assert.match(partial, /'allow_unsafe_links'\s*=>\s*false/);
});

test('existing terms, financial approval and stored clauses stay intact', () => {
    const terms = read('resources/views/terms.blade.php');
    const najm = read('resources/views/najm-bahar/agreement.blade.php');
    assert.match(terms, /\$terms->isNotEmpty\(\)/);
    assert.match(terms, /route\('terms\.store'\)/);
    assert.match(najm, /\$agreements->isNotEmpty\(\)/);
    assert.match(najm, /route\('najm-bahar\.agreement\.process'\)/);
    assert.match(terms, /config\('docs-links\.foundational'/);
    assert.match(najm, /config\('docs-links\.foundational'/);
});

test('founder-run status and value of Bahar are transparent without placeholders', () => {
    const terms = read('resources/legal/earthcoop-terms-of-use.fa.md');
    const najm = read('resources/legal/najm-bahar-agreement.fa.md');
    const membership = read('resources/legal/earthcoop-membership-statute.fa.md');
    assert.match(terms, /شخصیت حقوقی مستقل و ثبت‌شده‌ای ندارد/);
    assert.doesNotMatch(terms + najm, /\[[^\]]*پیش از انتشار/);
    assert.match(najm, /طلا فقط سنجه قراردادی/);
    assert.match(najm, /۱۰٬۰۰۰ بهار/);
    assert.match(membership, /شراکت جهانی بالقوه و عضویت بالفعل/);
});

test('clauses have mobile spacing and a responsive width', () => {
    const css = read('resources/css/document-pages.css');
    assert.match(css, /\.ec-legal-clauses/);
    assert.match(css, /\.ec-legal-clause-body/);
    assert.match(css, /@media\s*\(max-width:\s*768px\)/);
});
