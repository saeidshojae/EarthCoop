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
        <img class="earthcoop-pwa-splash__logo" src="{{ asset('icons/icon.svg') }}?v=5" alt="">
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
