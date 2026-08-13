<style>
    #earthcoop-pwa-splash { position: fixed; inset: 0; z-index: 2147483647; display: none; place-items: center; background: #fff; opacity: 1; transition: opacity .45s ease, visibility .45s ease; }
    #earthcoop-pwa-splash.is-active { display: grid; }
    #earthcoop-pwa-splash.is-leaving { opacity: 0; visibility: hidden; }
    .earthcoop-pwa-splash__brand { display: flex; flex-direction: column; align-items: center; gap: 1.5rem; color: #1e293b; font-family: 'Vazirmatn', sans-serif; font-size: clamp(2rem, 7vw, 3rem); font-weight: 800; line-height: 1; }
    .earthcoop-pwa-splash__logo { width: clamp(8.5rem, 30vw, 12rem); height: auto; animation: earthcoop-pwa-splash-float 2.6s ease-in-out infinite; filter: drop-shadow(0 18px 24px rgba(4, 120, 87, .2)); will-change: transform; }
    @keyframes earthcoop-pwa-splash-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
    @media (prefers-reduced-motion: reduce) { .earthcoop-pwa-splash__logo { animation: none; } }
</style>
<div id="earthcoop-pwa-splash" aria-hidden="true">
    <div class="earthcoop-pwa-splash__brand">
        <svg class="earthcoop-pwa-splash__logo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2Z" fill="#10b981" opacity="0.8"/>
            <path d="M12 2C10.5 4 8 6 8 9C8 12 12 14 12 14C12 14 16 12 16 9C16 6 13.5 4 12 2ZM12 14C12 14 10 16 10 18C10 20 12 22 12 22" fill="#047857"/>
        </svg>
        <span>EarthCoop</span>
    </div>
</div>
<script>
    (() => {
        const splash = document.getElementById('earthcoop-pwa-splash');
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true || new URLSearchParams(window.location.search).get('source') === 'pwa';
        if (!splash || !isStandalone) return;
        const startedAt = Date.now();
        splash.classList.add('is-active');
        window.addEventListener('load', () => {
            window.setTimeout(() => {
                splash.classList.add('is-leaving');
                window.setTimeout(() => splash.remove(), 500);
            }, Math.max(0, 900 - (Date.now() - startedAt)));
        }, { once: true });
    })();
</script>
