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

    function setGroupHeroExpanded(expanded) {
        const hero = document.querySelector('[data-group-hero]');
        const trigger = hero?.querySelector('[data-group-chat-action="toggle-group-hero"]');
        const content = hero?.querySelector('[data-group-hero-content]');
        const chevron = hero?.querySelector('[data-group-hero-chevron]');
        if (!hero || !trigger || !content) return;

        trigger.setAttribute('aria-expanded', String(expanded));
        content.hidden = !expanded;
        content.classList.toggle('is-expanded', expanded);
        chevron?.classList.toggle('rotate-180', expanded);
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
        },
        toggleGroupHero() {
            const trigger = document.querySelector('[data-group-chat-action="toggle-group-hero"]');
            setGroupHeroExpanded(trigger?.getAttribute('aria-expanded') !== 'true');
        }
    });

    const pinnedMessages = document.querySelector('.pinned-messages');
    if (pinnedMessages) pinnedMessages.scrollTop = pinnedMessages.scrollHeight;

    @if (session()->has('success'))
    lifecycle.on(window, 'load', function() {
        window.GroupChatFeedback?.toast(@json(session()->get('success')), { type: 'success' });
    }, { once: true });
    @endif

    lifecycle.add(function() {
        setGroupEditVisible(false);
        setGroupHeroExpanded(false);
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
