const LEGACY_DOCS_CENTER_URL = 'https://docs.earthcoop.ir/fa/introduction';
const CANONICAL_DOCS_CENTER_URL = 'https://docs.earthcoop.ir/';

const normalizeDocsCenterLinks = () => {
    document.querySelectorAll(`a[href="${LEGACY_DOCS_CENTER_URL}"]`).forEach((link) => {
        link.setAttribute('href', CANONICAL_DOCS_CENTER_URL);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', normalizeDocsCenterLinks, { once: true });
} else {
    normalizeDocsCenterLinks();
}

export { normalizeDocsCenterLinks };
