export function createOperations({ api, store, feed, actions, lifecycle, groupId }) {
    const toast = (message, type = 'info') => window.GroupChatFeedback?.toast?.(message, { type });
    const confirm = (message, options = {}) => window.GroupChatFeedback?.confirm?.(message, options) || Promise.resolve(false);
    const prompt = (message, options = {}) => window.GroupChatFeedback?.prompt?.(message, options) || Promise.resolve(null);
    const ok = data => !data?.status || data.status === 'success';
    const register = (name, handler) => actions.register(name, context => {
        void handler(context);
        return true;
    });
    register('delete-message', async ({ target }) => {
        const id = Number(target.dataset.messageId);
        const bubble = document.querySelector(`.message-bubble[data-message-id="${id}"]`);
        if (!bubble?.dataset.deleteUrl) return toast('پیام موردنظر پیدا نشد.', 'error');
        if (!await confirm('آیا از حذف پیام مطمئن هستید؟', { confirmText: 'حذف' })) return;
        try {
            const data = await api.json(bubble.dataset.deleteUrl, { method: 'POST', credentials: 'same-origin' });
            if (!ok(data)) return toast(data.message || 'خطا در حذف پیام', 'error');
            feed.mutate({ content_type: 'message', id, action: 'delete' }, 'local-message-delete');
        } catch (error) {
            toast(error.message || 'خطا در ارتباط با سرور', 'error');
        }
    });

    register('report-message', async ({ target }) => {
        const id = Number(target.dataset.messageId);
        const reason = await prompt('لطفاً دلیل گزارش این پیام را وارد کنید:');
        if (!reason) return;
        try {
            const data = await api.json(`/groups/messages/${id}/report`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ reason }),
            });
            toast(ok(data) ? 'پیام با موفقیت گزارش شد.' : (data.message || 'خطا در گزارش پیام.'), ok(data) ? 'success' : 'error');
        } catch (error) {
            toast(error.message || 'خطا در گزارش پیام.', 'error');
        }
    });

    register('delete-post', async ({ target }) => {
        const id = Number(target.dataset.postId);
        if (!await confirm('آیا از حذف این پست اطمینان دارید؟', { confirmText: 'حذف' })) return;
        try {
            const data = await api.json(`/blog/${id}`, { method: 'DELETE' });
            if (!ok(data)) return toast(data.message || 'خطا در حذف پست', 'error');
            feed.mutate({ content_type: 'post', id, action: 'delete' }, 'local-post-delete');
        } catch (error) {
            toast(error.message || 'خطا در ارتباط با سرور', 'error');
        }
    });

    const closeReport = () => document.querySelector('.report-box')?.remove();
    actions.register('close-report', () => (closeReport(), true));
    actions.register('report-user', () => {
        if (document.querySelector('.report-box')) return true;
        const box = document.createElement('div');
        box.className = 'report-box';
        box.innerHTML = `<div class="report-header"><h3>گزارش کاربر</h3><button type="button" data-group-chat-action="close-report">×</button></div><div class="report-content"><select id="reportReason"><option value="spam">اسپم</option><option value="harassment">آزار و اذیت</option><option value="inappropriate">محتوا نامناسب</option><option value="other">سایر</option></select><textarea id="reportDescription" placeholder="توضیحات بیشتر..."></textarea><button type="button" data-group-chat-action="submit-report">ارسال گزارش</button></div>`;
        document.body.appendChild(box);
        return true;
    });
    register('submit-report', async () => {
        try {
            const data = await api.json(`/api/groups/${groupId}/report`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reason: document.getElementById('reportReason')?.value, description: document.getElementById('reportDescription')?.value }),
            });
            if (!data?.success && !ok(data)) return toast(data.message || 'خطا در ارسال گزارش', 'error');
            closeReport();
            toast('گزارش با موفقیت ارسال شد', 'success');
        } catch (error) { toast(error.message || 'خطا در ارسال گزارش', 'error'); }
    });

    register('clear-chat', async () => {
        if (!await confirm('آیا از پاک کردن تاریخچه چت اطمینان دارید؟', { confirmText: 'پاک کردن' })) return;
        try {
            const data = await api.json(`/api/groups/${groupId}/clear-history`, { method: 'POST' });
            if (!data?.success && !ok(data)) return toast(data.message || 'خطا در پاک کردن تاریخچه چت', 'error');
            document.getElementById('chat-box')?.replaceChildren();
            store.setState({ feed: Object.freeze({}), feedVersion: (store.getState().feedVersion || 0) + 1 });
            toast('تاریخچه چت با موفقیت پاک شد', 'success');
        } catch (error) { toast(error.message || 'خطا در پاک کردن تاریخچه چت', 'error'); }
    });
    register('delete-chat', async () => {
        if (!await confirm('آیا از حذف این چت اطمینان دارید؟ این عمل غیرقابل بازگشت است.', { confirmText: 'حذف چت' })) return;
        try {
            const data = await api.json(`/api/groups/${groupId}/delete`, { method: 'POST' });
            if (!data?.success && !ok(data)) return toast(data.message || 'خطا در حذف چت', 'error');
            window.location.assign('/groups');
        } catch (error) { toast(error.message || 'خطا در حذف چت', 'error'); }
    });

    const closePostEditModal = id => new Promise(resolve => {
        const modal = document.getElementById(`editPostModal-${id}`);
        const cleanup = () => {
            modal?.classList.remove('show');
            if (modal) {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
            }
            document.querySelectorAll('.modal-backdrop').forEach(element => element.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        };
        if (!modal) {
            cleanup();
            resolve();
            return;
        }
        const instance = window.bootstrap?.Modal?.getOrCreateInstance?.(modal);
        if (!instance) {
            cleanup();
            resolve();
            return;
        }
        let settled = false;
        let timeoutId;
        const finish = () => {
            if (settled) return;
            settled = true;
            modal.removeEventListener('hidden.bs.modal', finish);
            if (timeoutId) lifecycle.clearTimeout(timeoutId);
            cleanup();
            resolve();
        };
        lifecycle.on(modal, 'hidden.bs.modal', finish, { once: true });
        timeoutId = lifecycle.timeout(finish, 500);
        instance.hide();
    });

    lifecycle.on(document, 'submit', async event => {
        const form = event.target.closest?.('[data-post-edit-form]');
        if (!form) return;
        event.preventDefault();
        const id = Number(form.dataset.postId);
        const title = form.querySelector('[data-post-edit-title]')?.value.trim();
        const content = form.querySelector('[data-post-edit-content]')?.value.trim();
        const categoryId = form.querySelector('[data-post-edit-category]')?.value;
        if (!title || !content || !categoryId) return toast('لطفاً همه فیلدهای الزامی پست را تکمیل کنید', 'error');
        try {
            const data = await api.json(`/blog/${id}`, {
                method: 'PUT', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, content, category_id: categoryId }),
            });
            if (!ok(data)) return toast(data.message || 'خطا در ویرایش پست', 'error');
            await closePostEditModal(id);
            const post = data.post?.html ? { ...data.post, id: data.post.id || id } : data.blog;
            if (post) feed.mutate({ ...post, content_type: 'post', id, action: 'update' }, 'local-post-edit');
            toast('پست با موفقیت ویرایش شد', 'success');
        } catch (error) { toast(error.message || 'خطا در ارتباط با سرور', 'error'); }
    });

    return Object.freeze({ closeReport });
}
