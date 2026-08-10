function validationMessage(data, fallback) {
    const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
    return [data?.message || fallback, errors].filter(Boolean).join('\n');
}

export function createPolls({ api, store, feed, actions, lifecycle }) {
    function refreshCountdowns() {
        document.querySelectorAll('.poll-timer').forEach(timer => {
            if (timer.dataset.timerSet === 'true') return;
            const expiresAtValue = timer.dataset.expires;
            if (!expiresAtValue) {
                timer.textContent = 'بدون زمان پایان';
                return;
            }
            const expiresAt = new Date(expiresAtValue);
            let intervalId = null;
            const stop = label => {
                if (label) timer.textContent = label;
                if (intervalId !== null) lifecycle.clearInterval(intervalId);
                intervalId = null;
                timer.dataset.timerSet = 'complete';
            };
            const update = () => {
                if (!timer.isConnected) return stop(), false;
                const diff = expiresAt.getTime() - Date.now();
                if (Number.isNaN(diff)) return stop('تاریخ نامعتبر'), false;
                if (diff <= 0) return stop('پایان یافته'), false;
                const total = Math.floor(diff / 1000);
                const hours = Math.floor(total / 3600);
                const minutes = Math.floor((total % 3600) / 60);
                const seconds = total % 60;
                const clock = `${String(hours % 24).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                timer.textContent = hours > 24 ? `${Math.floor(hours / 24)} روز ${clock}` : clock;
                return true;
            };
            if (update()) {
                intervalId = lifecycle.interval(update, 1000);
                timer.dataset.timerSet = 'true';
            }
        });
    }
    const notify = (message, type = 'info') => window.GroupChatFeedback?.toast?.(message, { type });
    const setStatus = (status, error = null) => store.setState({ pollStatus: status, pollError: error });
    const syncType = () => {
        const type = document.getElementById('poll_type');
        const specialties = document.getElementById('specialties_box');
        if (specialties) specialties.style.display = type?.value === '1' ? 'block' : 'none';
    };
    const addOption = () => {
        const container = document.getElementById('dynamic-inputs');
        if (!container) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex gap-2 mb-2';
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'options[]';
        input.required = true;
        input.className = 'modal-input';
        input.placeholder = `گزینه ${container.children.length + 1}`;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'modal-option-remove';
        remove.dataset.groupChatAction = 'remove-poll-option';
        remove.textContent = '×';
        wrapper.append(input, remove);
        container.appendChild(wrapper);
    };

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
                syncType();
                window.GroupChat?.composer?.closePoll();
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
    actions.register('add-poll-option', addOption);
    actions.register('remove-poll-option', ({ target }) => { target.parentElement?.remove(); });
    const pollType = document.getElementById('poll_type');
    if (pollType) lifecycle.on(pollType, 'change', syncType);
    const specialties = window.jQuery?.('#specialties');
    if (specialties?.length && !specialties.data('select2')) {
        specialties.select2({ dropdownParent: window.jQuery('#pollOptionsBox') });
        lifecycle.add(() => { if (specialties.data('select2')) specialties.select2('destroy'); });
    }
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

    refreshCountdowns();
    return Object.freeze({ updateUI, vote, refreshCountdowns });
}
