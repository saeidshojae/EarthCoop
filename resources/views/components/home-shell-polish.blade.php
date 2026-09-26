{{-- Home-only presentation polish for the shared shell and state-aware Home surfaces. --}}
<style>
    .home-hoda-intro {
        display: grid;
        gap: .9rem;
        margin-top: 1.35rem;
        padding: 1rem 1.15rem;
        border: 1px solid rgba(59, 130, 246, .14);
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(59, 130, 246, .055), rgba(16, 185, 129, .055));
        box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
    }

    .home-hoda-intro-copy { min-width: 0; }
    .home-hoda-intro-title {
        margin: 0;
        color: var(--color-gentle-black);
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.6;
    }
    .home-hoda-intro-text {
        margin: .3rem 0 0;
        color: #64748b;
        font-size: .82rem;
        line-height: 1.85;
    }
    .home-hoda-intro-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
        align-items: center;
    }
    .home-hoda-intro-link,
    .home-hoda-chat-link {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        padding: .6rem .9rem;
        border-radius: 999px;
        font-size: .8rem;
        font-weight: 700;
        text-decoration: none;
    }
    .home-hoda-intro-link {
        color: var(--color-dark-blue);
        border: 1px solid rgba(59, 130, 246, .22);
        background: rgba(255, 255, 255, .84);
    }
    .home-hoda-chat-link {
        color: #fff;
        border: 0;
        background: linear-gradient(135deg, var(--color-earth-green), var(--color-dark-green));
    }

    .home-today-grid {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
    }

    .home-card-state[data-state='membership_fee_due'] {
        color: #92400e;
        background: rgba(245, 158, 11, .12);
    }

    body.dark-mode .home-hoda-intro {
        border-color: rgba(96, 165, 250, .18);
        background: linear-gradient(135deg, rgba(59, 130, 246, .09), rgba(16, 185, 129, .08));
    }
    body.dark-mode .home-hoda-intro-title { color: var(--text-dark); }
    body.dark-mode .home-hoda-intro-text { color: #94a3b8; }

    @media (min-width: 768px) {
        .home-hoda-intro {
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
        }
    }

    @media (max-width: 768px) {
        #najm-hoda-widget[data-route-name="home"] {
            bottom: calc(82px + env(safe-area-inset-bottom)) !important;
            right: 12px !important;
        }

        #najm-hoda-widget[data-route-name="home"] .najm-hoda-toggle-btn {
            width: 48px;
            height: 48px;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(55, 196, 180, .38);
        }

        .home-hoda-intro-actions {
            display: grid;
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        #najm-hoda-widget[data-route-name="home"] {
            bottom: calc(86px + env(safe-area-inset-bottom)) !important;
            right: 10px !important;
        }
    }
</style>

<template id="home-hoda-intro-template">
    <section class="home-hoda-intro" data-home-hoda-intro>
        <div class="home-hoda-intro-copy">
            <span class="home-eyebrow"><i class="fas fa-sparkles" aria-hidden="true"></i>همراه شما</span>
            <h2 class="home-hoda-intro-title">نجم هدا، همراه شما در ارث‌کوپ</h2>
            <p class="home-hoda-intro-text">هرجا درباره مسیر عضویت، گروه‌ها، حکمرانی یا کارهای خود سؤال داشتید، می‌توانید از نجم هدا راهنمایی بخواهید.</p>
        </div>
        <div class="home-hoda-intro-actions">
            <a href="{{ route('najm-hoda.profile') }}" class="home-hoda-intro-link"><i class="fas fa-circle-info" aria-hidden="true"></i>آشنایی با نجم هدا</a>
            <button type="button" class="home-hoda-chat-link" data-open-najm-hoda><i class="fas fa-comments" aria-hidden="true"></i>گفتگو با نجم هدا</button>
        </div>
    </section>
</template>

<script>
(function () {
    const installHomePolish = () => {
        const journey = document.querySelector('[data-home-journey]');
        const template = document.getElementById('home-hoda-intro-template');
        if (journey && template && !document.querySelector('[data-home-hoda-intro]')) {
            journey.insertAdjacentElement('afterend', template.content.firstElementChild.cloneNode(true));
        }

        document.querySelector('[data-open-najm-hoda]')?.addEventListener('click', () => {
            document.getElementById('najm-hoda-toggle')?.click();
        });

        document.querySelectorAll('[data-home-today-signals] .home-today-item').forEach((item) => {
            const value = Number.parseInt(item.querySelector('strong')?.textContent?.trim() || '0', 10);
            if (!Number.isFinite(value) || value <= 0) item.hidden = true;
        });

        const membershipCard = document.querySelector('[data-journey-status="membership_fee_due"]');
        if (membershipCard) {
            const state = membershipCard.querySelector('.home-card-state');
            const link = membershipCard.querySelector('.home-card-link');
            if (state) state.innerHTML = '<i class="fas fa-circle-exclamation" aria-hidden="true"></i>حق عضویت دوره جاری هنوز پرداخت نشده است';
            if (link) link.innerHTML = 'پرداخت حق عضویت <i class="fas fa-arrow-left" aria-hidden="true"></i>';
        }

        const nextActionFragment = @json(data_get($homeDashboard ?? [], 'next_action.fragment'));
        if (nextActionFragment) {
            const nextActionLink = document.querySelector('[data-home-next-action] a[href]');
            if (nextActionLink) nextActionLink.hash = nextActionFragment;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installHomePolish, { once: true });
    } else {
        installHomePolish();
    }
})();
</script>