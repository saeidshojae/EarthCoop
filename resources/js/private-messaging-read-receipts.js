const root = document.querySelector('[data-private-conversation]');

if (root) {
    const conversationId = Number(root.dataset.conversationId || 0);
    const infoUrl = `/private-chats/${conversationId}/info`;
    const messagesUrl = `/private-chats/${conversationId}/messages`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let channel = null;
    let pollingTimer = null;

    const markReceiptRead = (messageId) => {
        const receipt = document.querySelector(`[data-read-receipt][data-message-id="${messageId}"]`);
        if (!receipt) return;
        receipt.classList.add('is-read');
        receipt.textContent = '✓✓';
        receipt.setAttribute('aria-label', 'خوانده شده');
    };

    const markReceiptsThrough = (lastReadMessageId) => {
        const maxId = Number(lastReadMessageId || 0);
        if (!maxId) return;

        document.querySelectorAll('[data-read-receipt][data-message-id]').forEach((receipt) => {
            const messageId = Number(receipt.dataset.messageId || 0);
            if (messageId && messageId <= maxId) {
                markReceiptRead(messageId);
            }
        });
    };

    const syncReceiptState = async () => {
        if (!conversationId || document.hidden) return;

        try {
            const response = await fetch(infoUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) return;
            const payload = await response.json();
            markReceiptsThrough(payload?.conversation?.last_read_outgoing_message_id);
        } catch (error) {
            console.warn('Private messaging read receipt sync failed:', error);
        }
    };

    const acknowledgeForegroundMessage = async (messageId) => {
        if (!conversationId || !messageId || document.hidden) return;

        try {
            await fetch(`${messagesUrl}?after_id=${Math.max(0, Number(messageId) - 1)}&limit=1`, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
        } catch (error) {
            console.warn('Private messaging foreground read acknowledgement failed:', error);
        }
    };

    const subscribeRealtime = () => {
        if (!window.Echo || !conversationId) return;

        try {
            channel = window.Echo.private(`private-chat.${conversationId}`);

            channel.listen('.private-messages.read', (event) => {
                if (!event || Number(event.conversation_id) !== conversationId) return;
                (event.message_ids || []).forEach(markReceiptRead);
            });

            channel.listen('.private-message.created', (event) => {
                const messageId = event?.message?.id;
                if (!messageId || document.hidden) return;
                // The server only marks messages whose sender is not the current
                // participant, so acknowledging our own broadcast is harmless.
                acknowledgeForegroundMessage(messageId);
            });
        } catch (error) {
            console.warn('Private messaging read receipt realtime subscription failed:', error);
        }
    };

    const startPollingFallback = () => {
        if (pollingTimer || !conversationId) return;
        pollingTimer = window.setInterval(syncReceiptState, 5000);
    };

    subscribeRealtime();
    syncReceiptState();
    startPollingFallback();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) syncReceiptState();
    });

    window.addEventListener('beforeunload', () => {
        if (pollingTimer) window.clearInterval(pollingTimer);
        if (channel && window.Echo && typeof window.Echo.leave === 'function') {
            window.Echo.leave(`private-chat.${conversationId}`);
        }
    });
}
