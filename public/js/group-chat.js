// Add styles for chat features (inject once)
const existingRuntimeStyle = document.getElementById('group-chat-runtime-style');
const style = existingRuntimeStyle || document.createElement('style');
if (!existingRuntimeStyle) {
    style.id = 'group-chat-runtime-style';
}
style.textContent = `
    .chat-search-box {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        width: 80%;
        max-width: 500px;
    }

    .search-header {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }

    .search-header input {
        flex: 1;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .search-header button {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }

    .search-results {
        max-height: 300px;
        overflow-y: auto;
    }

    .report-box {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        width: 80%;
        max-width: 500px;
    }

    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .report-header button {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }

    .report-content {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .report-content select,
    .report-content textarea {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .report-content textarea {
        min-height: 100px;
        resize: vertical;
    }

    .report-content button {
        background: #007bff;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 4px;
        cursor: pointer;
    }

    .report-content button:hover {
        background: #0056b3;
    }
`;
if (!existingRuntimeStyle) {
    document.head.appendChild(style);
}

const legacyLifecycle = window.GroupChatLifecycle;
const groupChatDebug = Boolean(
    window.__groupChatDebug ||
    window.__chatPollingDebug ||
    (typeof window !== 'undefined' && window.localStorage && (
        window.localStorage.getItem('__groupChatDebug') === '1' ||
        window.localStorage.getItem('__chatPollingDebug') === '1'
    ))
);
const debugLog = (...args) => {
    if (groupChatDebug) {
        console.log(...args);
    }
};
const debugWarn = (...args) => {
    if (groupChatDebug) {
        console.warn(...args);
    }
};
const groupChatNotify = (message, type = 'info') => {
    if (window.GroupChatFeedback?.toast) return window.GroupChatFeedback.toast(message, { type });
    console[type === 'error' ? 'error' : 'info'](message);
};
const groupChatConfirm = (message, options = {}) => window.GroupChatFeedback?.confirm
    ? window.GroupChatFeedback.confirm(message, options)
    : Promise.resolve(false);
const groupChatPrompt = (message, options = {}) => window.GroupChatFeedback?.prompt
    ? window.GroupChatFeedback.prompt(message, options)
    : Promise.resolve(null);
// ========== FORCE LOG - همیشه نمایش بده ==========
// استفاده از alert برای اطمینان از نمایش
if (groupChatDebug && typeof window !== 'undefined') {
    debugLog('🔍🔍🔍 SCRIPT LOADED - VERSION 2024-12-19-v4 🔍🔍🔍');
    debugLog('🔍 window.groupId:', typeof window.groupId !== 'undefined' ? window.groupId : 'NOT DEFINED YET');
    debugLog('🔍 Current time:', new Date().toISOString());
    
    // تست: اگر بعد از 3 ثانیه console.log ها نمایش داده نشدند، alert نمایش بده
    legacyLifecycle.timeout(function() {
        if (typeof window.groupId !== 'undefined') {
            debugLog('✅✅✅ POLLING TEST: window.groupId is defined:', window.groupId);
        } else {
            debugWarn('❌❌❌ POLLING TEST: window.groupId is NOT defined!');
            // نمایش alert فقط برای debugging
            // Debug output is intentionally console-only.
        }
    }, 3000);
}
// ========== END FORCE LOG ==========

// Helper function to get CSRF token safely
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (!metaTag || !metaTag.content) {
        console.error('CSRF token meta tag not found!');
        return '';
    }
    return metaTag.content;
}

function generateClientMessageId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return 'cmid_' + Date.now() + '_' + Math.random().toString(16).slice(2);
}

function getOrCreateClientMessageIdInput(form) {
    let input = form.querySelector('input[name="client_message_id"]');

    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'client_message_id';
        form.appendChild(input);
    }

    if (!input.value) {
        input.value = generateClientMessageId();
    }

    return input;
}

