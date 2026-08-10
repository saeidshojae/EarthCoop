import { renderMessage } from './message-renderer.js';

export function installLegacyRenderers({ app, callbacks = {} }) {
    const idOf = (item, type) => item?.content_id || item?.[`${type}_id`] || item?.id;
    const refreshPolls = () => app.polls?.refreshCountdowns();
    const feedRoot = () => document.getElementById('chat-box') || document.getElementById('group-feed');
    const appendHtml = (html, id, type) => {
        if (!html || (type === 'post' && document.getElementById(`blog-${id}`)) || (type === 'poll' && document.getElementById(`poll-${id}`))) return false;
        const root = feedRoot();
        if (!root) return false;
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const node = template.content.firstElementChild;
        if (!node) return false;
        const typing = document.getElementById('group-typing-indicator');
        root.insertBefore(node, typing?.parentElement === root ? typing : null);
        refreshPolls();
        return true;
    };
    const replaceHtml = (selector, html) => {
        const existing = html && document.querySelector(selector);
        if (!existing) return false;
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const node = template.content.firstElementChild;
        if (!node) return false;
        (existing.closest('.post-wrapper, .poll-wrapper') || existing).replaceWith(node);
        refreshPolls();
        return true;
    };
    const fadeRemove = element => {
        if (!element) return false;
        element.style.transition = 'opacity 0.3s ease-out';
        element.style.opacity = '0';
        app.lifecycle.timeout(() => element.remove(), 300);
        return true;
    };
    const updateMessage = item => {
        const id = idOf(item, 'message');
        const bubble = document.querySelector(`.message-bubble[data-message-id="${id}"]`);
        if (!bubble) return false;
        const content = item.content || item.message || '';
        const node = bubble.querySelector('.message-content');
        if (node) node.innerHTML = content;
        bubble.dataset.contentRaw = content.replace(/<[^>]*>/g, '');
        if (item.edited !== false && !bubble.querySelector('.message-edit-status')) {
            const badge = document.createElement('span');
            badge.className = 'edited-icon message-edit-status';
            badge.textContent = '(ویرایش شده)';
            const receipt = bubble.querySelector('.read-receipt');
            if (receipt) receipt.before(badge);
            else bubble.querySelector('.message-timestamp')?.appendChild(badge);
        }
        if (item.edited !== false) {
            const menuMeta = bubble.querySelector('.menu-meta-time');
            if (menuMeta && !menuMeta.querySelector('.menu-meta-time__item--edited')) {
                const edited = document.createElement('div');
                edited.className = 'menu-meta-time__item menu-meta-time__item--edited';
                edited.innerHTML = '<i class="fas fa-edit" aria-hidden="true"></i><span class="menu-meta-time__label">ویرایش شده:</span>';
                const value = document.createElement('span');
                value.className = 'menu-meta-time__value';
                value.textContent = item.edited_at ? new Date(item.edited_at).toLocaleString('fa-IR') : new Date().toLocaleString('fa-IR');
                edited.appendChild(value);
                menuMeta.appendChild(edited);
            }
        }
        return true;
    };
    const updateReactions = item => {
        const id = idOf(item, 'message');
        const bubble = document.querySelector(`.message-bubble[data-message-id="${id}"]`);
        if (!bubble) return false;
        let region = bubble.querySelector('.message-reactions');
        if (!item.reactions?.length) {
            region?.remove();
            return true;
        }
        if (!region) {
            region = document.createElement('div');
            region.className = 'message-reactions';
            const slot = bubble.querySelector('.message-reactions-slot');
            (slot || bubble).appendChild(region);
        }
        const emoji = { like: '👍', love: '❤️', laugh: '😂', wow: '😮', sad: '😢', angry: '😠' };
        region.replaceChildren(...item.reactions.map(reaction => {
            const type = reaction.type || reaction.reaction_type || '';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'reaction-badge';
            button.dataset.legacyChatAction = 'reaction';
            button.dataset.messageId = id;
            button.dataset.reactionType = type;
            button.textContent = `${emoji[type] || type || '👍'} ${reaction.count || 0}`;
            return button;
        }));
        return true;
    };
    const updatePostFields = item => {
        const root = document.getElementById(`blog-${idOf(item, 'post')}`);
        if (!root) return false;
        const title = root.querySelector('.blog-title');
        const content = root.querySelector('.blog-content');
        const editedAt = root.querySelector('.blog-edit-time');
        if (title) title.textContent = item.title || '';
        if (content) content.innerHTML = item.content || '';
        if (editedAt && item.updated_at) editedAt.textContent = `(ویرایش شده: ${item.updated_at})`;
        return true;
    };
    const updateReceipt = (type, id, readCount) => {
        const root = type === 'message'
            ? document.querySelector(`.message-bubble[data-message-id="${id}"]`)
            : document.getElementById(`${type === 'post' ? 'blog' : 'poll'}-${id}`);
        const receipt = root?.querySelector(type === 'message' ? '.read-receipt span' : `.${type}-read-receipt span`);
        if (!receipt) return false;
        const count = Number.isFinite(Number(readCount)) ? Number(readCount) : 0;
        receipt.style.color = count > 0 ? '#10b981' : '#9ca3af';
        receipt.replaceChildren();
        const icon = document.createElement('i');
        icon.className = count > 0 ? 'fas fa-check-double' : 'fas fa-check';
        receipt.append(icon, document.createTextNode(count > 0 ? ` ${count} نفر ${type === 'message' ? 'خوانده‌اند' : 'دیده‌اند'}` : ' ارسال شده'));
        return true;
    };
    const messageMutations = {
        edit: updateMessage,
        delete(item) {
            const id = idOf(item, 'message');
            const input = document.getElementById('parent_id');
            if (input?.value == id) app.composer?.cancelReply();
            return fadeRemove(document.getElementById(`msg-${id}`));
        },
        reaction: updateReactions,
        'mark-read': item => updateReceipt('message', idOf(item, 'message'), item.read_count || 0),
    };
    const dispatchComment = item => (document.dispatchEvent(new CustomEvent('group-comment-updated', { detail: item })), true);
    const adapters = {
        post: {
            render: item => appendHtml(item.html, idOf(item, 'post'), 'post'),
            update: item => item.html ? replaceHtml(`#blog-${idOf(item, 'post')}`, item.html) : updatePostFields(item),
            delete: item => fadeRemove(document.getElementById(`blog-${idOf(item, 'post')}`)?.closest('.post-wrapper')),
            reaction(item) {
                const region = document.querySelector(`.reaction-buttons[data-post-id="${idOf(item, 'post')}"]`);
                if (!region) return false;
                if (region.querySelector('.like-count')) region.querySelector('.like-count').textContent = item.likes ?? 0;
                if (region.querySelector('.dislike-count')) region.querySelector('.dislike-count').textContent = item.dislikes ?? 0;
                return true;
            },
            read: item => updateReceipt('post', idOf(item, 'post'), item.read_count || 0),
        },
        poll: {
            render: item => appendHtml(item.html, idOf(item, 'poll'), 'poll'),
            update: item => replaceHtml(`#poll-${idOf(item, 'poll')}, [data-poll-id="${idOf(item, 'poll')}"]`, item.html),
            vote: item => app.polls?.updateUI(item) || false,
            delete: item => fadeRemove((document.getElementById(`poll-${idOf(item, 'poll')}`) || document.querySelector(`[data-poll-id="${idOf(item, 'poll')}"]`))?.closest('.poll-wrapper')),
            read: item => updateReceipt('poll', idOf(item, 'poll'), item.read_count || 0),
        },
        comment: { render: dispatchComment, update: dispatchComment, delete: dispatchComment, reaction: dispatchComment },
    };
    app.renderer.register('message', {
        render: renderMessage,
        mutate: (item, context) => messageMutations[context.action]?.(item) || false,
    });
    Object.entries(adapters).forEach(([type, adapter]) => app.renderer.register(type, {
        render: (item, context) => adapter.render?.(item, context) || false,
        mutate: (item, context) => adapter[context.action]?.(item, context) || false,
    }));
    const normalize = (type, action, payload) => ({ ...payload, content_type: type, content_id: idOf(payload, type), action });
    const bridge = Object.freeze({
        create(type, payload, source = 'local') {
            const result = app.feed.apply([normalize(type, 'create', payload)], source)[0] || false;
            if (result && type === 'post') callbacks.updateLastPostCursor?.(idOf(payload, type));
            return result;
        },
        mutate(type, action, payload, source = 'local') {
            return app.feed.mutate(normalize(type, action, payload), source);
        },
    });
    app.feedBridge = bridge;
    return bridge;
}
