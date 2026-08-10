<script>
function initializePostSubmissionRuntime() {
        const lifecycle = window.GroupChatLifecycle;
        if (!lifecycle || lifecycle.destroyed || window.__groupChatPostSubmissionInitialized) return;

        var postForm = document.getElementById('postForm');
        if (!postForm) return;

        window.__groupChatPostSubmissionInitialized = true;
        lifecycle.on(postForm, 'submit', async function(e) {
            e.preventDefault();
            var submitBtn = postForm.querySelector('button[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'در حال ارسال...'; }
            // Sync CKEditor to textarea BEFORE capturing FormData
            if (window.CKEDITOR) {
                Object.values(CKEDITOR.instances).forEach(function(ed) {
                    try { ed.updateElement(); } catch(ex) {}
                });
            }
            var formData = new FormData(postForm);
            var action = postForm.getAttribute('action');
            try {
                var csrfToken = (typeof getCsrfToken === 'function') ? getCsrfToken() :
                    (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var resp = await fetch(action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                var data = await resp.json();
                if (data.status === 'success' && data.post && data.post.html) {
                    // reset form and button FIRST
                    postForm.reset();
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'انتشار پست'; }
                    // reset CKEditor instances inside the form
                    if (window.CKEDITOR) {
                        Object.values(CKEDITOR.instances).forEach(function(ed) {
                            try { ed.setData(''); } catch(ex) {}
                        });
                    }
                    // close modal
                    if (typeof cancelPostForm === 'function') cancelPostForm();
                    // inject new post into chat
                    var chatBox = document.getElementById('chat-box');
                    if (chatBox) {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = data.post.html;
                        var postEl = tmp.firstElementChild;
                        if (postEl) {
                            chatBox.appendChild(postEl);
                            // init action menus for this new post
                            if (typeof window._initPostMenus === 'function') window._initPostMenus(postEl);
                            if (typeof window._initReactionButtons === 'function') window._initReactionButtons(postEl);
                            chatBox.scrollTop = chatBox.scrollHeight;
                            // update post polling lastPostId so we don't get duplicate from poll
                            if (data.post.id) { window._lastKnownPostId = Math.max(window._lastKnownPostId || 0, data.post.id); }
                        }
                    }
                } else {
                    window.groupChatNotify(data.message || 'خطا در ارسال پست', 'error');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'انتشار پست'; }
                }
            } catch(err) {
                console.error('Post submit error:', err);
                window.groupChatNotify('خطا در ارتباط با سرور', 'error');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'انتشار پست'; }
            }
        });

        lifecycle.add(function() {
            window.__groupChatPostSubmissionInitialized = false;
        });
}

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePostSubmissionRuntime, { once: true });
    } else {
        initializePostSubmissionRuntime();
    }
</script>
