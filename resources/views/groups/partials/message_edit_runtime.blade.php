    <script>
    function initializeMessageEditRuntime() {
        const lifecycle = window.GroupChatLifecycle;
        if (!lifecycle || lifecycle.destroyed || window.__groupChatMessageEditInitialized) return;
        window.__groupChatMessageEditInitialized = true;
        // اگر CSRF را در <meta name="csrf-token" content="..."> داری:
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            '{{ csrf_token() }}';

        const modal = document.getElementById('editModal');
        const textarea = document.getElementById('editText');
        if (!modal || !textarea) {
            window.__groupChatMessageEditInitialized = false;
            return;
        }
        const btnSave = modal.querySelector('.save-edit');
        const btnCancel = modal.querySelector('.cancel-edit');
        const btnClose = modal.querySelector('.edit-close');
        const backdrop = modal.querySelector('.edit-modal__backdrop');
        if (!btnSave) {
            window.__groupChatMessageEditInitialized = false;
            return;
        }

        // متغیرهای وضعیت جاری ویرایش
        let currentBubble = null; // عنصر .message-bubble
        let currentUrl = null; // آدرس PATCH
        let currentId = null; // message-id

        // هندلر کلیک روی "ویرایش"
        lifecycle.on(document, 'click', function(e) {
            const editBtn = e.target.closest('.btn-edit');
            if (!editBtn) return;

            const bubble = editBtn.closest('.message-bubble');
            if (!bubble) return;

            // بستن منوی عملیات قبل از باز کردن مدال
            const actionMenu = bubble.closest('.message-head')?.querySelector('[data-action-menu]');
            if (actionMenu) {
                actionMenu.classList.remove('is-open');
                actionMenu.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded', 'false');
            }

            currentBubble = bubble;
            currentUrl = bubble.dataset.editUrl;
            currentId = bubble.dataset.messageId;

            // متن فعلی برای پر کردن باکس:
            // اول تلاش می‌کنیم از DOM (message-content) بخوانیم و HTML (مثل <br>) را به متن ساده با line break تبدیل کنیم
            const contentEl = bubble.querySelector('.message-content');
            let raw = '';
            if (contentEl) {
                const messageHtml = contentEl.innerHTML || "";
                raw = htmlToPlain(messageHtml);
            }

            // اگر به هر دلیلی متن خالی بود، از data-content-raw استفاده کن (fallback)
            if (!raw && bubble.dataset.contentRaw) {
                raw = bubble.dataset.contentRaw;
            }

            textarea.value = raw || '';
            openModal();
        });

        // ذخیره (ارسال PATCH)
        lifecycle.on(btnSave, 'click', async function() {
            const newText = textarea.value.trim();

            if (!currentBubble || !currentUrl) {
                closeModal();
                return;
            }

            // چرخنده و دکمه
            const overlay = document.getElementById('global-loading');
            const showOverlay = () => overlay && overlay.classList.add('show');
            const hideOverlay = () => overlay && overlay.classList.remove('show');
            const setBtnLoading = (on = true) => {
                btnSave.disabled = on;
                btnSave.classList.toggle('btn-loading', on);
            };

            try {
                showOverlay();
                setBtnLoading(true);

                // اگر روتت دقیقاً POST می‌پذیره (بدون شبیه‌سازی PATCH)، همین کافیه:
                const res = await fetch(currentUrl, {
                    method: 'POST', // مطابق کنترلرت
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        content: newText
                    })
                });

                // خواندن response (یک بار)
                let responseData = null;
                try {
                    const contentType = res.headers.get('content-type') || '';
                    console.log('Response status:', res.status, res.ok);
                    console.log('Content-Type:', contentType);

                    if (contentType.includes('application/json')) {
                        responseData = await res.json();
                    } else {
                        const text = await res.text();
                        try {
                            responseData = JSON.parse(text);
                        } catch {
                            responseData = {
                                message: text
                            };
                        }
                    }
                    console.log('Response data:', responseData);
                } catch (parseError) {
                    console.error('Error parsing response:', parseError);
                    window.groupChatNotify('خطا در خواندن پاسخ سرور', 'error');
                    return;
                }

                // بررسی status response
                if (!res.ok) {
                    // خطا در response
                    const errorMsg = responseData?.message || responseData?.error ||
                        'خطا در ذخیره‌سازی.';
                    console.error('Response error:', errorMsg);
                    window.groupChatNotify(errorMsg, 'error');
                    return;
                }

                // بررسی وجود currentBubble
                if (!currentBubble) {
                    console.error('currentBubble is null');
                    window.groupChatNotify('خطا: عنصر پیام پیدا نشد. لطفا صفحه را رفرش کنید.', 'error');
                    // ✅ FIXED: No reload - let user refresh manually if needed
                    closeModal();
                    return;
                }

                console.log('Current bubble found:', currentBubble);

                // ذخیره کردن currentBubble قبل از بستن مودال (چون closeModal ممکن است آن را null کند)
                const bubbleToUpdate = currentBubble;

                // بستن منوی عملیات (اگر bubble هنوز در DOM است)
                if (bubbleToUpdate && bubbleToUpdate.isConnected) {
                    try {
                        const actionMenu = bubbleToUpdate.closest('.message-head')?.querySelector(
                            '[data-action-menu]');
                        if (actionMenu) {
                            actionMenu.classList.remove('is-open');
                            actionMenu.querySelector('.action-menu__toggle')?.setAttribute(
                                'aria-expanded', 'false');
                            console.log('Action menu closed');
                        }
                        // همچنین بستن details menu در صورت وجود (برای سازگاری)
                        const details = bubbleToUpdate.closest('details.menu-wrapper[open]');
                        if (details) {
                            details.removeAttribute('open');
                            console.log('Details menu closed');
                        }
                    } catch (e) {
                        console.warn('Error closing menu:', e);
                        // ادامه بده، این خطای جدی نیست
                    }
                }

                // بستن مودال
                closeModal();

                // استفاده از bubbleToUpdate برای به‌روزرسانی (نه currentBubble که ممکن است null شده باشد)
                const finalBubble = bubbleToUpdate;

                // بررسی format response و به‌روزرسانی محتوا
                let contentToUpdate = null;
                if (responseData && responseData.content) {
                    contentToUpdate = responseData.content;
                    console.log('Using responseData.content:', contentToUpdate);
                } else if (responseData && responseData.message && typeof responseData.message ===
                    'object' && responseData.message.content) {
                    // اگر message یک object است و content دارد
                    contentToUpdate = responseData.message.content;
                    console.log('Using responseData.message.content:', contentToUpdate);
                } else if (responseData && responseData.message && typeof responseData.message ===
                    'string' && responseData.message !== 'پیام با موفقیت ویرایش شد') {
                    // اگر message یک string است (نه object) و پیام موفقیت نیست
                    contentToUpdate = responseData.message;
                    console.log('Using responseData.message (string):', contentToUpdate);
                }

                console.log('Content to update:', contentToUpdate);

                if (contentToUpdate) {
                    try {
                        console.log('Calling updateMessageContent with finalBubble...');
                        if (!finalBubble || !finalBubble.isConnected) {
                            console.error('finalBubble is null or not in DOM');
                            throw new Error('Message bubble not found in DOM');
                        }
                        updateMessageContent(finalBubble, contentToUpdate, true);
                        console.log('Message updated successfully!');
                        // موفق: هیچ reload لازم نیست
                    } catch (updateError) {
                        console.error('Error in updateMessageContent:', updateError);
                        console.error('Error stack:', updateError.stack);
                        // ✅ FIXED: No reload - show error and close modal
                        if (updateError.message && (updateError.message.includes('null') || updateError
                                .message.includes('not found') || updateError.message.includes(
                                    'not in DOM'))) {
                            console.warn('Critical error in updateMessageContent');
                            window.groupChatNotify('خطا در به‌روزرسانی پیام. لطفا دوباره تلاش کنید.', 'error');
                            closeModal();
                        } else {
                            // برای خطاهای دیگر، فقط alert بده
                            console.warn('Non-critical error in updateMessageContent, not reloading');
                            window.groupChatNotify('خطا در به‌روزرسانی پیام: ' + updateError.message, 'error');
                        }
                    }
                } else {
                    console.warn('Unexpected response format:', responseData);
                    console.warn('Response keys:', Object.keys(responseData || {}));
                    // اگر response درست نبود، سعی کن از message استفاده کن
                    if (responseData && responseData.message && typeof responseData.message ===
                        'string' && responseData.message !== 'پیام با موفقیت ویرایش شد') {
                        // اگر message یک string است و پیام موفقیت نیست، از آن استفاده کن
                        console.log('Trying to use responseData.message as content');
                        try {
                            if (!finalBubble || !finalBubble.isConnected) {
                                throw new Error('Message bubble not found in DOM');
                            }
                            updateMessageContent(finalBubble, responseData.message, true);
                            console.log('Message updated using responseData.message');
                        } catch (e) {
                            console.error('Failed to update using message:', e);
                            // ✅ FIXED: No reload - show error message
                            window.groupChatNotify('خطا در به‌روزرسانی پیام. لطفا دوباره تلاش کنید.', 'error');
                            closeModal();
                        }
                    } else {
                        // ✅ FIXED: No reload - show error message
                        console.error('No valid content found in response');
                        window.groupChatNotify('خطا در دریافت محتوای پیام. لطفا دوباره تلاش کنید.', 'error');
                        closeModal();
                    }
                }

            } catch (err) {
                console.error('Error in edit handler:', err);
                console.error('Error stack:', err.stack);
                console.error('Error name:', err.name);
                console.error('Error message:', err.message);
                // ✅ FIXED: No location.reload() - handle network errors gracefully
                if (err.name === 'TypeError' && (err.message.includes('fetch') || err.message.includes(
                        'network') || err.message.includes('Failed to fetch'))) {
                    console.warn('Network error detected');
                    window.groupChatNotify('خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'error');
                    closeModal();
                    return;
                }
                // برای سایر خطاها، فقط alert بده و مودال را ببند
                window.groupChatNotify('خطا در ویرایش پیام: ' + (err.message || 'خطای نامشخص'), 'error');
                closeModal();

            } finally {
                hideOverlay();
                setBtnLoading(false);
            }
        });


        // بستن مودال
        [btnCancel, btnClose, backdrop].filter(Boolean).forEach(el => lifecycle.on(el, 'click', closeModal));
        lifecycle.on(document, 'keydown', e => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });

        function openModal() {
            modal.classList.remove('hidden');
            textarea.focus();
            // مکان‌نما آخر متن:
            const val = textarea.value;
            textarea.setSelectionRange(val.length, val.length);
        }

        function closeModal() {
            modal.classList.add('hidden');
            textarea.value = '';
            currentBubble = null;
            currentUrl = null;
            currentId = null;
        }

        function htmlToPlain(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return (tmp.textContent || tmp.innerText || '').trim();
        }
        lifecycle.add(function() {
            closeModal();
            window.__groupChatMessageEditInitialized = false;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMessageEditRuntime, { once: true });
    } else {
        initializeMessageEditRuntime();
    }
    </script>