async function groupChatFetch(input, init = {}) {
    if (!window.GroupChat?.api) throw new Error('GroupChat ApiClient is not ready');
    return window.GroupChat.api.request(input, init);
}
const feedBridge = window.GroupChat.installLegacyRenderers({
    renderMessage: appendMessage,
    updatePostFields: updatePostFieldsDom,
    updateLastPostCursor: id => window.GroupChat.realtimeRuntime?.advancePost(id),
});
const realtimeRuntime = window.GroupChat.installRealtime({ debug: groupChatDebug });
legacyLifecycle.on(document, 'DOMContentLoaded', function() {
    const chatBox = document.getElementById('chat-box');
    const form = document.getElementById('chatForm');
    const voiceFileInput = document.getElementById('voice-file-input');
    const voiceFilePreview = document.getElementById('voice-file-preview');
    const voiceFileName = document.getElementById('voice-file-name');
    const voiceFileSize = document.getElementById('voice-file-size');
    const voiceFileRemove = document.getElementById('voice-file-remove');

    // Format file size helper
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 بایت';
        const k = 1024;
        const sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    const updateVoiceFilePreview = (file) => {
        if (!voiceFilePreview) return;

        if (file) {
            if (voiceFileName) {
                voiceFileName.textContent = file.name;
            }
            if (voiceFileSize) {
                voiceFileSize.textContent = formatFileSize(file.size);
            }
            voiceFilePreview.style.display = 'flex';
            voiceFilePreview.style.setProperty('display', 'flex', 'important');
        } else {
            voiceFilePreview.style.display = 'none';
            voiceFilePreview.style.setProperty('display', 'none', 'important');
            if (voiceFileName) voiceFileName.textContent = '';
            if (voiceFileSize) voiceFileSize.textContent = '';
        }
    };

    if (voiceFileInput) legacyLifecycle.on(voiceFileInput, 'change', () => {
        updateVoiceFilePreview(voiceFileInput.files?.[0] || null);
    });

    if (voiceFileRemove) legacyLifecycle.on(voiceFileRemove, 'click', (event) => {
        event.preventDefault();
        if (voiceFileInput) {
            voiceFileInput.value = '';
        }
        updateVoiceFilePreview(null);
    });

    if (form) {
        legacyLifecycle.on(form, 'submit', async function(e) {
            e.preventDefault();
            debugLog('[TIMING] JS T0 - form submit started:', Date.now());

            const ckeditor = window.CKEDITOR;

            // Sync CKEditor قبل از خواندن محتوا
            if (ckeditor && ckeditor.instances) {
                for (const instance of Object.values(ckeditor.instances)) {
                    instance.updateElement();
                }
            }
            
            const formData = new FormData(form);
            const clientMessageIdInput = getOrCreateClientMessageIdInput(form);
            formData.set('client_message_id', clientMessageIdInput.value);
            const parentIdInput = document.getElementById('parent_id');
            const replyContainer = document.getElementById('reply-indicator-container');
            const parentId = parentIdInput ? String(parentIdInput.value || '').trim() : '';
            const isReplyUiActive = !!(
                replyContainer &&
                replyContainer.style.display !== 'none' &&
                replyContainer.innerHTML &&
                replyContainer.innerHTML.trim() !== ''
            );

            // Safety guard: never send hidden/stale parent_id values.
            if (parentId && isReplyUiActive) {
                formData.set('parent_id', parentId);
            } else {
                formData.delete('parent_id');
                if (parentIdInput) {
                    parentIdInput.value = '';
                }
            }

            // بررسی محتوای پیام قبل از ارسال
            const hasVoiceFile = voiceFileInput && voiceFileInput.files && voiceFileInput.files.length > 0;
            const messageEditor = ckeditor?.instances?.message_editor || null;
            let messageText = '';
            let messageHtml = '';
            
            if (messageEditor) {
                messageHtml = messageEditor.getData().trim();
                // تبدیل HTML به plain text با حفظ line breaks
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = messageHtml;
                // تبدیل <br> و <p> به line breaks
                const brs = tempDiv.querySelectorAll('br');
                brs.forEach(br => br.replaceWith('\n'));
                const ps = tempDiv.querySelectorAll('p, div');
                ps.forEach(p => {
                    if (p.nextSibling) {
                        p.appendChild(document.createTextNode('\n'));
                    }
                });
                messageText = tempDiv.textContent || tempDiv.innerText || '';
                messageText = messageText.trim();
            } else {
                const messageTextarea = document.getElementById('message_editor');
                if (messageTextarea) {
                    messageText = messageTextarea.value.trim();
                }
            }
            
            // تنظیم محتوا در formData (اگر CKEditor استفاده می‌شود، plain text را ارسال کن)
            if (messageEditor && messageText) {
                formData.set('message', messageText);
            }

            // اگر هم پیام خالی است و هم فایل صوتی نیست، از ارسال جلوگیری کن
            if (!messageText && !hasVoiceFile) {
                groupChatNotify('پیام نمی‌تواند خالی باشد.', 'error');
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // ذخیره موقعیت قبل از submit (اگر تابع موجود باشد)
            if (typeof saveScrollPositionBeforeSubmit === 'function') {
                saveScrollPositionBeforeSubmit();
            }

            // اگر فایل صوتی انتخاب شده و message خالی است، یک مقدار پیش‌فرض اضافه کن
            if (hasVoiceFile && !messageText) {
                if (messageEditor) {
                    messageEditor.setData('🎤 پیام صوتی');
                } else {
                    const messageTextarea = document.getElementById('message_editor');
                    if (messageTextarea) {
                        messageTextarea.value = '🎤 پیام صوتی';
                    }
                }
                formData.set('message', '🎤 پیام صوتی');
            }

            // ====== OPTIMISTIC UPDATE: نمایش فوری پیام قبل از پاسخ سرور ======
            debugLog('[TIMING] JS T1 - before optimistic update:', Date.now());
            const tempMsgId = 'temp_' + Date.now();
            const optimisticMsg = {
                id: tempMsgId,
                user_id: window.authUserId,
                sender: 'شما',
                message: messageHtml || messageText,
                created_at: new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }),
                reactions: [],
                parent_id: null,
                state: 'pending'
            };
            renderMessageThroughPipeline(optimisticMsg, 'optimistic');
            debugLog('[TIMING] JS T2 - after appendMessage (message should be visible NOW):', Date.now());
            // نمایش حالت «در حال ارسال» با کاهش شفافیت
            const tempMsgEl = document.getElementById('msg-' + tempMsgId);
            if (tempMsgEl) {
                tempMsgEl.dataset.deliveryState = 'pending';
                tempMsgEl.style.opacity = '0.65';
                const pendingBubble = tempMsgEl.querySelector('.message-bubble');
                if (pendingBubble) {
                    const existingReadReceipt = pendingBubble.querySelector('.read-receipt span');
                    if (existingReadReceipt) existingReadReceipt.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ارسال...';
                }
            }
            // اسکرول به پایین بعد از نمایش فوری
            const chatBoxScroll = document.getElementById('chat-box');
            if (chatBoxScroll) chatBoxScroll.scrollTop = chatBoxScroll.scrollHeight;
            // ====== END OPTIMISTIC UPDATE ======

            try {
                debugLog('[TIMING] JS T3 - before fetch (server request starting):', Date.now());
                
                // بررسی اینکه parent_id هنوز معتبره (پیام مرجع هنوز در DOM هست)
                const parentIdVal = document.getElementById('parent_id')?.value;
                if (parentIdVal && /^\d+$/.test(parentIdVal)) {
                    const parentMsgEl = document.getElementById('msg-' + parentIdVal);
                    if (!parentMsgEl) {
                        // پیام مرجع حذف شده - reply indicator رو پاک کن
                        window.GroupChat?.composer?.cancelReply();
                        formData.set('parent_id', '');
                    }
                }
                
                const response = await groupChatFetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server did not return JSON');
                }

                const responseData = await response.json();
                const serverTime = response.headers.get('X-Chat-Server-Time-Ms');
                debugLog('[TIMING] JS T4 - server responded:', Date.now(), 'status:', response.status, 'server_time_ms:', serverTime);

                if (!response.ok) {
                    if (response.status === 422) {
                        const errorMessage = responseData.message || 'خطا در اعتبارسنجی داده‌ها';
                        const errors = responseData.errors ? Object.values(responseData.errors).flat().join('\n') : '';
                        // حذف پیام موقت
                        const tempEl422 = document.getElementById('msg-' + tempMsgId);
                        if (tempEl422) tempEl422.remove();
                        groupChatNotify(`${errorMessage}\n${errors}`, 'error');
                        return;
                    } else if (response.status === 500) {
                        console.error('Server Error Details:', responseData);
                        // حذف پیام موقت
                        const tempEl500 = document.getElementById('msg-' + tempMsgId);
                        if (tempEl500) tempEl500.remove();
                        groupChatNotify('خطا در سرور. لطفاً دوباره تلاش کنید. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.', 'error');
                        return;
                    } else {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }
                
                if (responseData.status === 'success') {
                    // حذف پیام موقت و جایگزینی با پیام واقعی از سرور
                    const tempElSuccess = document.getElementById('msg-' + tempMsgId);
                    const nextSibling = tempElSuccess ? tempElSuccess.nextSibling : null;
                    const realMessageRow = renderMessageThroughPipeline(responseData.message, 'submit-response');
                    if (realMessageRow) realMessageRow.dataset.deliveryState = responseData.message.state || 'sent';
                    if (tempElSuccess && realMessageRow && realMessageRow.parentElement) {
                        realMessageRow.parentElement.insertBefore(realMessageRow, nextSibling);
                        tempElSuccess.remove();
                    }
                    // اسکرول به پایین بعد از دریافت پاسخ
                    const chatBoxFinal = document.getElementById('chat-box');
                    if (chatBoxFinal) chatBoxFinal.scrollTop = chatBoxFinal.scrollHeight;
                    realtimeRuntime.advanceMessage(responseData.message?.id);
                    form.reset();
                    clientMessageIdInput.value = '';
                    
                    // Clear voice file input and preview
                    if (voiceFileInput) {
                        voiceFileInput.value = '';
                    }
                    updateVoiceFilePreview(null);
                    
                    // Clear CKEditor if exists
                    const activeMessageEditor = ckeditor?.instances?.message_editor || null;
                    if (activeMessageEditor) {
                        activeMessageEditor.setData('');
                    }
                    
                    window.GroupChat?.composer?.cancelReply();
                } else {
                    // حذف پیام موقت در صورت خطا سرور
                    const tempElFail = document.getElementById('msg-' + tempMsgId);
                    if (tempElFail) tempElFail.remove();
                    groupChatNotify('خطا در ارسال پیام: ' + (responseData.message || 'خطای ناشناخته'), 'error');
                }
            } catch (error) {
                // حذف پیام موقت در صورت خطا شبکه
                const tempElCatch = document.getElementById('msg-' + tempMsgId);
                if (tempElCatch) tempElCatch.remove();
                console.error('Error:', error);
                if (error.message.includes('Failed to fetch')) {
                    groupChatNotify('خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'error');
                } else {
                    groupChatNotify('خطا در ارسال پیام. لطفاً دوباره تلاش کنید.', 'error');
                }
            }
        });
    }
    
    legacyLifecycle.timeout(() => {
        realtimeRuntime.initialize();
        realtimeRuntime.startPolling();
    }, 2000);
});

