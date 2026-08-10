<script>
function initializeGroupChatPageChrome() {
    const lifecycle = window.GroupChatLifecycle;
    if (!lifecycle || lifecycle.destroyed || window.__groupChatPageChromeInitialized) return;
    window.__groupChatPageChromeInitialized = true;

    const groupEditForm = document.getElementById('groupEditFormBox');
    const backdrop = document.getElementById('back');

    function setGroupEditVisible(visible) {
        if (groupEditForm) groupEditForm.style.display = visible ? 'block' : 'none';
        if (backdrop) backdrop.style.display = visible ? 'block' : 'none';
    }

    window.GroupChatPageChrome = Object.freeze({
        openGroupEdit() {
            setGroupEditVisible(true);
        },
        cancelGroupEdit() {
            setGroupEditVisible(false);
        }
    });

    const pinnedMessages = document.querySelector('.pinned-messages');
    if (pinnedMessages) pinnedMessages.scrollTop = pinnedMessages.scrollHeight;

    @if (session()->has('success'))
    lifecycle.on(window, 'load', function() {
        window.groupChatNotify(@json(session()->get('success')), 'success');
    }, { once: true });
    @endif

    lifecycle.add(function() {
        setGroupEditVisible(false);
        delete window.GroupChatPageChrome;
        window.__groupChatPageChromeInitialized = false;
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeGroupChatPageChrome, { once: true });
} else {
    initializeGroupChatPageChrome();
}
</script>
