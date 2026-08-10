<script>
(function initializeActionMenuDismissal() {
    'use strict';

    if (window.__groupChatActionMenuDismissalInitialized) return;
    const lifecycle = window.GroupChatLifecycle;
    if (!lifecycle || lifecycle.destroyed) return;
    window.__groupChatActionMenuDismissalInitialized = true;

    lifecycle.on(document, 'click', function(event) {
        document.querySelectorAll('details.menu-wrapper[open]').forEach(function(menu) {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    lifecycle.on(document, 'keydown', function(event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('details.menu-wrapper[open]').forEach(function(menu) {
            menu.removeAttribute('open');
        });
    });
})();
</script>