function renderMessageThroughPipeline(message, source = 'legacy') {
    if (window.GroupChat?.feed && window.GroupChat?.renderer?.supports('message')) {
        return window.GroupChat.feed.apply([{ ...message, content_type: 'message' }], source)[0] || null;
    }
    return appendMessage(message);
}

function appendMessage(message) {
    const chatBox = document.getElementById('chat-box');
    if (!chatBox) return;

    if (message && message.id) {
        const existing = document.getElementById('msg-' + message.id);
        if (existing) return existing;
    }

    const isMine = message.user_id == authUserId;
    const senderName = message.sender || 'کاربر';
    const initials = senderName.split(' ').map(n => n.charAt(0)).join(' ').trim() || '؟';
    // محتوا از backend با <br> برای line breaks می‌آید، مستقیماً استفاده می‌کنیم
    const messageContent = message.message || '';

    function renderMentionLinks(html) {
        if (!html || typeof html !== 'string') return '';
        if (/<a\b[^>]*class=["'][^"']*mention-link/i.test(html)) return html;
        const withBracketMentions = html.replace(/(^|[\s>])@\[([0-9]+)\]/g, function(match, prefix, userId) {
            return `${prefix}<a href="/profile-member/${userId}" class="mention-link" data-mention-user-id="${userId}">@${userId}</a>`;
        });
        return withBracketMentions.replace(/(^|[\s>])@([0-9]+)\b/g, function(match, prefix, userId) {
            return `${prefix}<a href="/profile-member/${userId}" class="mention-link" data-mention-user-id="${userId}">@${userId}</a>`;
        });
    }

    const messageContentHtml = renderMentionLinks(messageContent);
    const formattedTime = message.created_at || new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
    
    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Strip HTML helper
    function stripHtml(html) {
        const tmp = document.createElement('DIV');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }
    
    // Generate reactions HTML
    function generateReactionsHTML(messageId, reactions) {
        if (!reactions || reactions.length === 0) return '';
        const emojis = {
            like: '👍',
            love: '❤️',
            laugh: '😂',
            wow: '😮',
            sad: '😢',
            angry: '😠',
            '👍': '👍',
            '❤️': '❤️',
            '😂': '😂',
            '😮': '😮',
            '😢': '😢',
            '🔥': '🔥',
            '👎': '👎'
        };
        return `<div class="message-reactions" style="display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;">${reactions.map(r => {
            const type = r.type || r.reaction_type || '';
            const count = r.count || 0;
            const emoji = emojis[type] || type || '👍';
            return `<button type="button" class="reaction-badge" style="background:#f0f0f0;padding:2px 6px;border:0;border-radius:12px;font-size:12px;cursor:pointer;" data-legacy-chat-action="reaction" data-message-id="${messageId}" data-reaction-type="${type}">${emoji} ${count}</button>`;
        }).join('')}</div>`;
    }
    
    const messageRow = document.createElement('div');
    messageRow.className = `message-row ${isMine ? 'you' : 'other'}`;
    messageRow.setAttribute('data-message-id', message.id);
    messageRow.setAttribute('data-feed-item', 'true');
    messageRow.setAttribute('data-feed-type', 'message');
    messageRow.setAttribute('data-feed-id', message.id);
    messageRow.setAttribute('data-feed-author-id', message.user_id);
    messageRow.setAttribute('data-feed-unread', isMine ? '0' : '1');
    messageRow.id = `msg-${message.id}`;
    
    let messageHTML = '';
    
    // آواتار (فقط برای پیام‌های دیگران)
    if (!isMine) {
        messageHTML += `<a href="/profile/member/${message.user_id}" class="avatar-link"><span class="avatar"><span>${initials}</span></span></a>`;
    }
    
    // Reply preview
    let replyPreviewHTML = '';
    if (message.parent_id && message.parent_sender && message.parent_content) {
        replyPreviewHTML = `<div class="reply-preview"><div class="reply-sender">${escapeHtml(message.parent_sender)}</div><div class="reply-text">${escapeHtml(message.parent_content.substring(0, 80))}</div></div>`;
    }
    
    // Voice message
    let voiceMessageHTML = '';
    if (message.voice_message || message.voice_message_url) {
        // Prefer backend stream URL when available to avoid path/mime mismatches.
        let voiceUrl = message.voice_message_url || message.voice_message;
        if (!voiceUrl.startsWith('http://') && !voiceUrl.startsWith('https://')) {
            if (voiceUrl.startsWith('/messages/')) {
                // Dedicated voice stream endpoint; keep relative URL as-is.
            } else {
                let normalizedPath = voiceUrl.startsWith('/') ? voiceUrl.substring(1) : voiceUrl;

                // Avoid creating /storage/storage/... for already-prefixed paths.
                if (normalizedPath.startsWith('storage/')) {
                    normalizedPath = normalizedPath.substring('storage/'.length);
                }

                // Build full URL - encode each part separately to handle spaces.
                const pathParts = normalizedPath.split('/');
                const encodedParts = pathParts.map(part => encodeURIComponent(part));
                voiceUrl = window.location.origin + '/storage/' + encodedParts.join('/');
            }
        }
        let voiceType = String(message.file_type || 'audio/webm').toLowerCase();
        if (voiceType.includes('webm') || voiceType.includes('opus')) {
            voiceType = 'audio/webm';
        } else if (voiceType.includes('ogg')) {
            voiceType = 'audio/ogg';
        } else if (voiceType.includes('wav')) {
            voiceType = 'audio/wav';
        } else if (voiceType.includes('mp3') || voiceType.includes('mpeg')) {
            voiceType = 'audio/mpeg';
        } else if (!voiceType.startsWith('audio/')) {
            voiceType = 'audio/webm';
        }
        voiceMessageHTML = `<div class="voice-message-container" style="margin-top: 12px; padding: 12px; background: ${isMine ? '#e3f2fd' : '#f5f5f5'}; border-radius: 12px; border: 1px solid ${isMine ? '#90caf9' : '#e0e0e0'}; direction: ltr;"><div style="display: flex; align-items: center; gap: 12px;"><div style="width: 40px; height: 40px; border-radius: 50%; background: ${isMine ? '#2196f3' : '#757575'}; display: flex; align-items: center; justify-content: center; color: white;"><i class="fas fa-microphone"></i></div><div class="voice-message-content" style="flex: 1; min-width: 220px; width: 100%;"><div style="font-size: 12px; color: #666; margin-bottom: 4px;"><i class="fas fa-headphones"></i> پیام صوتی</div><audio class="voice-player" controls style="width: 100%;" preload="metadata" src="${voiceUrl}" type="${voiceType}">مرورگر شما از پخش صدا پشتیبانی نمی‌کند.</audio></div></div></div>`;
    }
    
    const hasVoiceMessage = Boolean(message.voice_message || message.voice_message_url);
    messageHTML += `
        <div class="message-bubble ${isMine ? 'you' : 'other'} ${hasVoiceMessage ? 'message-bubble--voice' : ''}" data-message-id="${message.id}" data-user-id="${message.user_id}" data-edit-url="/messages/${message.id}/edit" data-delete-url="/messages/${message.id}/delete" data-report-url="/messages/${message.id}/report" data-content-raw="${escapeHtml(stripHtml(messageContentHtml))}">
            <div class="message-head">
                ${isMine ? 
                    // برای پیام‌های خود کاربر: سه نقطه در سمت چپ، نام در سمت راست
                    `<div class="action-menu message-action" data-action-menu>
                        <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="action-menu__list">
                            <button type="button" data-legacy-chat-action="reply" data-message-id="${message.id}" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                            <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                            ${([2,3].includes(window.yourRole || 0)) ? `<button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="${message.id}"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                            <button type="button" class="action-menu__item btn-edit"><i class="fas fa-edit"></i> ویرایش</button>
                            <button type="button" data-group-chat-action="delete-message" data-message-id="${message.id}" class="action-menu__item action-menu__item--danger btn-delete"><i class="fas fa-trash"></i> حذف</button>
                            <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                        </div>
                    </div>
                    <div class="message-head__info">
                        <span class="message-sender message-sender--self">شما</span>
                    </div>` :
                    // برای پیام‌های دیگران: نام کاربر در سمت چپ، سه نقطه در سمت راست
                    `<div class="message-head__info">
                        <a href="/profile-member/${message.user_id}" class="message-sender">${escapeHtml(senderName)}</a>
                    </div>
                    <div class="action-menu message-action" data-action-menu>
                        <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="action-menu__list">
                            <button type="button" data-legacy-chat-action="reply" data-message-id="${message.id}" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                            <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                            ${([2,3].includes(window.yourRole || 0)) ? `<button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="${message.id}"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                            <button type="button" data-group-chat-action="report-message" data-message-id="${message.id}" class="action-menu__item btn-report"><i class="fas fa-flag"></i> گزارش</button>
                            <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                        </div>
                    </div>`
                }
            </div>
            ${replyPreviewHTML}
            <p class="message-content">${messageContentHtml}</p>
            ${voiceMessageHTML}
            <div class="message-timestamp" style="display: flex !important; align-items: center; gap: 6px; margin-top: 4px; flex-wrap: wrap; margin-left: -10px !important; margin-right: -10px !important; padding-left: 10px !important; padding-right: 10px !important; justify-content: space-between !important; float: none !important; text-align: left !important; direction: ltr !important;">
                ${isMine ? '<div class="read-receipt" style="font-size: 10px; text-align: left; direction: ltr; margin-right: auto; margin-left: 0;"><span style="color: #9ca3af;"><i class="fas fa-check"></i> ارسال شده</span></div>' : ''}
                <div style="display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center;">
                    ${(message.reactions && message.reactions.length > 0) ? generateReactionsHTML(message.id, message.reactions) : ''}
                </div>
                <div style="display: flex; align-items: center; gap: 4px; margin-left: auto;">
                    <span class="message-time">${formattedTime}</span>
                </div>
            </div>
        </div>
    `;
    
    messageRow.innerHTML = messageHTML;
    const typingIndicator = document.getElementById('group-typing-indicator');
    if (typingIndicator && typingIndicator.parentElement === chatBox) {
        chatBox.insertBefore(messageRow, typingIndicator);
    } else {
        chatBox.appendChild(messageRow);
    }
    
    // اضافه کردن Thread button به پیام جدید (مثل پیام‌های Blade-rendered)
    const newBubble = messageRow.querySelector('[data-message-id]');
    if (newBubble && typeof window.addThreadButton === 'function') {
        window.addThreadButton(newBubble);
    }
    
    // Initialize reaction button for this message
    if (typeof addReactionButton === 'function') {
        const messageBubble = messageRow.querySelector('.message-bubble');
        if (messageBubble) {
            addReactionButton(message.id);
        }
    }
    
    // Scroll to the bottom of the chat - فقط اگر scroll restore کامل شده باشد
    // و کاربر خودش به پایین رفته باشد
    // در غیر این صورت، scroll restore خودش موقعیت را تنظیم می‌کند
    // این کد حذف شد چون با scroll restore تداخل دارد
    return messageRow;
}

// ✅ NEW: Helper to update blog UI without page reload
function updatePostFieldsDom(blogData) {
    try {
        const blogElement = document.getElementById(`blog-${blogData.id}`);
        if (!blogElement) {
            console.warn('Blog element not found:', blogData.id);
            return false;
        }
        
        // Update blog content
        const titleEl = blogElement.querySelector('.blog-title');
        const contentEl = blogElement.querySelector('.blog-content');
        
        if (titleEl) titleEl.textContent = blogData.title || '';
        if (contentEl) contentEl.innerHTML = blogData.content || '';
        
        // Update edit timestamp if exists
        const timeEl = blogElement.querySelector('.blog-edit-time');
        if (timeEl && blogData.updated_at) {
            timeEl.textContent = `(ویرایش شده: ${blogData.updated_at})`;
        }
        
        console.log('Blog updated successfully:', blogData.id);
        return true;
    } catch (error) {
        console.error('Error updating blog UI:', error);
        return false;
    }
}

// ✅ NEW: Alert helper
function showSuccessAlert(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'موفقیت‌آمیز',
            text: message,
            timer: 1500,
            showConfirmButton: false
        });
    } else {
        groupChatNotify(message, 'error');
    }
}

