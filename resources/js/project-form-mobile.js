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

@media (max-width: 640px) {
    .project-form-page {
        padding-inline: 0.875rem !important;
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

    .project-form-section input,
    .project-form-section select,
    .project-form-section textarea {
        width: 100%;
        min-width: 0;
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
}
`;

const ensureProjectFormStyles = () => {
    if (document.getElementById(PROJECT_FORM_STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = PROJECT_FORM_STYLE_ID;
    style.textContent = projectFormStyles;
    document.head.appendChild(style);
};

const enhanceProjectForm = () => {
    const form = document.querySelector('form[action*="/najm-bahar/projects"]');
    if (!form) return;

    const page = form.closest('.container') || form.parentElement;
    page?.classList.add('project-form-page');
    form.classList.add('project-form-shell');

    form.querySelectorAll(':scope > div').forEach((section) => {
        if (section.querySelector('input, select, textarea')) section.classList.add('project-form-section');
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
