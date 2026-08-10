<script type="module">
function initializeGroupChatScrollManager() {
    'use strict';

    const groupId = {{ $group->id }};
    const chatBox = document.getElementById('chat-box');
    const btn = document.getElementById('scroll-toggle-btn');
    const authUserIdForUnread = Number(window.authUserId || 0);
    const scrollKey = 'chatScroll_' + groupId;
    const initialLastReadMessageId = Number({{ $lastReadMessageId ?? 'null' }});
    const unreadCountUrl = @json(route('groups.unread-count', $group));
    const lifecycle = window.GroupChatLifecycle;
    let unreadCount = Number(@json($unreadContentCounts['total'] ?? 0));
    let isRenderingUnreadIndicators = false;
    let unreadRenderTimer = null;
    let unreadRefreshTimer = null;

    window.GroupChat.store.setState({ scrollRestored: false });

    if (!chatBox || !btn || !lifecycle || lifecycle.destroyed) {
        window.GroupChat.store.setState({ scrollRestored: true });
        return;
    }

    btn.style.right = '24px';
    btn.style.left = 'auto';
    btn.style.bottom = '96px';

    btn.innerHTML = '<i class="fas fa-arrow-down"></i>';

    function getEffectiveLastReadMessageId() {
        const liveValue = Number(window.GroupChat.store.getState().lastReadMessageId);
        if (Number.isFinite(liveValue) && liveValue > 0) return liveValue;

        if (Number.isFinite(initialLastReadMessageId) && initialLastReadMessageId > 0) {
            return initialLastReadMessageId;
        }

        return null;
    }

    function getMessageRows() {
        // Keep backward compatibility: some messages may expose id on row, bubble, or wrapper.
        const candidates = Array.from(chatBox.querySelectorAll('.message-row[data-message-id], [id^="msg-"][data-message-id], .message-bubble[data-message-id]'));
        const unique = new Map();

        candidates.forEach(function(node) {
            const id = Number(node.getAttribute('data-message-id'));
            if (!Number.isFinite(id)) return;
            if (unique.has(id)) return;

            // Prefer top-level row wrapper if available.
            const wrapper = node.closest('.message-row[data-message-id]') || node.closest('[id^="msg-"][data-message-id]') || node;
            unique.set(id, wrapper);
        });

        return Array.from(unique.values()).sort(function(a, b) {
            const aid = Number(a.getAttribute('data-message-id'));
            const bid = Number(b.getAttribute('data-message-id'));
            return aid - bid;
        });
    }

    function getFeedRows() {
        return Array.from(chatBox.querySelectorAll('[data-feed-item="true"]'));
    }

    function getMessageId(node) {
        const id = Number(node?.getAttribute('data-message-id'));
        return Number.isFinite(id) ? id : null;
    }

    function getMessageUserId(node) {
        if (!node) return null;

        const direct = Number(node.getAttribute('data-user-id'));
        if (Number.isFinite(direct)) return direct;

        const nested = node.querySelector('[data-user-id]');
        if (!nested) return null;

        const nestedId = Number(nested.getAttribute('data-user-id'));
        return Number.isFinite(nestedId) ? nestedId : null;
    }

    function getMessageElementForDivider(node) {
        if (!node) return null;
        return node.closest('.message-row') || node.closest('[id^="msg-"]') || node;
    }

    function scrollNodeToTop(node) {
        if (!node) return;
        const top = node.offsetTop;
        chatBox.scrollTop = Math.max(0, top - 12);
    }

    function focusUnreadTarget(node) {
        if (!node) return;

        // Layout can still expand after first paint (images/audio/fonts).
        // Retry a few times so initial position lands on first unread reliably.
        const delays = [0, 80, 220, 520, 1100, 1800];
        delays.forEach(function(delay) {
            lifecycle.timeout(function() {
                if (!node.isConnected) return;

                try {
                    node.scrollIntoView({ block: 'start', inline: 'nearest', behavior: 'auto' });
                } catch (e) {
                    scrollNodeToTop(node);
                    return;
                }

                chatBox.scrollTop = Math.max(0, chatBox.scrollTop - 12);
            }, delay);
        });
    }

    function findFirstUnreadMessage() {
        const rows = getFeedRows();
        for (const row of rows) {
            const authorId = Number(row.dataset.feedAuthorId || 0);
            const isOwnContent = authUserIdForUnread > 0 && authorId === authUserIdForUnread;
            if (row.dataset.feedUnread === '1' && !isOwnContent) {
                return getMessageElementForDivider(row);
            }
        }

        return null;
    }

    function getUnreadRows() {
        return getFeedRows().filter(function(row) {
            const authorId = Number(row.dataset.feedAuthorId || 0);
            const isOwnContent = authUserIdForUnread > 0 && authorId === authUserIdForUnread;
            return row.dataset.feedUnread === '1' && !isOwnContent;
        });
    }

    function isNearBottom() {
        const threshold = 72;
        return (chatBox.scrollHeight - chatBox.clientHeight - chatBox.scrollTop) <= threshold;
    }

    function scrollToLatest(smooth) {
        chatBox.scrollTo({
            top: chatBox.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }

    function updateScrollButtonVisibility() {
        const shouldShow = unreadCount > 0 || !isNearBottom();
        btn.classList.toggle('visible', shouldShow);
    }

    function getLastVisibleMessageIdInViewport() {
        const rows = getMessageRows();
        if (!rows.length) return null;

        const boxRect = chatBox.getBoundingClientRect();
        for (let i = rows.length - 1; i >= 0; i--) {
            const row = rows[i];
            const rect = row.getBoundingClientRect();
            const intersects = rect.bottom > boxRect.top && rect.top < boxRect.bottom;
            if (!intersects) continue;

            const id = getMessageId(row);
            if (id) return id;
        }

        return null;
    }

    function getOrCreateUnreadBadge() {
        let badge = document.getElementById('chat-unread-badge');
        if (badge) return badge;

        badge = document.createElement('span');
        badge.id = 'chat-unread-badge';
        badge.style.cssText = [
            'position:absolute',
            'top:-8px',
            'left:-8px',
            'min-width:20px',
            'height:20px',
            'padding:0 6px',
            'border-radius:999px',
            'background:#ef4444',
            'color:#fff',
            'font-size:11px',
            'font-weight:700',
            'display:none',
            'align-items:center',
            'justify-content:center',
            'line-height:1',
            'box-shadow:0 4px 10px rgba(0,0,0,.2)'
        ].join(';');

        btn.style.position = 'fixed';
        btn.appendChild(badge);
        return badge;
    }

    function removeUnreadDivider() {
        const existing = document.getElementById('chat-unread-divider');
        if (existing) existing.remove();
    }

    function upsertUnreadDivider(beforeRow, count) {
        if (!beforeRow || !beforeRow.parentElement) {
            removeUnreadDivider();
            return;
        }

        const anchorId = String(getMessageId(beforeRow) || '');
        let divider = document.getElementById('chat-unread-divider');

        if (!divider) {
            divider = document.createElement('div');
            divider.id = 'chat-unread-divider';
            divider.style.cssText = [
                'display:flex',
                'align-items:center',
                'gap:10px',
                'margin:10px 0 14px',
                'color:#0f766e',
                'font-size:12px',
                'font-weight:700'
            ].join(';');
            divider.innerHTML = '<span style="flex:1;height:1px;background:rgba(15,118,110,.25)"></span><span class="chat-unread-divider__label"></span><span style="flex:1;height:1px;background:rgba(15,118,110,.25)"></span>';
        }

        const label = divider.querySelector('.chat-unread-divider__label');
        if (label) {
            label.textContent = 'پیام‌های خوانده‌نشده (' + count + ')';
        }

        divider.dataset.anchorId = anchorId;
        divider.dataset.count = String(count);

        if (divider.nextSibling !== beforeRow || divider.parentElement !== beforeRow.parentElement) {
            beforeRow.parentElement.insertBefore(divider, beforeRow);
        }
    }

    function renderUnreadIndicators() {
        if (isRenderingUnreadIndicators) return;
        isRenderingUnreadIndicators = true;

        const badge = getOrCreateUnreadBadge();
        const unreadRows = getUnreadRows();

        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            badge.style.display = 'inline-flex';
            upsertUnreadDivider(unreadRows[0], unreadCount);
        } else {
            badge.style.display = 'none';
            removeUnreadDivider();
        }

        isRenderingUnreadIndicators = false;
    }

    async function refreshUnreadCount() {
        try {
            const response = await fetch(unreadCountUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!response.ok) return;

            const data = await response.json();
            const total = Number(data?.unread?.total);
            if (Number.isFinite(total) && total >= 0) {
                unreadCount = total;
                renderUnreadIndicators();
                updateScrollButtonVisibility();
            }
        } catch (error) {
            // Polling and realtime continue; retry on the next scheduled refresh.
        }
    }

    function scheduleUnreadRefresh(delay = 120) {
        if (unreadRefreshTimer !== null) lifecycle.clearTimeout(unreadRefreshTimer);
        unreadRefreshTimer = lifecycle.timeout(refreshUnreadCount, delay);
    }

    function getLastMessageId() {
        const rows = getMessageRows();
        if (rows.length === 0) return null;
        return getMessageId(rows[rows.length - 1]);
    }

    function restoreInitialPosition() {
        const unreadTarget = findFirstUnreadMessage();
        const effectiveLastReadMessageId = getEffectiveLastReadMessageId();
        const hasReadBefore = Number.isFinite(effectiveLastReadMessageId) && effectiveLastReadMessageId > 0;
        renderUnreadIndicators();

        if (!hasReadBefore) {
            // اولین ورود واقعی (بدون سابقه خواندن): از ابتدای پیام‌ها
            removeUnreadDivider();
            chatBox.scrollTop = 0;
        } else if (unreadTarget) {
            // کاربر قبلا پیام خوانده و unread دارد: باز شدن روی اولین پیام خوانده‌نشده
            sessionStorage.removeItem(scrollKey);
            focusUnreadTarget(unreadTarget);
        } else {
            // کاربر قبلا پیام‌ها را دیده و پیام نخوانده‌ای ندارد: مستقیم آخرین پیام
            sessionStorage.removeItem(scrollKey);
            removeUnreadDivider();
            scrollToLatest(false);

            const lastId = getLastMessageId();
            if (lastId) window.GroupChat.unread.updateLastMessage(lastId);
        }

        window.GroupChat.store.setState({ scrollRestored: true });
        updateScrollButtonVisibility();
    }

    // چند بار بازیابی را تکرار می‌کنیم تا بعد از mount شدن کامل DOM دقیق بنشیند.
    [0, 220, 620, 1200, 2000].forEach(delay => lifecycle.timeout(restoreInitialPosition, delay));

    let saveTimer = null;
    lifecycle.on(chatBox, 'scroll', function() {
        updateScrollButtonVisibility();

        if (saveTimer !== null) lifecycle.clearTimeout(saveTimer);
        saveTimer = lifecycle.timeout(function() {
            sessionStorage.setItem(scrollKey, String(chatBox.scrollTop));

            const lastVisibleId = getLastVisibleMessageIdInViewport();
            if (lastVisibleId) window.GroupChat.unread.updateLastMessage(lastVisibleId);
        }, 180);
    }, { passive: true });

    lifecycle.on(btn, 'click', function(e) {
        e.preventDefault();
        scrollToLatest(true);
    });

    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            updateScrollButtonVisibility();

            if (unreadRenderTimer !== null) lifecycle.clearTimeout(unreadRenderTimer);
            unreadRenderTimer = lifecycle.timeout(function() {
                renderUnreadIndicators();
            }, 80);

            const feedChanged = mutations.some(function(mutation) {
                return Array.from(mutation.addedNodes).some(function(node) {
                    return node instanceof HTMLElement && (
                        node.matches?.('[data-feed-item="true"]') ||
                        node.querySelector?.('[data-feed-item="true"]')
                    );
                });
            });
            if (feedChanged) scheduleUnreadRefresh();
        });
        observer.observe(chatBox, { childList: true, subtree: false });
        lifecycle.add(() => observer.disconnect());
    }

    lifecycle.on(window, 'group-chat:last-read-updated', function() {
        renderUnreadIndicators();
        updateScrollButtonVisibility();
    });

    lifecycle.on(window, 'group-feed:read-state-changed', function() {
        scheduleUnreadRefresh(50);
    });

    lifecycle.timeout(refreshUnreadCount, 250);
    lifecycle.interval(refreshUnreadCount, 15000);

    if (typeof addReactionButton === 'function') {
        document.querySelectorAll('.message-bubble').forEach(b => {
            if (b.dataset.messageId) addReactionButton(b);
        });
    }
}

initializeGroupChatScrollManager();
</script>