function closeAllModals() {
  window.GroupChat?.elections?.close();
  window.GroupChat?.composer?.closePost();
  window.GroupChat?.composer?.closePoll();
  window.GroupChat?.skillLists?.close();
  window.GroupChat?.elections?.closeAdmin();
}

// // Add click handlers for file upload buttons
// document.getElementById('file-upload').addEventListener('change', function(e) {
//     if (this.files.length > 0) {
//         document.getElementById('chatForm').submit();
//     }
// });

function editMessage(messageId, currentContent) {
    // Hide all other edit forms
    document.querySelectorAll('.edit-form').forEach(form => {
        form.style.display = 'none';
    });
    
    // Show the edit form for this message
    const editForm = document.getElementById(`edit-form-${messageId}`);
    const editInput = document.getElementById(`edit-message-${messageId}`);
    
    if (editForm && editInput) {
        editForm.style.display = 'block';
        editInput.focus();
    }
}

function cancelEdit(messageId) {
    const editForm = document.getElementById(`edit-form-${messageId}`);
    if (editForm) {
        editForm.style.display = 'none';
    }
}
function submitEdit(event, messageId) {
    event.preventDefault();
    

    const newContent = document.getElementById(`edit-message-${messageId}`).value;
    
    $.ajax({
        url: `/messages/${messageId}/edit`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken()
        },
        data: {
            content: newContent
        },
        success: function(response) {
            if (response.status === 'success') {
                // Update the message content
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                const messageContent = messageElement.querySelector('.message-bubble p');
                if (messageContent) {
                    messageContent.textContent = newContent;
                }
                
                // Add edited badge if it doesn't exist
                if (!messageElement.querySelector('.edited-badge')) {
                    const editedBadge = document.createElement('span');
                    editedBadge.className = 'edited-badge';
                    editedBadge.textContent = 'ویرایش شده';
                    messageContent.parentNode.insertBefore(editedBadge, messageContent.nextSibling);
                }
                
                // Hide the edit form
                document.getElementById(`edit-form-${messageId}`).style.display = 'none';
                
                // Show success message
                groupChatNotify('پیام با موفقیت ویرایش شد', 'success');
            } else {
                groupChatNotify(response.message || 'خطا در ویرایش پیام', 'error');
            }
        },
        error: function() {
            groupChatNotify('خطا در ارتباط با سرور', 'error');
        }
    });
}

