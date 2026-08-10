    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
            textarea.addEventListener('input', autoResize);
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

        plusButton?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });

        document.addEventListener('click', function(e) {
            if (!menu) {
                return;
            }
            const clickedInsideTrigger = triggerWrapper?.contains(e.target);
            const clickedInsideMenu = menu.contains(e.target);
            if (!clickedInsideTrigger && !clickedInsideMenu) {
                toggleMenu(false);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                toggleMenu(false);
            }
        });

        menu?.querySelectorAll('button').forEach(function(actionButton) {
            // اگر دکمه onclick دارد (مثل openBlogBox, openPollBox)، منو را ببند اما event را متوقف نکن
            if (actionButton.onclick || actionButton.getAttribute('onclick')) {
                actionButton.addEventListener('click', function(e) {
                    // فقط منو را ببند، event را متوقف نکن تا onclick اجرا شود
                    toggleMenu(false);
                });
            } else {
                // برای دکمه‌های دیگر (مثل audio-upload-trigger) که event handler جداگانه دارند
                actionButton.addEventListener('click', function() {
                    toggleMenu(false);
                });
            }
        });

        audioUploadTrigger?.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMenu(false);
            voiceFileInput?.click();
        });

        // Handle create post button
        const createPostBtn = document.getElementById('create-post-btn');
        if (createPostBtn) {
            createPostBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(false);
                // کمی تأخیر برای بستن منو قبل از باز کردن modal
                setTimeout(function() {
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
            createPollBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(false);
                // کمی تأخیر برای بستن منو قبل از باز کردن modal
                setTimeout(function() {
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

    });
    </script>
