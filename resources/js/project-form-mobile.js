const PROJECT_FORM_STYLE_ID = 'earthcoop-project-form-mobile-style';

const projectFormStyles = `
.project-form-page,
.project-form-shell,
.project-form-section,
.project-form-actions,
.project-scope-mobile-surface {
    min-width: 0;
}

.project-form-page {
    width: 100%;
    max-width: 100%;
    overflow-x: clip;
}

.project-form-shell {
    width: 100%;
    max-width: 100%;
}

.project-form-section input,
.project-form-section select,
.project-form-section textarea,
.project-form-actions button,
.project-form-actions a,
.project-scope-mobile-surface select,
.project-scope-mobile-surface button {
    min-height: 44px;
    max-width: 100%;
}

.project-scope-mobile-surface,
.project-scope-mobile-surface [data-location-levels],
.project-scope-mobile-surface [data-location-selector-status] {
    min-width: 0;
    max-width: 100%;
}

.project-form-choice-row {
    min-width: 0;
}

.project-form-choice-copy {
    min-width: 0;
    overflow-wrap: anywhere;
}

@media (max-width: 640px) {
    .project-form-page {
        padding-inline: 0.875rem !important;
        padding-bottom: calc(5.5rem + env(safe-area-inset-bottom));
    }

    .project-form-shell {
        border-radius: 1rem !important;
    }

    .project-form-section {
        padding: 1rem !important;
    }

    .project-form-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .project-form-section input:not([type="radio"]):not([type="checkbox"]),
    .project-form-section select,
    .project-form-section textarea {
        width: 100%;
        min-width: 0;
    }

    .project-form-choice-row {
        display: grid !important;
        grid-template-columns: auto minmax(0, 1fr) !important;
        align-items: start !important;
        gap: 0.75rem !important;
        width: 100%;
        min-height: 48px;
    }

    .project-form-choice-row > input[type="radio"],
    .project-form-choice-row > input[type="checkbox"] {
        width: 1.25rem !important;
        height: 1.25rem !important;
        min-width: 1.25rem;
        min-height: 1.25rem;
        margin: 0.125rem 0 0 !important;
    }

    .project-form-choice-copy,
    .project-form-investment-option {
        font-size: 0.875rem;
        line-height: 1.65;
    }

    .project-form-investment-option {
        padding: 0.875rem !important;
    }

    .project-scope-mobile-surface {
        overflow-wrap: anywhere;
    }

    .project-scope-mobile-surface [data-location-levels] {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.75rem;
    }

    .project-form-actions {
        position: sticky;
        bottom: 0;
        z-index: 20;
        display: grid !important;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.625rem !important;
        padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom)) !important;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(10px);
    }

    .project-form-actions button,
    .project-form-actions a {
        width: 100%;
        min-height: 44px;
        justify-content: center;
        text-align: center;
    }

    .project-form-hoda-safe #najm-hoda-widget {
        max-width: calc(100vw - 1.75rem);
    }
}
`;

const ensureProjectFormStyles = () => {
    if (document.getElementById(PROJECT_FORM_STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = PROJECT_FORM_STYLE_ID;
    style.textContent = projectFormStyles;
    document.head.appendChild(style);
};

const closestChoiceRow = (input) => {
    const label = input.closest('label');
    if (label) return label;

    const parent = input.parentElement;
    if (!parent) return null;

    if (parent.children.length <= 3) return parent;
    return null;
};

const enhanceChoiceRow = (input, { investment = false } = {}) => {
    const row = closestChoiceRow(input);
    if (!row) return;

    row.classList.add('project-form-choice-row');
    if (investment) row.classList.add('project-form-investment-option');

    Array.from(row.children).forEach((child) => {
        if (child !== input) child.classList.add('project-form-choice-copy');
    });
};

const enhanceProjectForm = () => {
    const form = document.querySelector('form[action*="/najm-bahar/projects"]');
    if (!form) return;

    const page = form.closest('.container') || form.parentElement;
    page?.classList.add('project-form-page', 'project-form-hoda-safe');
    form.classList.add('project-form-shell');

    form.querySelectorAll(':scope > div').forEach((section) => {
        if (section.querySelector('input, select, textarea')) section.classList.add('project-form-section');
    });

    form.querySelectorAll('input[name="investment_method"]').forEach((input) => {
        enhanceChoiceRow(input, { investment: true });
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
        enhanceChoiceRow(input);
    });

    const submit = form.querySelector('button[type="submit"], input[type="submit"]');
    const actions = submit?.parentElement;
    actions?.classList.add('project-form-actions');

    const projectScope = form.querySelector('[data-location-selector-context="project-scope"]');
    projectScope?.classList.add('project-scope-mobile-surface');

    ensureProjectFormStyles();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceProjectForm, { once: true });
} else {
    enhanceProjectForm();
}