async function deleteMessage(messageId) {
    const bubble = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
    const deleteUrl = bubble?.dataset.deleteUrl;
    if (!bubble || !deleteUrl) {
        groupChatNotify('پیام موردنظر پیدا نشد.', 'error');
        return;
    }

    if (!await groupChatConfirm('آیا از حذف پیام مطمئن هستید؟', { confirmText: 'حذف' })) return;

    try {
        const response = await groupChatFetch(deleteUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || (data.status && data.status !== 'success')) {
            groupChatNotify(data.message || `خطا در حذف پیام (status ${response.status})`, 'error');
            return;
        }

        const row = bubble.closest('.message-row') || document.getElementById(`msg-${messageId}`);
        if (row) {
            row.style.transition = 'opacity 0.3s ease-out';
            row.style.opacity = '0';
            window.setTimeout(() => row.remove(), 300);
        }
        const parentInput = document.getElementById('parent_id');
        if (parentInput?.value == messageId) window.GroupChat?.composer?.cancelReply();
    } catch (error) {
        console.error('Error deleting message:', error);
        groupChatNotify('خطا در ارتباط با سرور', 'error');
    }
}

async function reportMessage(messageId) {
    const reason = await groupChatPrompt('لطفاً دلیل گزارش این پیام را وارد کنید:');
    if (reason) {
        fetch(`/groups/messages/${messageId}/report`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                groupChatNotify('پیام با موفقیت گزارش شد.', 'success');
            } else {
                groupChatNotify('خطا در گزارش پیام.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            groupChatNotify('خطا در گزارش پیام.', 'error');
        });
    }
}

