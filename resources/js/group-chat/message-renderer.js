export function renderMessage(message) {
    const chatBox = document.getElementById('chat-box');
    if (!chatBox) return;

    const messageId = message?.id ?? message?.content_id ?? message?.message_id;
    const userId = message?.user_id ?? message?.actor_id ?? message?.user?.id;
    if (messageId == null || messageId === '' || messageId === 'undefined'
        || userId == null || userId === '' || userId === 'undefined') return false;
    message = { ...message, id: messageId, user_id: userId };

    if (message && message.id) {
        const existing = document.getElementById('msg-' + message.id);
        if (existing) return existing;
    }

    const isMine = message.user_id == window.authUserId;
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
    const editedAt = message.edited_at
        ? new Date(message.edited_at).toLocaleString('fa-IR')
        : (message.edited ? new Date().toLocaleString('fa-IR') : '');
    const menuTimeHtml = `<div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" aria-hidden="true"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div>${editedAt ? `<div class="menu-meta-time__item menu-meta-time__item--edited"><i class="fas fa-edit" aria-hidden="true"></i><span class="menu-meta-time__label">ویرایش شده:</span><span class="menu-meta-time__value">${editedAt}</span></div>` : ''}</div>`;
    
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
                            ${menuTimeHtml}
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
                            ${menuTimeHtml}
                        </div>
                    </div>`
                }
            </div>
            ${replyPreviewHTML}
            <p class="message-content">${messageContentHtml}</p>
            ${voiceMessageHTML}
            <div class="message-timestamp" style="display: flex !important; align-items: center; gap: 6px; margin-top: 4px; flex-wrap: wrap; margin-left: -10px !important; margin-right: -10px !important; padding-left: 10px !important; padding-right: 10px !important; justify-content: space-between !important; float: none !important; text-align: left !important; direction: ltr !important;">
                <div class="message-reactions-slot" style="display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center;">
                    ${(message.reactions && message.reactions.length > 0) ? generateReactionsHTML(message.id, message.reactions) : ''}
                </div>
                <div class="message-primary-meta" style="display: flex; align-items: center; gap: 4px; margin-left: auto;">
                    <span class="message-time">${formattedTime}</span>
                </div>
                ${message.edited ? '<span class="edited-icon message-edit-status">(ویرایش شده)</span>' : ''}
                ${isMine ? '<div class="read-receipt" style="font-size: 10px; text-align: left; direction: ltr;"><span style="color: #9ca3af;"><i class="fas fa-check"></i> ارسال شده</span></div>' : ''}
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
    if (typeof window.addReactionButton === 'function') {
        const messageBubble = messageRow.querySelector('.message-bubble');
        if (messageBubble) {
            window.addReactionButton(message.id);
        }
    }
    
    // Scroll to the bottom of the chat - فقط اگر scroll restore کامل شده باشد
    // و کاربر خودش به پایین رفته باشد
    // در غیر این صورت، scroll restore خودش موقعیت را تنظیم می‌کند
    // این کد حذف شد چون با scroll restore تداخل دارد
    return messageRow;
}
