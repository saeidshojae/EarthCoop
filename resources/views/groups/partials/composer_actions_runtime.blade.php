    <script>
    function initializeComposerActionsRuntime() {
        const lifecycle = window.GroupChatLifecycle;
        if (!lifecycle || lifecycle.destroyed || window.__groupChatComposerActionsInitialized) return;
        window.__groupChatComposerActionsInitialized = true;

        const plusButton = document.getElementById('chatCreateToggle');
        const menu = document.getElementById('createMenu');
        const triggerWrapper = plusButton?.closest('.telegram-attach-btn-wrapper');
        const audioUploadTrigger = document.getElementById('audio-upload-trigger');

        // Auto-resize textarea
        const textarea = document.getElementById('message_editor');
        if (textarea && !textarea.classList.contains('ckeditor-initialized')) {
            function autoResize() {
                if (textarea.scrollHeight > 0) {
                    textarea.style.height = 'auto';
                    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
                }
            }
            lifecycle.on(textarea, 'input', autoResize);
            textarea.classList.add('ckeditor-initialized');
        }

        const voiceFileInput = document.getElementById('voice-file-input');

        const toggleMenu = (visible) => {
            if (!menu) {
                return;
            }
            const shouldShow = typeof visible === 'boolean' ?
                visible :
                (menu.style.display === 'none' || menu.style.display === '');
            menu.style.display = shouldShow ? 'block' : 'none';
        };

        if (plusButton) {
            lifecycle.on(plusButton, 'click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu();
            });
        }

        lifecycle.on(document, 'click', function(e) {
            if (!menu) {
                return;
            }
            const clickedInsideTrigger = triggerWrapper?.contains(e.target);
            const clickedInsideMenu = menu.contains(e.target);
            if (!clickedInsideTrigger && !clickedInsideMenu) {
                toggleMenu(false);
            }
        });

        lifecycle.on(document, 'keydown', function(e) {
            if (e.key === 'Escape') {
                toggleMenu(false);
            }
        });

        menu?.querySelectorAll('button').forEach(function(actionButton) {
            // اگر دکمه onclick دارد (مثل openBlogBox, openPollBox)، منو را ببند اما event را متوقف نکن
            if (actionButton.onclick || actionButton.getAttribute('onclick')) {
                lifecycle.on(actionButton, 'click', function() {
                    // فقط منو را ببند، event را متوقف نکن تا onclick اجرا شود
                    toggleMenu(false);
                });
            } else {
                // برای دکمه‌های دیگر (مثل audio-upload-trigger) که event handler جداگانه دارند
                lifecycle.on(actionButton, 'click', function() {
                    toggleMenu(false);
                });
            }
        });

        if (audioUploadTrigger) {
            lifecycle.on(audioUploadTrigger, 'click', function(e) {
                e.preventDefault();
                toggleMenu(false);
                voiceFileInput?.click();
            });
        }

        // Handle create post button
        const createPostBtn = document.getElementById('create-post-btn');
        if (createPostBtn) {
            lifecycle.on(createPostBtn, 'click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(false);
                // کمی تأخیر برای بستن منو قبل از باز کردن modal
                lifecycle.timeout(function() {
                    // بررسی وجود تابع در scope global
                    if (typeof window.openBlogBox === 'function') {
                        window.openBlogBox();
                    } else if (typeof openBlogBox === 'function') {
                        openBlogBox();
                    } else {
                        console.error('openBlogBox function not found. Available functions:',
                            Object.keys(window).filter(k => k.includes('Blog') || k
                                .includes('Poll')));
                    }
                }, 150);
            });
        }

        // Handle create poll button
        const createPollBtn = document.getElementById('create-poll-btn');
        if (createPollBtn) {
            lifecycle.on(createPollBtn, 'click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(false);
                // کمی تأخیر برای بستن منو قبل از باز کردن modal
                lifecycle.timeout(function() {
                    // بررسی وجود تابع در scope global
                    if (typeof window.openPollBox === 'function') {
                        window.openPollBox();
                    } else if (typeof openPollBox === 'function') {
                        openPollBox();
                    } else {
                        console.error('openPollBox function not found. Available functions:',
                            Object.keys(window).filter(k => k.includes('Blog') || k
                                .includes('Poll')));
                    }
                }, 150);
            });
        }

        lifecycle.add(function() {
            window.__groupChatComposerActionsInitialized = false;
            textarea?.classList.remove('ckeditor-initialized');
            toggleMenu(false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeComposerActionsRuntime, { once: true });
    } else {
        initializeComposerActionsRuntime();
    }
    </script>
