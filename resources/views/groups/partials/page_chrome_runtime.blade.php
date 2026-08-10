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
        },
        showEditPollBox(pollId) {
            const editBox = document.getElementById('edit-poll-box-' + Number(pollId));
            if (!editBox) return;
            editBox.style.display = editBox.style.display === 'none' || editBox.style.display === ''
                ? 'block'
                : 'none';
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
        document.querySelectorAll('[id^="edit-poll-box-"]').forEach(editBox => {
            editBox.style.display = 'none';
        });
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
