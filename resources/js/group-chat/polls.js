function validationMessage(data, fallback) {
    const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
    return [data?.message || fallback, errors].filter(Boolean).join('\n');
}

export function createPolls({ api, store, feed, actions, lifecycle }) {
    const notify = (message, type = 'info') => window.GroupChatFeedback?.toast?.(message, { type });
    const setStatus = (status, error = null) => store.setState({ pollStatus: status, pollError: error });

    async function request(url, body) {
        const response = await api.request(url, { method: 'POST', body });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status && data.status !== 'success') {
            const error = new Error(validationMessage(data, 'عملیات نظرسنجی با خطا مواجه شد.'));
            error.data = data;
            throw error;
        }
        return data;
    }

    function updateUI(pollData) {
        const pollId = pollData?.id || pollData?.poll_id;
        const pollElement = document.getElementById(`poll-${pollId}`);
        if (!pollElement) return false;
        const options = Array.isArray(pollData.options) ? pollData.options : [];
        const byId = new Map(options.map(option => [String(option.id), option]));
        pollElement.querySelectorAll('.poll-option[data-option-id]').forEach(button => {
            const option = byId.get(String(button.dataset.optionId));
            if (!option) return;
            const percent = Number.isFinite(Number(option.percent)) ? Number(option.percent) : 0;
            const stat = button.querySelector('.poll-option__stat');
            if (stat) stat.textContent = `${percent}%`;
            const selected = Number(pollData.user_option_id) === Number(button.dataset.optionId);
            button.classList.toggle('poll-option--selected', selected);
            button.classList.toggle('voted', selected);
        });
        const total = Number.isFinite(Number(pollData.total_votes))
            ? Number(pollData.total_votes)
            : options.reduce((sum, option) => sum + (Number(option.count) || 0), 0);
        const totalElement = pollElement.querySelector('.poll-card__total');
        if (totalElement) totalElement.textContent = `تعداد رأی: ${total}`;
        return true;
    }

    async function vote(target) {
        if (target.classList.contains('voted')) return;
        const pollId = target.dataset.pollId;
        const optionId = target.dataset.optionId;
        const body = new FormData();
        body.set('option_id', optionId);
        setStatus('voting');
        try {
            const data = await request(`/polls/${pollId}/vote`, body);
            feed.mutate({ ...data.poll, id: data.poll?.id || pollId, content_type: 'poll', action: 'vote' }, 'local-vote');
            document.dispatchEvent(new CustomEvent('poll-voted', { detail: { poll: data.poll, optionId } }));
            notify('رای شما ثبت شد', 'success');
            setStatus('idle');
        } catch (error) {
            setStatus('error', error);
            notify(error.message || 'خطا در اتصال به سرور', 'error');
        }
    }

    async function remove(target) {
        const pollId = Number(target.dataset.pollId);
        if (!pollId || !target.dataset.deleteUrl) return notify('اطلاعات حذف نظرسنجی ناقص است.', 'error');
        const confirmed = await window.GroupChatFeedback?.confirm?.('آیا از حذف این نظرسنجی مطمئن هستید؟', { confirmText: 'حذف' });
        if (!confirmed) return;
        setStatus('deleting');
        try {
            const data = await request(target.dataset.deleteUrl, new FormData());
            feed.mutate({ id: pollId, content_type: 'poll', action: 'delete' }, 'local-poll-delete');
            notify(data.message || 'نظرسنجی حذف شد.', 'success');
            setStatus('idle');
        } catch (error) {
            setStatus('error', error);
            notify(error.message, 'error');
        }
    }

    async function submitForm(form, action) {
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        setStatus(action === 'create' ? 'creating' : 'editing');
        try {
            const data = await request(form.action, new FormData(form));
            const poll = data.poll || {};
            const item = { ...poll, id: poll.id, content_type: 'poll', action };
            if (action === 'create') feed.apply([item], 'local-poll-create');
            else feed.mutate(item, 'local-poll-edit');
            if (action === 'create') {
                form.reset();
                const options = document.getElementById('dynamic-inputs');
                if (options) options.innerHTML = '<input type="text" name="options[]" placeholder="گزینه ۱" class="modal-input mb-2" />';
                window.handlePollTypeChange?.();
                window.cancelPollForm?.();
            }
            notify(data.message || (action === 'create' ? 'نظرسنجی با موفقیت ایجاد شد.' : 'نظرسنجی با موفقیت ویرایش شد.'), 'success');
            setStatus('idle');
        } catch (error) {
            setStatus('error', error);
            notify(error.message, 'error');
        } finally {
            form.dataset.submitting = 'false';
        }
    }

    actions.register('submit-vote', ({ target }) => { vote(target); });
    actions.register('delete-poll', ({ target }) => { remove(target); });
    lifecycle.on(document, 'submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.id === 'pollForm') {
            event.preventDefault();
            submitForm(form, 'create');
        } else if (form.classList.contains('poll-edit-form')) {
            event.preventDefault();
            submitForm(form, 'update');
        }
    });

    return Object.freeze({ updateUI, vote });
}
