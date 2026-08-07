const NAJM_HODA_API = '/api/najm-hoda';
const ACTIVE_CONVERSATION_KEY = 'najm-hoda-active-conversation-id';

function authHeaders() {
    const token = localStorage.getItem('api_token') || '';
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
    };
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function safeFormatMessage(content) {
    return escapeHtml(content)
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');
}

function pageContext() {
    const widget = document.getElementById('najm-hoda-widget');
    const body = document.body;
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const numericPart = [...pathParts].reverse().find((part) => /^\d+$/.test(part));

    // Keep browser-supplied context deliberately narrow. Free-form page text/title/path
    // is not promoted into model context; resource details can later be resolved server-side.
    return {
        route_name: widget?.dataset?.routeName || body?.dataset?.routeName || null,
        module: widget?.dataset?.module || body?.dataset?.module || pathParts[0] || 'home',
        resource_id: body?.dataset?.resourceId || numericPart || null,
    };
}

async function apiFetch(path, options = {}) {
    const response = await fetch(`${NAJM_HODA_API}${path}`, {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...authHeaders(),
            ...(options.headers || {}),
        },
    });

    if (response.status === 401 || response.status === 403) {
        localStorage.removeItem(ACTIVE_CONVERSATION_KEY);
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(data.message || 'Najm Hoda API request failed');
        error.status = response.status;
        error.payload = data;
        throw error;
    }

    return data;
}

function clearRenderedMessages() {
    const messages = document.getElementById('najm-hoda-messages');
    if (messages) messages.innerHTML = '';
}

function installSafeRenderer(widget) {
    widget.formatMessage = safeFormatMessage;
    widget.addMessage = function addSafeMessage(content, role, icon) {
        const messagesDiv = document.getElementById('najm-hoda-messages');
        if (!messagesDiv) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `najm-hoda-message ${role === 'user' ? 'user' : 'assistant'}`;

        const avatar = document.createElement('div');
        avatar.className = 'najm-hoda-message-avatar';
        avatar.textContent = String(icon || (role === 'user' ? '👤' : '🤖'));

        const messageContent = document.createElement('div');
        messageContent.className = 'najm-hoda-message-content';
        messageContent.innerHTML = safeFormatMessage(content);

        messageDiv.append(avatar, messageContent);
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    };
}

async function loadConversation(widget, conversationId) {
    const data = await apiFetch(`/conversations/${conversationId}`);
    const conversation = data?.conversation;
    if (!conversation || !Array.isArray(conversation.messages)) return false;

    widget.conversationId = Number(conversation.id);
    localStorage.setItem(ACTIVE_CONVERSATION_KEY, String(conversation.id));
    clearRenderedMessages();

    conversation.messages.forEach((message) => {
        const role = message.role === 'user' ? 'user' : 'assistant';
        widget.addMessage(message.content || '', role, role === 'user' ? '👤' : '🤖');
    });

    return true;
}

async function restoreConversation(widget) {
    let conversationId = Number(localStorage.getItem(ACTIVE_CONVERSATION_KEY) || 0) || null;

    if (!conversationId) {
        try {
            const list = await apiFetch('/conversations?status=active&per_page=1');
            conversationId = Number(list?.conversations?.[0]?.id || 0) || null;
        } catch (error) {
            if (![401, 403].includes(error.status)) {
                console.warn('Najm Hoda conversation list could not be restored:', error);
            }
            return;
        }
    }

    if (!conversationId) return;

    try {
        await loadConversation(widget, conversationId);
    } catch (error) {
        if (error.status === 404) {
            localStorage.removeItem(ACTIVE_CONVERSATION_KEY);
            widget.conversationId = null;
            return;
        }

        if (![401, 403].includes(error.status)) {
            console.warn('Najm Hoda conversation could not be restored:', error);
        }
    }
}

function installEnhancedSend(widget) {
    widget.sendMessage = async function sendMessageWithContext() {
        const input = document.getElementById('najm-hoda-input');
        const message = input?.value?.trim() || '';

        if (!message || this.isTyping) return;

        this.addMessage(message, 'user', '👤');
        input.value = '';
        this.showTyping();

        try {
            const agent = document.getElementById('najm-hoda-agent')?.value || 'auto';
            const data = await apiFetch('/chat', {
                method: 'POST',
                body: JSON.stringify({
                    message,
                    agent,
                    conversation_id: this.conversationId,
                    context: {
                        page: pageContext(),
                    },
                }),
            });

            this.hideTyping();

            if (data.success) {
                this.conversationId = Number(data.conversation_id) || null;
                if (this.conversationId) {
                    localStorage.setItem(ACTIVE_CONVERSATION_KEY, String(this.conversationId));
                }
                this.addMessage(data.message, 'assistant', data.agent_icon || '🤖');
                if (Array.isArray(data.suggestions) && data.suggestions.length > 0) {
                    this.showSuggestions(data.suggestions);
                }
                return;
            }

            this.addMessage(data.message || 'خطایی رخ داد', 'assistant', '⚠️');
        } catch (error) {
            this.hideTyping();
            this.addMessage(error.payload?.message || 'متأسفانه مشکلی پیش آمد. لطفاً دوباره تلاش کنید.', 'assistant', '❌');
            console.error('خطا در ارسال پیام نجم هدا:', error);
        }
    };
}

function makeButton(label, title) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = label;
    button.title = title;
    Object.assign(button.style, {
        border: '1px solid rgba(255,255,255,.4)',
        background: 'rgba(255,255,255,.12)',
        color: '#fff',
        borderRadius: '8px',
        padding: '5px 9px',
        fontSize: '12px',
        cursor: 'pointer',
    });
    return button;
}