async function deletePost(postId) {
    if (await groupChatConfirm('آیا از حذف این پست اطمینان دارید؟', { confirmText: 'حذف' })) {
        fetch(`/blog/${postId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'success') {
                feedBridge.mutate('post', 'delete', { id: postId }, 'local-post-delete');
            } else {
                groupChatNotify(data.message || 'خطا در حذف پست', 'error');
            }
        })
        .catch(function() { groupChatNotify('خطا در ارتباط با سرور', 'error'); });
    }
}

function editPost(postId, currentTitle, currentContent, currentCategoryId) {
    // Hide all other edit forms
    document.querySelectorAll('.post-edit-form').forEach(form => {
        form.style.display = 'none';
    });
    
    // Show the edit form for this post
    const editForm = document.getElementById(`post-edit-form-${postId}`);
    const titleInput = document.getElementById(`edit-post-title-${postId}`);
    const contentInput = document.getElementById(`edit-post-content-${postId}`);
    const categorySelect = document.getElementById(`edit-post-category-${postId}`);
    
    if (editForm && titleInput && contentInput && categorySelect) {
        editForm.style.display = 'block';
        titleInput.value = currentTitle;
        contentInput.value = currentContent;
        categorySelect.value = currentCategoryId;
        titleInput.focus();
    }
}

function cancelPostEdit(postId) {
    const editForm = document.getElementById(`post-edit-form-${postId}`);
    if (editForm) {
        editForm.style.display = 'none';
    }
}

async function submitPostEdit(event, postId) {
    event.preventDefault();
    
    const title = document.getElementById(`edit-post-title-${postId}`).value;
    const content = document.getElementById(`edit-post-content-${postId}`).value;
    const categoryId = document.getElementById(`edit-post-category-${postId}`).value;
    
    // Validate required fields
    if (!title.trim()) {
        groupChatNotify('لطفاً عنوان پست را وارد کنید', 'error');
        return;
    }
    if (!content.trim()) {
        groupChatNotify('لطفاً محتوای پست را وارد کنید', 'error');
        return;
    }
    if (!categoryId) {
        groupChatNotify('لطفاً دسته‌بندی را انتخاب کنید', 'error');
        return;
    }
    
    try {
        const response = await groupChatFetch(`/blog/${postId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                title: title,
                content: content,
                category_id: categoryId
            })
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422) {
                const errors = data.errors;
                let errorMessage = '';
                for (const field in errors) {
                    errorMessage += errors[field].join('\n') + '\n';
                }
                console.log(errorMessage)
            } else {
                throw new Error(data.message || 'خطا در ویرایش پست');
            }
            return;
        }

        if (data.status === 'success') {
            // Close Bootstrap modal properly BEFORE replacing DOM
            var bsModalEl = document.getElementById('editPostModal-' + postId);
            if (bsModalEl) {
                if (window.bootstrap && bootstrap.Modal) {
                    var bsInst = bootstrap.Modal.getInstance(bsModalEl);
                    if (bsInst) bsInst.hide();
                }
            }
            // Fallback: force-remove backdrop & restore body
            legacyLifecycle.timeout(function() {
                document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }, 350);
            const updatedPost = data.post?.html
                ? { ...data.post, id: data.post.id || postId }
                : data.blog;
            if (updatedPost) {
                feedBridge.mutate('post', 'update', updatedPost, 'local-post-edit');
            }
            showSuccessAlert('پست با موفقیت ویرایش شد');
        } else {
            groupChatNotify(data.message || 'خطا در ویرایش پست', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        groupChatNotify('خطا در ارتباط با سرور', 'error');
    }
}

