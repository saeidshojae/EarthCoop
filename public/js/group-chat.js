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

async function groupChatFetch(input, init = {}) {
    if (!window.GroupChat?.api) throw new Error('GroupChat ApiClient is not ready');
    return window.GroupChat.api.request(input, init);
}
const feedBridge = window.GroupChat.installLegacyRenderers({
    updatePostFields: updatePostFieldsDom,
    updateLastPostCursor: id => window.GroupChat.realtimeRuntime?.advancePost(id),
});
const realtimeRuntime = window.GroupChat.installRealtime({ debug: groupChatDebug });
window.GroupChat.composer.initializeSubmission({ feed: window.GroupChat.feed, realtime: realtimeRuntime });
legacyLifecycle.timeout(() => {
    realtimeRuntime.initialize();
    realtimeRuntime.startPolling();
}, 2000);
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

