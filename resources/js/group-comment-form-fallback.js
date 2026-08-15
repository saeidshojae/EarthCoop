// Delegated safety net for the standalone group comment page.
// The page has a legacy form-level submit handler. If that handler is not
// registered (for example because its DOMContentLoaded hook was missed), this
// listener prevents a normal browser navigation to /comment/send and performs
// the same JSON submit flow.

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.id !== 'commentForm') return;

    // The page-specific handler runs on the form before this delegated handler.
    // If it already intercepted the submit, do not send a duplicate request.
    if (event.defaultPrevented) return;

    event.preventDefault();

    const button = form.querySelector('#submit-btn, [type="submit"]');
    const textarea = form.querySelector('#message_editor');
    const editor = window.CKEDITOR?.instances?.message_editor;

    if (editor) editor.updateElement();

    const raw = editor ? editor.getData() : (textarea?.value || '');
    const tmp = document.createElement('div');
    tmp.innerHTML = raw;
    if (!(tmp.textContent || '').trim()) {
        window.commentNotify?.('لطفاً نظر خود را وارد کنید.');
        return;
    }

    if (button) button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status !== 'success') {
            const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
            throw new Error(errors || data?.message || 'خطا در ارسال نظر');
        }

        const list = document.getElementById('comments-list');
        if (list && data.comment?.html && !document.getElementById(`msg-${data.comment.id}`)) {
            const holder = document.createElement('div');
            holder.innerHTML = data.comment.html;
            const node = holder.firstElementChild;
            if (node) {
                list.querySelector('.comments-empty')?.remove();
                list.appendChild(node);
                window._initCommentReactions?.(node);
            }
        }

        const count = document.querySelector('.comments-section__count');
        if (count && Number.isFinite(Number(data.comments_count))) {
            count.textContent = String(data.comments_count);
        }

        if (editor) editor.setData('');
        else if (textarea) textarea.value = '';

        const parent = form.querySelector('#parent_id');
        if (parent) parent.value = '';
        document.getElementById('reply-indicator')?.classList.remove('show');

        window.commentNotify?.('نظر شما با موفقیت ثبت شد.', 'success');
    } catch (error) {
        console.error('Comment submit fallback failed:', error);
        window.commentNotify?.(error?.message || 'خطا در ارسال نظر');
    } finally {
        if (button) button.disabled = false;
    }
});