function ensureHistoryUi(widget) {
    const header = document.querySelector('#najm-hoda-chat-container .najm-hoda-header');
    const container = document.getElementById('najm-hoda-chat-container');
    if (!header || !container || document.getElementById('najm-hoda-history-panel')) return;

    const controls = document.createElement('div');
    controls.style.cssText = 'display:flex;gap:6px;margin-top:8px;direction:rtl;';

    const historyButton = makeButton('🕘 تاریخچه', 'نمایش گفتگوهای قبلی');
    const newChatButton = makeButton('＋ گفتگوی جدید', 'شروع گفتگوی جدید');
    controls.append(historyButton, newChatButton);
    header.appendChild(controls);

    const panel = document.createElement('div');
    panel.id = 'najm-hoda-history-panel';
    panel.hidden = true;
    panel.style.cssText = [
        'position:absolute', 'inset:0', 'z-index:5', 'background:#fff', 'direction:rtl',
        'display:flex', 'flex-direction:column', 'color:#263238'
    ].join(';');

    const panelHeader = document.createElement('div');
    panelHeader.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#37c4b4;color:#fff;';
    const title = document.createElement('strong');
    title.textContent = 'تاریخچه گفتگوهای نجم هدا';
    const close = makeButton('✕', 'بستن تاریخچه');
    panelHeader.append(title, close);

    const list = document.createElement('div');
    list.id = 'najm-hoda-history-list';
    list.style.cssText = 'overflow:auto;padding:12px;flex:1;background:#f7fafb;';

    panel.append(panelHeader, list);
    container.appendChild(panel);

    const closePanel = () => { panel.hidden = true; };
    close.addEventListener('click', closePanel);

    newChatButton.addEventListener('click', () => {
        widget.conversationId = null;
        localStorage.removeItem(ACTIVE_CONVERSATION_KEY);
        clearRenderedMessages();
        widget.addMessage('گفتگوی جدید آماده است. چه کمکی از دستم برمی‌آید؟', 'assistant', '🌟');
        closePanel();
    });

    historyButton.addEventListener('click', async () => {
        panel.hidden = false;
        list.textContent = 'در حال بارگذاری...';
        try {
            const [active, archived] = await Promise.all([
                apiFetch('/conversations?status=active&per_page=20'),
                apiFetch('/conversations?status=archived&per_page=20'),
            ]);
            renderHistoryList(widget, list, [
                ...(active.conversations || []),
                ...(archived.conversations || []),
            ], closePanel);
        } catch (error) {
            list.textContent = [401, 403].includes(error.status)
                ? 'برای مشاهده تاریخچه باید وارد حساب کاربری شوید.'
                : 'بارگذاری تاریخچه با خطا مواجه شد.';
        }
    });
}

function renderHistoryList(widget, list, conversations, closePanel) {
    list.innerHTML = '';
    if (!conversations.length) {
        list.textContent = 'هنوز گفتگویی ذخیره نشده است.';
        return;
    }

    conversations.forEach((conversation) => {
        const item = document.createElement('div');
        item.style.cssText = 'background:#fff;border:1px solid #e3ecef;border-radius:10px;padding:10px;margin-bottom:8px;';

        const row = document.createElement('div');
        row.style.cssText = 'display:flex;justify-content:space-between;gap:8px;align-items:flex-start;';

        const open = document.createElement('button');
        open.type = 'button';
        open.style.cssText = 'border:0;background:transparent;text-align:right;flex:1;cursor:pointer;color:#263238;padding:0;';
        const title = document.createElement('strong');
        title.textContent = conversation.title || 'بدون عنوان';
        const preview = document.createElement('div');
        preview.style.cssText = 'font-size:11px;color:#6b7b83;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
        preview.textContent = conversation.last_message || (conversation.status === 'archived' ? 'آرشیو شده' : 'گفتگوی فعال');
        open.append(title, preview);

        open.addEventListener('click', async () => {
            try {
                await loadConversation(widget, Number(conversation.id));
                closePanel();
            } catch (error) {
                console.warn('Najm Hoda history item could not be loaded:', error);
            }
        });

        row.appendChild(open);

        if (conversation.status === 'active') {
            const archive = document.createElement('button');
            archive.type = 'button';
            archive.textContent = 'آرشیو';
            archive.style.cssText = 'border:1px solid #d7e2e6;background:#fff;border-radius:7px;padding:4px 7px;font-size:11px;cursor:pointer;';
            archive.addEventListener('click', async () => {
                try {
                    await apiFetch(`/conversations/${conversation.id}/archive`, { method: 'PUT' });
                    if (Number(widget.conversationId) === Number(conversation.id)) {
                        widget.conversationId = null;
                        localStorage.removeItem(ACTIVE_CONVERSATION_KEY);
                    }
                    item.remove();
                } catch (error) {
                    console.warn('Najm Hoda conversation could not be archived:', error);
                }
            });
            row.appendChild(archive);
        }

        item.appendChild(row);
        list.appendChild(item);
    });
}

function enhanceWidget(widget) {
    if (!widget || widget.__continuityInstalled) return;
    widget.__continuityInstalled = true;

    installSafeRenderer(widget);
    widget.getPageContext = pageContext;
    installEnhancedSend(widget);
    ensureHistoryUi(widget);
    restoreConversation(widget);
}

function waitForWidget() {
    if (window.NajmHoda) {
        enhanceWidget(window.NajmHoda);
        return;
    }

    let attempts = 0;
    const timer = window.setInterval(() => {
        attempts += 1;
        if (window.NajmHoda) {
            window.clearInterval(timer);
            enhanceWidget(window.NajmHoda);
        } else if (attempts >= 100) {
            window.clearInterval(timer);
        }
    }, 50);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', waitForWidget, { once: true });
} else {
    waitForWidget();
}
