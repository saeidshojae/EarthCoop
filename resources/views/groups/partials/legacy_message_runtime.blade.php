
    <script>
    const chatBox = document.getElementById('chat-box');
    const STORAGE_KEY = 'chatScroll_{{ $group->id }}';
    const GROUP_ID = {{ $group->id }};
    const LAST_READ_MESSAGE_ID = {{ $lastReadMessageId ?? 'null' }};
    const UPDATE_LAST_READ_URL = '{{ route("groups.messages.updateLastRead", $group->id) }}';
    const CURRENT_USER_ROLE = {{ $roleValue ?? 0 }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        '{{ csrf_token() }}';

    // جلوگیری از رفتار پیش‌فرض مرورگر
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    // تابع برای به‌روزرسانی last_read_message_id
    let lastReadUpdateTimeout = null;
    let currentLastReadMessageId = Number.isFinite(Number(LAST_READ_MESSAGE_ID)) ? Number(LAST_READ_MESSAGE_ID) : null;
    window.lastReadMessageIdState = currentLastReadMessageId;

    function setLastReadState(messageId, notify = true) {
        const parsedId = Number(messageId);
        if (!Number.isFinite(parsedId) || parsedId <= 0) return;

        if (!Number.isFinite(currentLastReadMessageId) || parsedId > currentLastReadMessageId) {
            currentLastReadMessageId = parsedId;
            window.lastReadMessageIdState = parsedId;

            if (notify) {
                window.dispatchEvent(new CustomEvent('group-chat:last-read-updated', {
                    detail: {
                        messageId: parsedId
                    }
                }));
            }
        }
    }

    function updateLastReadMessage(messageId) {
        const parsedId = Number(messageId);
        if (!Number.isFinite(parsedId) || parsedId <= 0) return;
        if (Number.isFinite(currentLastReadMessageId) && parsedId <= currentLastReadMessageId) return;

        // Optimistic local update to immediately refresh unread visuals.
        setLastReadState(parsedId, true);

        // Debounce: فقط آخرین پیام visible را به‌روزرسانی کن
        clearTimeout(lastReadUpdateTimeout);
        lastReadUpdateTimeout = setTimeout(() => {
            fetch(UPDATE_LAST_READ_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message_id: parsedId
                })
            }).then((res) => {
                if (!res.ok) throw new Error('updateLastReadMessage failed with status ' + res.status);
            }).catch(err => console.error('Error updating last read message:', err));
        }, 500); // 500ms debounce
    }

    // تابع برای پیدا کردن آخرین پیام visible در viewport
    function getLastVisibleMessageId() {
        const messages = chatBox.querySelectorAll('[data-message-id]');
        let lastVisibleId = null;

        for (let i = messages.length - 1; i >= 0; i--) {
            const msg = messages[i];
            const rect = msg.getBoundingClientRect();
            const chatBoxRect = chatBox.getBoundingClientRect();

            // بررسی اینکه آیا پیام در viewport قرار دارد
            if (rect.top >= chatBoxRect.top && rect.bottom <= chatBoxRect.bottom) {
                const messageId = parseInt(msg.getAttribute('data-message-id'));
                if (messageId && !isNaN(messageId)) {
                    lastVisibleId = messageId;
                    break;
                }
            }
        }

        return lastVisibleId;
    }

    // تابع برای ذخیره موقعیت قبل از submit فرم
    function saveScrollPositionBeforeSubmit() {
        const lastVisibleId = getLastVisibleMessageId();
        if (lastVisibleId) {
            updateLastReadMessage(lastVisibleId);
        }
        sessionStorage.setItem(STORAGE_KEY, chatBox.scrollTop);
    }

    // Event listener برای submit فرم در group-chat.js تعریف شده است
    // این کد حذف شد تا از تداخل event listenerها جلوگیری شود

    // تابع برای اضافه کردن پیام جدید به chat بدون reload
    function addMessageToChat(messageData) {
        try {
            const chatBox = document.getElementById('chat-box');
            if (!chatBox || !messageData) {
                console.error('Chat box or message data not found');
                return;
            }

            // بررسی کن که پیام معتبر باشد و خالی نباشد
            if (!messageData.id || !messageData.message || (typeof messageData.message === 'string' && messageData
                    .message.trim() === '')) {
                console.warn('Invalid or empty message data:', messageData);
                return;
            }

            // بررسی کن که آیا این پیام قبلاً اضافه شده یا نه
            const existingMessage = document.getElementById(`msg-${messageData.id}`);
            if (existingMessage) {
                console.warn('Message already exists:', messageData.id);
                return;
            }

            const isMine = messageData.user_id == {{ auth()->id() }};
            const messageRow = document.createElement('div');
            messageRow.className = `message-row ${isMine ? 'you' : 'other'}`;
            messageRow.setAttribute('data-message-id', messageData.id);
            messageRow.id = `msg-${messageData.id}`;

            // ساخت HTML پیام
            const senderName = messageData.sender || 'کاربر';
            const initials = senderName.split(' ').map(n => n.charAt(0)).join(' ').trim() || '؟';
            const messageContent = messageData.message || '';
            const formattedTime = messageData.created_at || new Date().toLocaleTimeString('fa-IR', {
                hour: '2-digit',
                minute: '2-digit'
            });

            let messageHTML = '';

            // آواتار (فقط برای پیام‌های دیگران)
            if (!isMine) {
                messageHTML +=
                    `<a href="/profile/member/${messageData.user_id}" class="avatar-link"><span class="avatar"><span>${initials}</span></span></a>`;
            }

            // Reply preview
            let replyPreviewHTML = '';
            if (messageData.parent_id && messageData.parent_sender && messageData.parent_content) {
                replyPreviewHTML =
                    `<div class="reply-preview"><div class="reply-sender">${escapeHtml(messageData.parent_sender)}</div><div class="reply-text">${escapeHtml(messageData.parent_content.substring(0, 80))}</div></div>`;
            }

            // Voice message
            let voiceMessageHTML = '';
            if (messageData.voice_message || messageData.voice_message_url) {
                // Convert relative path to full URL if needed
                let voiceUrl = messageData.voice_message_url || messageData.voice_message;
                let voiceType = String(messageData.file_type || 'audio/webm').toLowerCase();
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
                if (!voiceUrl.startsWith('http://') && !voiceUrl.startsWith('https://')) {
                    if (voiceUrl.startsWith('/messages/')) {
                        // Dedicated voice stream endpoint; keep relative URL as-is.
                    } else {
                        // Remove leading slash if exists
                        voiceUrl = voiceUrl.startsWith('/') ? voiceUrl.substring(1) : voiceUrl;
                        // Build full URL - encode each part separately to handle spaces
                        const pathParts = voiceUrl.split('/');
                        const encodedParts = pathParts.map(part => encodeURIComponent(part));
                        voiceUrl = window.location.origin + '/storage/' + encodedParts.join('/');
                    }
                }
                voiceMessageHTML =
                    `<div class="voice-message-container" style="margin-top: 12px; padding: 12px; background: ${isMine ? '#e3f2fd' : '#f5f5f5'}; border-radius: 12px; border: 1px solid ${isMine ? '#90caf9' : '#e0e0e0'}; direction: ltr;"><div style="display: flex; align-items: center; gap: 12px;"><div style="width: 40px; height: 40px; border-radius: 50%; background: ${isMine ? '#2196f3' : '#757575'}; display: flex; align-items: center; justify-content: center; color: white;"><i class="fas fa-microphone"></i></div><div class="voice-message-content" style="flex: 1; min-width: 220px; width: 100%;"><div style="font-size: 12px; color: #666; margin-bottom: 4px;"><i class="fas fa-headphones"></i> پیام صوتی</div><audio class="voice-player" controls style="width: 100%;" preload="metadata" src="${voiceUrl}" type="${voiceType}">مرورگر شما از پخش صدا پشتیبانی نمی‌کند.</audio></div></div></div>`;
            }

            const hasVoiceMessage = Boolean(messageData.voice_message || messageData.voice_message_url);
            messageHTML += `
            <div class="message-bubble ${isMine ? 'you' : 'other'} ${hasVoiceMessage ? 'message-bubble--voice' : ''}" data-message-id="${messageData.id}" data-user-id="${messageData.user_id}" data-edit-url="/messages/${messageData.id}/edit" data-delete-url="/messages/${messageData.id}/delete" data-report-url="/messages/${messageData.id}/report" data-content-raw="${escapeHtml(stripHtml(messageContent))}">
                <div class="message-head">
                    ${isMine ?
                        // برای پیام‌های خود کاربر: سه نقطه در سمت چپ، نام در سمت راست
                        `<div class="action-menu message-action" data-action-menu>
                            <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="action-menu__list">
                                <button type="button" data-legacy-chat-action="reply" data-message-id="${messageData.id}" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                                <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                                ${([2,3].includes(CURRENT_USER_ROLE)) ? `<button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="${messageData.id}"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                                <button type="button" class="action-menu__item btn-edit"><i class="fas fa-edit"></i> ویرایش</button>
                                <button type="button" data-group-chat-action="delete-message" data-message-id="${messageData.id}" class="action-menu__item action-menu__item--danger btn-delete"><i class="fas fa-trash"></i> حذف</button>
                                <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                            </div>
                        </div>
                        <div class="message-head__info">
                            <span class="message-sender message-sender--self">شما</span>
                        </div>` :
                        // برای پیام‌های دیگران: نام کاربر در سمت چپ، سه نقطه در سمت راست
                        `<div class="message-head__info">
                            <a href="/profile-member/${messageData.user_id}" class="message-sender">${escapeHtml(senderName)}</a>
                        </div>
                        <div class="action-menu message-action" data-action-menu>
                            <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                            <div class="action-menu__list">
                                <button type="button" data-legacy-chat-action="reply" data-message-id="${messageData.id}" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                                <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                                ${([2,3].includes(CURRENT_USER_ROLE)) ? `<button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="${messageData.id}"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                                <button type="button" data-group-chat-action="report-message" data-message-id="${messageData.id}" class="action-menu__item btn-report"><i class="fas fa-flag"></i> گزارش</button>
                                <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                            </div>
                        </div>`
                    }
                </div>
                ${replyPreviewHTML}
                <p class="message-content">${messageContent}</p>
                <div class="message-timestamp"><span class="message-time">${formattedTime}</span></div>
                ${voiceMessageHTML}
                ${isMine ? '<div class="read-receipt" style="font-size: 10px; margin-top: 4px; text-align: left; direction: ltr;"><span style="color: #9ca3af;"><i class="fas fa-check"></i> ارسال شده</span></div>' : ''}
            </div>
        `;

            messageRow.innerHTML = messageHTML;
            chatBox.appendChild(messageRow);

            // اضافه کردن Thread button
            const newMsgBubble = messageRow.querySelector('[data-message-id]');
            if (newMsgBubble && typeof window.addThreadButton === 'function') {
                window.addThreadButton(newMsgBubble);
            }

            // Initialize click handler for profile link
            const profileLink = messageRow.querySelector('a.message-sender');
            if (profileLink) {
                profileLink.addEventListener('click', function(e) {
                    // اجازه بده لینک کار کند
                    e.stopPropagation();
                    e.preventDefault();
                    // اگر href وجود دارد، به آن برو
                    const href = this.getAttribute('href');
                    if (href && !href.includes('#')) {
                        window.location.href = href;
                    }
                });
            }

        } catch (error) {
            console.error('Error in addMessageToChat:', error);
            // ✅ FIXED: No location.reload() - gracefully handle error without disrupting user
            console.warn('Could not add message to chat, but continuing without reload');
            // Don't reload - let the polling or next action recover the state
        }
    }

    // تابع برای به‌روزرسانی محتوای پیام بعد از ویرایش
    function updateMessageContent(messageBubble, newContent, isEdited) {
        try {
            if (!messageBubble) {
                console.error('updateMessageContent: messageBubble is null');
                throw new Error('messageBubble is null');
            }

            const contentElement = messageBubble.querySelector('.message-content');
            if (!contentElement) {
                console.error('updateMessageContent: .message-content element not found');
                console.error('Message bubble:', messageBubble);
                console.error('Message bubble HTML:', messageBubble.outerHTML);
                throw new Error('.message-content element not found');
            }

            // پاک کردن محتوای قبلی (برای جلوگیری از تکرار)
            // ابتدا همه child elements را حذف کن
            while (contentElement.firstChild) {
                contentElement.removeChild(contentElement.firstChild);
            }
            contentElement.innerHTML = '';

            // تبدیل محتوا به HTML با حفظ line breaks
            // محتوا از backend به صورت plain text می‌آید (با \n برای line breaks)
            // باید line breaks را به <br> تبدیل کنیم و HTML را escape کنیم
            let htmlContent = '';

            // بررسی اینکه آیا محتوا HTML است یا نه
            // اگر شامل تگ‌های HTML معتبر باشد (نه فقط < یا &)، HTML است
            const hasHtmlTags = /<[a-z][\s\S]*>/i.test(newContent);

            if (hasHtmlTags) {
                // محتوا HTML است، مستقیماً استفاده کن (اما باید اطمینان حاصل کنیم که safe است)
                htmlContent = newContent;
            } else {
                // محتوا plain text است، escape کن و line breaks را به <br> تبدیل کن
                htmlContent = nl2br(newContent);
            }

            // به‌روزرسانی محتوا
            contentElement.innerHTML = htmlContent;
            console.log('Content updated in DOM');

            // به‌روزرسانی data-content-raw (بدون HTML)
            const rawContent = stripHtml(htmlContent);
            messageBubble.setAttribute('data-content-raw', escapeHtml(rawContent));
            console.log('data-content-raw updated');

            // اضافه کردن آیکون ویرایش شده
            if (isEdited) {
                const timestampElement = messageBubble.querySelector('.message-timestamp');
                if (timestampElement) {
                    // اگر آیکون ویرایش وجود ندارد، اضافه کن
                    let editedIcon = timestampElement.querySelector('.message-edited');
                    if (!editedIcon) {
                        editedIcon = document.createElement('span');
                        editedIcon.className = 'message-edited';
                        editedIcon.innerHTML = '<i class="fas fa-edit"></i>';
                        timestampElement.appendChild(editedIcon);
                        console.log('Edited icon added');
                    } else {
                        console.log('Edited icon already exists');
                    }
                } else {
                    console.warn('Timestamp element not found, cannot add edited icon');
                }
            }

            console.log('Message content updated successfully');
        } catch (error) {
            console.error('Error in updateMessageContent:', error);
            console.error('Error stack:', error.stack);
            // خطا را throw کن تا caller بتواند آن را handle کند
            throw error;
        }
    }

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function stripHtml(html) {
        if (!html) return '';
        const tmp = document.createElement('DIV');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    // تبدیل line breaks به <br> برای نمایش درست
    function nl2br(text) {
        if (!text) return '';
        // Escape HTML first
        const escaped = escapeHtml(text);
        // Convert \n to <br>
        return escaped.replace(/\n/g, '<br>');
    }

    // Legacy scroll/read listeners were removed.
    // The unified manager at the end of this Blade handles scroll state,
    // unread indicators, and debounced updates to avoid observer loops.


    (function() {
        function openCatModal() {
            $('#categoryBlogsOverlay').fadeIn(120);
            $('#categoryBlogsModal').fadeIn(120);
            $('body').css('overflow', 'hidden');
        }

        function closeCatModal() {
            $('#categoryBlogsModal').fadeOut(120);
            $('#categoryBlogsOverlay').fadeOut(120, function() {
                $('body').css('overflow', '');
            });
        }

        // اول بسته باشه - اطمینان از اینکه modal مخفی است
        $('#categoryBlogsModal').hide();
        $('#categoryBlogsOverlay').hide();

        // بستن با کلیک یا Esc
        $(document).on('click', '#closeCatModal, #categoryBlogsOverlay', closeCatModal);
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeCatModal();
        });

        // باز کردن مدال
        document.querySelectorAll('.open-category-blogs').forEach(openCategory => {
            openCategory.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const ajaxUrl = $(this).data('url');
                const groupId = $(this).data('group-id') || '';

                if (!ajaxUrl) return;

                // ریست UI
                $('#catList').empty().hide();
                $('#catEmpty').hide();
                $('#catLoading').show();
                $('#catModalTitle').text('در حال بارگذاری...');
                openCatModal();

                $.ajax({
                        url: ajaxUrl,
                        method: 'GET',
                        data: {
                            group_id: groupId
                        },
                        dataType: 'json',
                        headers: {
                            'Accept': 'application/json'
                        },
                        cache: false,
                        timeout: 15000 // 15s
                    })
                    .done(function(res) {
                        try {
                            $('#catModalTitle').text(
                                'دسته: ' +
                                (res?.category?.name || '—') +
                                ' (' +
                                (res?.count ?? 0) +
                                ')'
                            );

                            const items = Array.isArray(res?.items) ? res.items : [];
                            $('#catLoading').hide();

                            if (!items.length) {
                                $('#catEmpty').show();
                                return;
                            }

                            const $list = $('#catList').show();

                            items.forEach(function(item) {
                                const $li = $('<li/>').css({
                                    padding: '.75rem .5rem',
                                    borderBottom: '1px solid #eee',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: '.5rem'
                                });

                                const $left = $('<div/>').css({
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: '.25rem'
                                });

                                const $title = $('<a/>', {
                                        href: item.url,
                                        text: item.title,
                                        title: item.title
                                    })
                                    .css({
                                        color: '#0d6efd',
                                        textDecoration: 'none',
                                        fontWeight: '600'
                                    })
                                    .hover(
                                        function() {
                                            $(this).css('text-decoration', 'underline');
                                        },
                                        function() {
                                            $(this).css('text-decoration', 'none');
                                        }
                                    );

                                const $date = $('<small/>', {
                                    text: item.date
                                }).css({
                                    color: '#666'
                                });

                                $left.append($title, $date);

                                const $go = $('<a/>', {
                                    href: item.url,
                                    text: 'مشاهده'
                                }).css({
                                    padding: '.35rem .6rem',
                                    borderRadius: '8px',
                                    border: '1px solid #ddd',
                                    textDecoration: 'none'
                                });

                                $li.append($left, $go);
                                $list.append($li);
                            });
                        } catch (err) {
                            console.error('Parse/render error:', err);
                            $('#catLoading').hide();
                            $('#catEmpty').show().text('خطا در پردازش داده‌ها.');
                        }
                    })
                    .fail(function(xhr, status, err) {
                        console.error('AJAX fail:', status, err, xhr?.status, xhr
                            ?.responseText);
                        $('#catLoading').hide();
                        $('#catEmpty').show().text('خطا در دریافت لیست پست‌ها.');
                    })
                    .always(function() {
                        if ($('#catLoading').is(':visible')) {
                            $('#catLoading').hide();
                            $('#catEmpty').show().text('عدم دریافت پاسخ از سرور.');
                        }
                    });
            });
        });
    })();
    </script>
