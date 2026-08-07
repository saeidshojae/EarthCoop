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
    const body = document.body;
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const numericPart = [...pathParts].reverse().find((part) => /^\d+$/.test(part));

    return {
        route_name: body?.dataset?.routeName || null,
        module: body?.dataset?.module || pathParts[0] || 'home',
        resource_type: body?.dataset?.resourceType || null,
        resource_id: body?.dataset?.resourceId || numericPart || null,
        page_title: document.title || null,
        path: window.location.pathname,
        locale: document.documentElement.lang || null,
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
        const data = await apiFetch(`/conversations/${conversationId}`);
        const conversation = data?.conversation;
        if (!conversation || !Array.isArray(conversation.messages)) return;

        widget.conversationId = Number(conversation.id);
        localStorage.setItem(ACTIVE_CONVERSATION_KEY, String(conversation.id));
        clearRenderedMessages();

        conversation.messages.forEach((message) => {
            const role = message.role === 'user' ? 'user' : 'assistant';
            const icon = role === 'user' ? '👤' : '🤖';
            widget.addMessage(message.content || '', role, icon);
        });
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

function enhanceWidget(widget) {
    if (!widget || widget.__continuityInstalled) return;
    widget.__continuityInstalled = true;

    // Never render model/user supplied HTML directly.
    widget.formatMessage = safeFormatMessage;
    widget.getPageContext = pageContext;
    installEnhancedSend(widget);

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