async function clearChatHistory() {
    if (await groupChatConfirm('آیا از پاک کردن تاریخچه چت اطمینان دارید؟', { confirmText: 'پاک کردن' })) {
        fetch(`/api/groups/${groupId}/clear-history`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('chat-box').innerHTML = '';
                groupChatNotify('تاریخچه چت با موفقیت پاک شد', 'success');
            } else {
                groupChatNotify('خطا در پاک کردن تاریخچه چت', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            groupChatNotify('خطا در پاک کردن تاریخچه چت', 'error');
        });
    }
}

async function deleteChat() {
    if (await groupChatConfirm('آیا از حذف این چت اطمینان دارید؟ این عمل غیرقابل بازگشت است.', { confirmText: 'حذف چت' })) {
        fetch(`/api/groups/${groupId}/delete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/groups';
            } else {
                groupChatNotify('خطا در حذف چت', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            groupChatNotify('خطا در حذف چت', 'error');
        });
    }
}

function reportUser() {
    const reportBox = document.createElement('div');
    reportBox.className = 'report-box';
    reportBox.innerHTML = `
        <div class="report-header">
            <h3>گزارش کاربر</h3>
            <button type="button" data-legacy-chat-action="close-report">×</button>
        </div>
        <div class="report-content">
            <select id="reportReason">
                <option value="spam">اسپم</option>
                <option value="harassment">آزار و اذیت</option>
                <option value="inappropriate">محتوا نامناسب</option>
                <option value="other">سایر</option>
            </select>
            <textarea id="reportDescription" placeholder="توضیحات بیشتر..."></textarea>
            <button type="button" data-legacy-chat-action="submit-report">ارسال گزارش</button>
        </div>
    `;
    document.body.appendChild(reportBox);
}

function closeReportBox() {
    const reportBox = document.querySelector('.report-box');
    if (reportBox) {
        reportBox.remove();
    }
}

function submitReport() {
    const reason = document.getElementById('reportReason').value;
    const description = document.getElementById('reportDescription').value;
    
    fetch(`/api/groups/${groupId}/report`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            reason: reason,
            description: description
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            groupChatNotify('گزارش با موفقیت ارسال شد', 'success');
            closeReportBox();
        } else {
            groupChatNotify('خطا در ارسال گزارش', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        groupChatNotify('خطا در ارسال گزارش', 'error');
    });
}

