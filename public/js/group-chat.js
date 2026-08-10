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
window.groupChatNotify = groupChatNotify;
window.groupChatConfirm = groupChatConfirm;
window.groupChatPrompt = groupChatPrompt;

// ========== FORCE LOG - همیشه نمایش بده ==========
// استفاده از alert برای اطمینان از نمایش
if (groupChatDebug && typeof window !== 'undefined') {
    debugLog('🔍🔍🔍 SCRIPT LOADED - VERSION 2024-12-19-v4 🔍🔍🔍');
    debugLog('🔍 window.groupId:', typeof window.groupId !== 'undefined' ? window.groupId : 'NOT DEFINED YET');
    debugLog('🔍 Current time:', new Date().toISOString());
    
    // تست: اگر بعد از 3 ثانیه console.log ها نمایش داده نشدند، alert نمایش بده
    setTimeout(function() {
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

function generateClientMessageId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return 'cmid_' + Date.now() + '_' + Math.random().toString(16).slice(2);
}

function getOrCreateClientMessageIdInput(form) {
    let input = form.querySelector('input[name="client_message_id"]');

    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'client_message_id';
        form.appendChild(input);
    }

    if (!input.value) {
        input.value = generateClientMessageId();
    }

    return input;
}

function groupChatRequestId() {
    return generateClientMessageId().replace(/^cmid_/, 'req_');
}

async function groupChatFetch(input, init = {}) {
    if (window.__groupChatModularFrontend && window.GroupChat?.api) {
        return window.GroupChat.api.request(input, init);
    }

    const options = { ...init };
    const method = String(options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});
    const requestId = headers.get('X-Request-ID') || groupChatRequestId();
    headers.set('X-Request-ID', requestId);
    headers.set('Accept', headers.get('Accept') || 'application/json');

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && !headers.has('Idempotency-Key')) {
        headers.set('Idempotency-Key', groupChatRequestId().replace(/^req_/, 'idem_'));
    }
    options.headers = headers;

    const timeoutMs = Number(window.__groupChatRequestTimeoutMs || 15000);
    const controller = new AbortController();
    const upstreamSignal = options.signal;
    if (upstreamSignal) {
        upstreamSignal.addEventListener('abort', () => controller.abort(upstreamSignal.reason), { once: true });
    }
    options.signal = controller.signal;

    let lastError;
    for (let attempt = 0; attempt < 2; attempt += 1) {
        const timer = window.setTimeout(() => controller.abort('timeout'), timeoutMs);
        try {
            const response = await window.fetch(input, options);
            window.clearTimeout(timer);
            if (attempt === 0 && response.status >= 500) {
                await new Promise(resolve => window.setTimeout(resolve, 250));
                continue;
            }
            return response;
        } catch (error) {
            window.clearTimeout(timer);
            lastError = error;
            if (controller.signal.aborted || attempt > 0) throw error;
            await new Promise(resolve => window.setTimeout(resolve, 250));
        }
    }

    throw lastError || new Error('Request failed');
}

document.addEventListener('DOMContentLoaded', function () {
    debugLog('Tabs script loaded ✅');

    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));

        tab.classList.add('active');
        const tabId = tab.getAttribute('data-tab');
        const target = document.getElementById(tabId);
        if (target) target.classList.add('active');
      });
    });
  });

function submitVote(el) {
    const pollId = $(el).data('poll-id');
    const optionId = $(el).data('option-id');

    if ($(el).hasClass('voted')) return;

    // ذخیره موقعیت scroll قبل از ارسال
    const chatBox = document.getElementById('chat-box');
    if (chatBox) {
        const scrollPosition = chatBox.scrollTop;
        // استفاده از window.groupId که در chat.blade.php تعریف شده
        const groupId = window.groupId || (typeof GROUP_ID !== 'undefined' ? GROUP_ID : 'default');
        const STORAGE_KEY = 'chatScroll_' + groupId;
        sessionStorage.setItem(STORAGE_KEY, scrollPosition);
        
        // همچنین آخرین پیام visible را ذخیره کن
        const messages = chatBox.querySelectorAll('[data-message-id]');
        let lastVisibleId = null;
        for (let i = messages.length - 1; i >= 0; i--) {
            const msg = messages[i];
            const rect = msg.getBoundingClientRect();
            const chatBoxRect = chatBox.getBoundingClientRect();
            if (rect.top >= chatBoxRect.top && rect.bottom <= chatBoxRect.bottom) {
                const messageId = parseInt(msg.getAttribute('data-message-id'));
                if (messageId && !isNaN(messageId)) {
                    lastVisibleId = messageId;
                    break;
                }
            }
        }
        if (lastVisibleId) {
            sessionStorage.setItem('lastVisibleMessageId_' + groupId, lastVisibleId);
        }
    }

    $.ajax({
        url: `/polls/${pollId}/vote`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken()
        },
        data: {
            option_id: optionId
        },
        success: function(data) {
            if (data.status === 'success') {
                // ✅ FIXED: No location.reload() - update DOM smoothly instead
                updatePollUI(data.poll);
                // Dispatch event for other listeners
                document.dispatchEvent(new CustomEvent('poll-voted', {
                    detail: { poll: data.poll, optionId: optionId }
                }));
                showSuccessAlert('رای شما ثبت شد');
            } else {
              showErrorAlert(data.message || 'خطا در ثبت رأی');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ خطا در اتصال:', error);
            showErrorAlert('خطا در اتصال به سرور');
        }
    });
}

window.deletePoll = async function(pollId, deleteUrl) {
    if (!pollId || !deleteUrl) {
        showErrorAlert('اطلاعات حذف نظرسنجی ناقص است.');
        return;
    }

    if (!await groupChatConfirm('آیا از حذف این نظرسنجی مطمئن هستید؟', { confirmText: 'حذف' })) {
        return;
    }

    try {
        const response = await fetch(deleteUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status !== 'success') {
            showErrorAlert(data.message || 'حذف نظرسنجی با خطا مواجه شد.');
            return;
        }

        removePollDom(pollId);
        showSuccessAlert(data.message || 'نظرسنجی حذف شد.');
    } catch (error) {
        console.error('Delete poll failed:', error);
        showErrorAlert('خطا در اتصال به سرور');
    }
};

$(document).ready(function() {
  // Select2 برای options (نه manager_vote و inspector_vote که در election_modal مدیریت می‌شوند)
  if ($('#options').length && !$('#options').data('select2')) {
    $('#options').select2({
      placeholder: "انتخاب کنید",
      dir: "rtl",
      tags: 'true'
    });
  }
  
  // Select2 برای manager_vote و inspector_vote فقط اگر در election modal نباشند
  // یا اگر تابع updateElectionSelect2 موجود نباشد
  if (!$('#electionVotingOverlay').length || typeof updateElectionSelect2 === 'undefined') {
    if ($('#manager_vote').length && !$('#manager_vote').data('select2')) {
  $('#manager_vote').select2({
    multiple: true,
    placeholder: "انتخاب کنید",
    dir: "rtl",
  });
    }
    if ($('#inspector_vote').length && !$('#inspector_vote').data('select2')) {
  $('#inspector_vote').select2({
    multiple: true,
    placeholder: "انتخاب کنید",
    dir: "rtl",
  });
    }
  }

  $('#electionForm').on('submit', function (e) {
    const inspectorSelectedCount = $('#inspector_vote').val()?.length || 0;
    const managerSelectedCount   = $('#manager_vote').val()?.length || 0;
  
    console.log(`بازرس: ${inspectorSelectedCount}, مدیر: ${managerSelectedCount}`);
  
    if (inspectorSelectedCount > inspectorCount) {
      e.preventDefault();
      showWarningAlert(`برای تعداد بازرس دقیقاً ${inspectorCount} گزینه انتخاب کنید.`);
      return;
    }
  
    if (managerSelectedCount > manageCount) {
      e.preventDefault();
      showWarningAlert(`برای تعداد مدیر دقیقاً ${manageCount} گزینه انتخاب کنید.`);
      return;
    }
  
    // همه‌چیز اوکی => فرم ارسال می‌شه
  });
  

  

});

document.addEventListener('DOMContentLoaded', function() {
    const pollForm = document.getElementById('pollForm');

    if (pollForm && !pollForm.dataset.ajaxBound) {
        pollForm.dataset.ajaxBound = 'true';
        pollForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            if (pollForm.dataset.submitting === 'true') {
                return;
            }
            pollForm.dataset.submitting = 'true';

            try {
                const response = await groupChatFetch(pollForm.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json'
                    },
                    body: new FormData(pollForm)
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.status !== 'success') {
                    const validationErrors = data.errors ? Object.values(data.errors).flat().join('\n') : '';
                    showErrorAlert([data.message || 'ارسال نظرسنجی با خطا مواجه شد.', validationErrors].filter(Boolean).join('\n'));
                    return;
                }

                const poll = data.poll || {};
                if (poll.html && poll.id) {
                    appendRenderedFeedHtml(poll.html, poll.id, 'poll');
                }

                pollForm.reset();
                const optionsContainer = document.getElementById('dynamic-inputs');
                if (optionsContainer) {
                    optionsContainer.innerHTML = '<input type="text" name="options[]" placeholder="گزینه ۱" class="modal-input mb-2" />';
                }
                if (typeof handlePollTypeChange === 'function') {
                    handlePollTypeChange();
                }
                if (typeof cancelPollForm === 'function') {
                    cancelPollForm();
                }

                showSuccessAlert(data.message || 'نظرسنجی با موفقیت ایجاد شد.');
            } catch (error) {
                console.error('Create poll failed:', error);
                showErrorAlert('خطا در اتصال به سرور');
            } finally {
                pollForm.dataset.submitting = 'false';
            }
        });
    }
});

document.addEventListener('submit', async function(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.classList.contains('poll-edit-form')) return;

    event.preventDefault();
    if (form.dataset.submitting === 'true') {
        return;
    }
    form.dataset.submitting = 'true';

    try {
        const response = await groupChatFetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: new FormData(form)
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status !== 'success') {
            const validationErrors = data.errors ? Object.values(data.errors).flat().join('\n') : '';
            showErrorAlert([data.message || 'ویرایش نظرسنجی با خطا مواجه شد.', validationErrors].filter(Boolean).join('\n'));
            return;
        }

        const poll = data.poll || {};
        if (poll.id && poll.html) {
            replaceRenderedFeedHtml('#poll-' + poll.id + ', [data-poll-id="' + poll.id + '"]', poll.html);
        }

        showSuccessAlert(data.message || 'نظرسنجی با موفقیت ویرایش شد.');
    } catch (error) {
        console.error('Edit poll failed:', error);
        showErrorAlert('خطا در اتصال به سرور');
    } finally {
        form.dataset.submitting = 'false';
    }
});

function openElectionBox(){
  const overlay = document.getElementById('electionVotingOverlay');
  if (overlay) {
    // Move overlay to body if not already there
    if (overlay.parentElement !== document.body) {
      document.body.appendChild(overlay);
    }
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    // Scroll to top of overlay
    overlay.scrollTop = 0;
    
    // Trigger event برای بروزرسانی Select2 بعد از باز شدن مدال
    setTimeout(function() {
      if (typeof window.updateElectionSelect2 === 'function') {
        window.updateElectionSelect2();
      }
      // Dispatch custom event برای اطلاع سایر کدها
      try {
        const event = new Event('electionModalOpened');
        window.dispatchEvent(event);
      } catch(e) {
        // Fallback for older browsers
        try {
          var event = document.createEvent('Event');
          event.initEvent('electionModalOpened', true, true);
          window.dispatchEvent(event);
        } catch(e2) {
          console.error('Error dispatching event:', e2);
        }
      }
    }, 600);
  }
  closeGroupInfo();
}

function closeElectionBox(){
  const overlay = document.getElementById('electionVotingOverlay');
  if (overlay) {
    overlay.style.display = 'none';
    document.body.style.overflow = '';
  }
}

// Close election modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const overlay = document.getElementById('electionVotingOverlay');
    if (overlay && overlay.style.display === 'flex') {
      closeElectionBox();
    }
  }
});

function sendReaction(blogId, type, container) {
  $.ajax({
    url: `/blogs/${blogId}/react`,
    method: 'POST',
    data: {
      type: type,
      _token: getCsrfToken()
    },
    success: function (data) {
      if (data.status === 'success') {
        // بروزرسانی تعداد لایک/دیسلایک
        $(container).find('.like-count').text(data.likes);
        $(container).find('.dislike-count').text(data.dislikes);

        // تغییر کلاس‌ برای حالت فعال یا غیرفعال
        const likeBtn = $(container).find('.btn-like');
        const dislikeBtn = $(container).find('.btn-dislike');

        if (type === '1') {
          likeBtn.toggleClass('active');
          dislikeBtn.removeClass('active');
        } else {
          dislikeBtn.toggleClass('active');
          likeBtn.removeClass('active');
        }
      } else {
        showErrorAlert(data.message || 'خطا در ثبت واکنش');
      }
    },
    error: function () {
      showErrorAlert('❌ خطا در ارتباط با سرور');
    }
  });
}



function openGroupInfo() {
  const panel = document.getElementById('groupInfoPanel');
  const backdrop = document.getElementById('groupInfoBackdrop');
  if (!panel) return;

  if (window.innerWidth < 1024) {
    panel.classList.add('is-open');
    if (backdrop) {
      backdrop.classList.remove('hidden');
      backdrop.classList.add('group-info-backdrop--visible');
    }
  }
}

function closeGroupInfo() {
  const panel = document.getElementById('groupInfoPanel');
  const backdrop = document.getElementById('groupInfoBackdrop');
  if (!panel) return;

  panel.classList.remove('is-open');
  if (backdrop) {
    backdrop.classList.add('hidden');
    backdrop.classList.remove('group-info-backdrop--visible');
  }
}

document.getElementById('groupInfoBackdrop')?.addEventListener('click', closeGroupInfo);
window.addEventListener('resize', () => {
  if (window.innerWidth >= 1024) {
    closeGroupInfo();
  }
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closeGroupInfo();
  }
});

function positionActionMenu(menu) {
    if (!menu) return;
    const list = menu.querySelector('.action-menu__list');
    if (!list) return;

    const margin = 8;
    const chatBox = document.getElementById('chat-box');
    const bounds = chatBox
        ? chatBox.getBoundingClientRect()
        : { left: 0, top: 0, right: window.innerWidth, bottom: window.innerHeight };

    list.style.left = '';
    list.style.right = '';
    list.style.transform = '';
    list.style.maxWidth = '';

    menu.classList.remove('open-down');
    let rect = list.getBoundingClientRect();
    const viewportTop = bounds.top + margin;

    // Default is opening upward. If there is not enough space above, flip downward.
    if (rect.top < viewportTop) {
        menu.classList.add('open-down');
        rect = list.getBoundingClientRect();
    }

    // Keep menu horizontally inside chat panel / viewport by applying a local translate.
    let offsetX = 0;
    const minLeft = bounds.left + margin;
    const maxRight = bounds.right - margin;

    if (rect.left < minLeft) {
        offsetX += (minLeft - rect.left);
    }
    if (rect.right > maxRight) {
        offsetX -= (rect.right - maxRight);
    }

    // If the menu itself is wider than available area, cap width so all actions stay visible.
    const maxWidth = Math.max(160, maxRight - minLeft);
    if (rect.width > maxWidth) {
        list.style.maxWidth = `${Math.floor(maxWidth)}px`;
        rect = list.getBoundingClientRect();
        offsetX = 0;
        if (rect.left < minLeft) offsetX += (minLeft - rect.left);
        if (rect.right > maxRight) offsetX -= (rect.right - maxRight);
    }

    if (offsetX !== 0) {
        list.style.transform = `translateX(${Math.round(offsetX)}px)`;
    }
}

function repositionOpenActionMenus() {
    document.querySelectorAll('[data-action-menu].is-open').forEach(function(menu) {
        positionActionMenu(menu);
    });
}

function closeAllActionMenus() {
    document.querySelectorAll('[data-action-menu].is-open').forEach(function(menu) {
        menu.classList.remove('is-open');
        menu.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded', 'false');
    });
}

window.closeAllActionMenus = closeAllActionMenus;

const actionMenuLifecycle = window.GroupChatLifecycle;
if (actionMenuLifecycle && !actionMenuLifecycle.destroyed && !window.__groupChatPostInteractionsDelegated) {
    window.__groupChatPostInteractionsDelegated = true;

    actionMenuLifecycle.on(document, 'click', function(event) {
        const reactionButton = event.target.closest('.reaction-buttons .btn-like, .reaction-buttons .btn-dislike');
        if (reactionButton) {
            const container = reactionButton.closest('.reaction-buttons');
            const blogId = container?.dataset.postId;
            if (container && blogId) {
                sendReaction(blogId, reactionButton.classList.contains('btn-like') ? '1' : '0', container);
            }
            return;
        }

        const toggle = event.target.closest('.action-menu__toggle');
        const menu = toggle?.closest('[data-action-menu]');
        if (toggle && menu) {
            event.preventDefault();
            event.stopPropagation();
            const isOpen = menu.classList.contains('is-open');
            closeAllActionMenus();
            menu.classList.toggle('is-open', !isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            if (!isOpen) requestAnimationFrame(function() { positionActionMenu(menu); });
            return;
        }

        const actionItem = event.target.closest('.action-menu__list button, .action-menu__list a');
        if (actionItem?.classList.contains('btn-reaction')) return;
        closeAllActionMenus();
    });

    actionMenuLifecycle.on(document, 'keydown', function(event) {
        if (event.key === 'Escape') closeAllActionMenus();
    });
    actionMenuLifecycle.on(window, 'resize', repositionOpenActionMenus);
    actionMenuLifecycle.on(document, 'scroll', repositionOpenActionMenus, true);
    actionMenuLifecycle.add(function() {
        window.__groupChatPostInteractionsDelegated = false;
    });
}


// Handle modal click - close if clicked outside dialog
window.handleModalClick = function(event, modalId) {
    // اگر روی خود dialog یا داخل dialog کلیک شده، نباید بسته شود
    const dialog = event.currentTarget.querySelector('.modal-shell__dialog');
    if (dialog && (event.target === dialog || dialog.contains(event.target))) {
        return;
    }
    
    // اگر روی backdrop یا خارج از dialog کلیک شده، modal را ببند
    if (modalId === 'postFormBox') {
        window.cancelPostForm();
    } else if (modalId === 'pollOptionsBox') {
        window.cancelPollForm();
    } else if (modalId === 'manageMembersModal') {
        if (typeof window.closeManageMembersModal === 'function') {
            window.closeManageMembersModal();
        }
    } else if (modalId === 'manageReportsModal') {
        if (typeof window.closeManageReportsModal === 'function') {
            window.closeManageReportsModal();
        }
    } else if (modalId === 'groupSettingsModal') {
        if (typeof window.closeGroupSettingsModal === 'function') {
            window.closeGroupSettingsModal();
        }
    }
};

// Make functions available in global scope
window.openBlogBox = function(){
    // حذف element #back اگر وجود دارد
    const back = document.querySelector('#back');
    if (back) {
        back.style.display = 'none';
    }
    
    const postFormBox = document.querySelector('#postFormBox');
    if (postFormBox) {
        postFormBox.style.display = 'flex';
        postFormBox.style.setProperty('display', 'flex', 'important');
    }
};

window.openPollBox = function(){
    // حذف element #back اگر وجود دارد
    const back = document.querySelector('#back');
    if (back) {
        back.style.display = 'none';
    }
    
    const pollOptionsBox = document.querySelector('#pollOptionsBox');
    if (pollOptionsBox) {
        pollOptionsBox.style.display = 'flex';
        pollOptionsBox.style.setProperty('display', 'flex', 'important');
    }
};

// Also keep them in local scope for backward compatibility
function openBlogBox(){
    window.openBlogBox();
}

function openPollBox(){
    window.openPollBox();
}

  function openElection2Box(){
    document.querySelector('#back').style='display: block'
    document.querySelector('#electionOptionsBox').style='display: block'
  }

  
  let openSkillListId = null;

  function toggleSkillList(pollId) {
      closeAllModals();
  
      const box = document.getElementById('skill-list-' + pollId);
      const back = document.getElementById('back');


      if (box && box.style.display !== 'flex') {
          box.style.display = 'flex';
          back.style.display = 'block';
          openSkillListId = pollId;
      } else {
          openSkillListId = null;
      }
  }


  
  function closeSkill() {
      document.querySelectorAll('.skill-list').forEach(el => el.style.display = 'none');
      const back = document.getElementById('back');
      if (back) back.style.display = 'none';
      openSkillListId = null;
  }
  
  // بعد از AJAX
  function reapplySkillListState() {
      if (openSkillListId !== null) {
          const box = document.getElementById('skill-list-' + openSkillListId);
          const back = document.getElementById('back');
          if (box) {
              box.style.display = 'flex';
          }
          if (back) {
              back.style.display = 'block';
          }
      }
  }
  
  
  
  // Make cancelPostForm available in global scope
  window.cancelPostForm = function(){
    const postFormBox = document.querySelector('#postFormBox');
    if (postFormBox) {
        postFormBox.style.display = 'none';
        postFormBox.style.setProperty('display', 'none', 'important');
    }
    // Also try to hide #back if it exists
    const back = document.querySelector('#back');
    if (back) {
        back.style.display = 'none';
    }
  };
  
  // Also keep it in local scope for backward compatibility
  function cancelPostForm(){
    window.cancelPostForm();
  }

  // Make cancelPollForm available in global scope
  window.cancelPollForm = function(){
    const pollOptionsBox = document.querySelector('#pollOptionsBox');
    if (pollOptionsBox) {
        pollOptionsBox.style.display = 'none';
        pollOptionsBox.style.setProperty('display', 'none', 'important');
    }
    // Also try to hide #back if it exists
    const back = document.querySelector('#back');
    if (back) {
        back.style.display = 'none';
    }
  };
  
  // Also keep it in local scope for backward compatibility
  function cancelPollForm(){
    window.cancelPollForm();
  }
  
  function cancelelectionForm(){
    document.querySelector('#back').style='display: none'
    document.querySelector('#electionOptionsBox').style='display: none'
  }
  
    // این کد حذف شد چون با منطق حفظ موقعیت scroll در chat.blade.php تداخل دارد
    // window.addEventListener('DOMContentLoaded', function () {
    //     const chatBox = document.getElementById('chat-box');
    //     chatBox.scrollTop = chatBox.scrollHeight;
    // });
    

    // صبر کن تا scroll restore کامل شود قبل از شروع polling
    // این مهم است چون polling نباید موقعیت scroll را تغییر دهد قبل از اینکه restore شود
    let pollingStarted = false;
    let lastMessageId = null; // آخرین پیام ID برای دریافت فقط پیام‌های جدید
    let pollingInterval = null;
    let lastPostId = 0; // آخرین پست ID برای posts polling
    const pollingDebug = groupChatDebug;
    const pollLog = (...args) => {
        if (pollingDebug) {
            console.log(...args);
        }
    };
    const pollWarn = (...args) => {
        if (pollingDebug) {
            console.warn(...args);
        }
    };
    const realtimeLifecycle = window.GroupChatLifecycle;
    const realtimeState = {
        initialized: false,
        connected: false,
        usingFallback: false,
        lastEventAt: 0,
        fallbackDelayMs: 15000,
        maxFallbackDelayMs: 120000,
        messageTimer: null,
        postTimer: null,
        reconcileTimer: null,
        fallbackMonitorTimer: null,
        lastSequence: Number(window.localStorage?.getItem('group-feed-sequence:' + window.groupId) || 0),
        syncingDelta: false,
        seenEventIds: new Set(),
        connectionStatus: 'connecting',
        deltaRetryMs: 1000
    };

    window.getGroupRealtimeState = function getGroupRealtimeState() {
        return { ...realtimeState };
    };

    function markRealtimeHealthy() {
        realtimeState.connected = true;
        realtimeState.usingFallback = false;
        realtimeState.lastEventAt = Date.now();
        realtimeState.fallbackDelayMs = 15000;
        realtimeState.deltaRetryMs = 1000;
        setConnectionStatus('online');
    }

    function shouldPollFallback() {
        if (document.hidden || navigator.onLine === false) return false;
        if (!realtimeState.initialized) return true;
        return realtimeState.usingFallback || !realtimeState.connected;
    }

    function setConnectionStatus(status) {
        realtimeState.connectionStatus = status;
        if (window.__groupChatModularFrontend && window.GroupChat?.store) {
            window.GroupChat.store.setState({ connection: status });
        }
        let indicator = document.getElementById('group-connection-status');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'group-connection-status';
            indicator.setAttribute('role', 'status');
            indicator.setAttribute('aria-live', 'polite');
            indicator.style.cssText = 'position:fixed;bottom:12px;left:12px;z-index:1000;padding:6px 10px;border-radius:14px;font-size:12px;background:#374151;color:#fff;';
            document.body.appendChild(indicator);
        }
        indicator.textContent = status === 'online' ? 'آنلاین' : (status === 'offline' ? 'آفلاین' : 'در حال اتصال');
        indicator.dataset.status = status;
    }

    function rememberRealtimeEvent(eventId) {
        if (!eventId) return true;
        if (realtimeState.seenEventIds.has(eventId)) return false;
        realtimeState.seenEventIds.add(eventId);
        if (realtimeState.seenEventIds.size > 1000) {
            realtimeState.seenEventIds.delete(realtimeState.seenEventIds.values().next().value);
        }
        return true;
    }

    function applyDeltaEvent(event) {
        if (!event) return;
        if (window.__groupChatModularFrontend && window.GroupChat?.reconciler) {
            const decision = window.GroupChat.reconciler.inspect(event);
            if (decision.action === 'ignore') return;
            if (decision.action === 'sync') {
                realtimeState.usingFallback = true;
                return;
            }
        } else if (!rememberRealtimeEvent(event.event_id)) return;
        const payload = event.payload || {};
        const contentType = payload.content_type;
        if (['message', 'file', 'voice'].includes(contentType)) {
            if (!document.getElementById('msg-' + payload.content_id)) renderMessageThroughPipeline({ id: payload.content_id, ...payload }, 'delta');
            updateLastMessageCursor(payload.content_id);
        } else if (['post', 'poll', 'comment'].includes(contentType)) {
            const applied = applyFeedItemThroughPipeline(contentType, 'create', payload, 'delta');
            if (!applied) realtimeState.usingFallback = true;
        } else {
            realtimeState.usingFallback = true;
        }
        realtimeState.lastSequence = Math.max(realtimeState.lastSequence, Number(event.sequence || 0));
        window.GroupChat?.reconciler?.advance(realtimeState.lastSequence);
        window.localStorage?.setItem('group-feed-sequence:' + window.groupId, String(realtimeState.lastSequence));
    }

    async function syncGroupDelta() {
        if (realtimeState.syncingDelta || !window.groupId || navigator.onLine === false) return;
        realtimeState.syncingDelta = true;
        try {
            let hasMore = true;
            while (hasMore) {
                const response = await groupChatFetch(`/api/groups/${window.groupId}/feed/delta?after_sequence=${realtimeState.lastSequence}&limit=100`);
                if (response.status === 409) return;
                if (!response.ok) throw new Error('Delta sync failed: ' + response.status);
                const data = await response.json();
                (data.events || []).forEach(applyDeltaEvent);
                hasMore = Boolean(data.has_more) && (data.events || []).length > 0;
            }
        } catch (error) {
            realtimeState.usingFallback = true;
            debugWarn('Delta sync failed; fallback enabled.', error);
            if (!document.hidden && navigator.onLine !== false) {
                const jitter = Math.floor(Math.random() * Math.max(250, realtimeState.deltaRetryMs * 0.25));
                realtimeLifecycle.timeout(syncGroupDelta, realtimeState.deltaRetryMs + jitter);
                realtimeState.deltaRetryMs = Math.min(30000, realtimeState.deltaRetryMs * 2);
            }
        } finally {
            realtimeState.syncingDelta = false;
        }
    }

    function applyRealtimeEnvelope(event) {
        if (!event) return;
        if (window.__groupChatModularFrontend && window.GroupChat?.reconciler) {
            const decision = window.GroupChat.reconciler.inspect(event, { commit: false });
            if (decision.action === 'ignore') return;
            if (decision.action === 'sync') {
                syncGroupDelta();
                return;
            }
        } else if (event.event_id && realtimeState.seenEventIds.has(event.event_id)) return;
        const sequence = Number(event.sequence || 0);
        if (sequence > realtimeState.lastSequence + 1) {
            syncGroupDelta();
            return;
        }
        // Fetch canonical DTO even for an in-order notification.
        syncGroupDelta();
    }

    realtimeLifecycle.on(window, 'online', function() { setConnectionStatus('connecting'); syncGroupDelta(); });
    realtimeLifecycle.on(window, 'offline', function() { realtimeState.connected = false; realtimeState.usingFallback = false; setConnectionStatus('offline'); });
    realtimeLifecycle.on(document, 'visibilitychange', function() {
        if (!document.hidden && navigator.onLine !== false) syncGroupDelta();
    });
    setConnectionStatus(navigator.onLine === false ? 'offline' : 'connecting');

    function getFeedElement() {
        return document.getElementById('chat-box') || document.getElementById('group-feed');
    }

    function appendBeforeTypingIndicator(feedEl, node) {
        if (!feedEl || !node) return;

        const indicator = document.getElementById('group-typing-indicator');
        if (indicator && indicator.parentElement === feedEl) {
            feedEl.insertBefore(node, indicator);
            return;
        }

        feedEl.appendChild(node);
    }

    function updateLastMessageCursor(messageId) {
        const numericId = parseInt(messageId, 10);
        if (numericId && (!lastMessageId || numericId > lastMessageId)) {
            lastMessageId = numericId;
        }
    }

    function updateLastPostCursor(postId) {
        const numericId = parseInt(postId, 10);
        if (numericId && numericId > lastPostId) {
            lastPostId = numericId;
        }
    }

    function appendRenderedFeedHtml(html, preferredId, type) {
        if (!html) return false;
        if (type === 'post' && preferredId && document.getElementById('blog-' + preferredId)) return false;
        if (type === 'poll' && preferredId && document.getElementById('poll-' + preferredId)) return false;

        const feedEl = getFeedElement();
        if (!feedEl) return false;

        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const newEl = tmp.firstElementChild;
        if (!newEl) return false;

        appendBeforeTypingIndicator(feedEl, newEl);
        if (typeof addReactionButton === 'function') {
            newEl.querySelectorAll?.('[data-message-id]').forEach(function(bubble) {
                addReactionButton(bubble);
            });
        }
        startPollCountdowns();
        return true;
    }

    function replaceRenderedFeedHtml(selector, html) {
        if (!selector || !html) return false;
        const existing = document.querySelector(selector);
        if (!existing) return false;

        const wrapper = existing.closest('.post-wrapper, .poll-wrapper') || existing.parentElement || existing;
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const newEl = tmp.firstElementChild;
        if (!newEl) return false;

        wrapper.replaceWith(newEl);
        startPollCountdowns();
        return true;
    }

    function fadeRemoveElement(element) {
        if (!element) return;
        element.style.transition = 'opacity 0.3s ease-out';
        element.style.opacity = '0';
        setTimeout(function() { element.remove(); }, 300);
    }

    function removeMessageDom(messageId) {
        const msgRow = document.getElementById('msg-' + messageId);
        if (msgRow) fadeRemoveElement(msgRow);
        const parentInput = document.getElementById('parent_id');
        if (parentInput && parentInput.value == messageId && typeof cancelReply === 'function') {
            cancelReply();
        }
    }

    function updateMessageContentDom(messageId, htmlContent, edited) {
        const bubble = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
        if (!bubble) return false;
        const contentEl = bubble.querySelector('.message-content');
        if (contentEl) contentEl.innerHTML = htmlContent || '';
        bubble.setAttribute('data-content-raw', (htmlContent || '').replace(/<[^>]*>/g, ''));
        if (edited && !bubble.querySelector('.edited-icon')) {
            const ts = bubble.querySelector('.message-timestamp');
            if (ts) {
                const badge = document.createElement('span');
                badge.className = 'edited-icon';
                badge.style.cssText = 'font-size:10px;color:#9ca3af;margin-left:4px;';
                badge.textContent = '(ویرایش شده)';
                ts.prepend(badge);
            }
        }
        return true;
    }

    function updateMessageReactionsDom(messageId, reactions) {
        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageElement) return false;
        const emojis = {
            like: '👍',
            love: '❤️',
            laugh: '😂',
            wow: '😮',
            sad: '😢',
            angry: '😠',
            '👍': '👍',
            '❤️': '❤️',
            '😂': '😂',
            '😮': '😮',
            '😢': '😢',
            '🔥': '🔥',
            '👎': '👎'
        };
        let reactionDisplay = messageElement.querySelector('.message-reactions');
        if (!reactions || reactions.length === 0) {
            if (reactionDisplay) reactionDisplay.remove();
            return true;
        }
        if (!reactionDisplay) {
            reactionDisplay = document.createElement('div');
            reactionDisplay.className = 'message-reactions';
            reactionDisplay.style.cssText = 'display:flex;gap:4px;margin-top:4px;flex-wrap:wrap;';
            const timestamp = messageElement.querySelector('.message-timestamp');
            if (timestamp) {
                const holder = timestamp.querySelector('div[style*="justify-content: center"]') || timestamp;
                holder.appendChild(reactionDisplay);
            } else {
                messageElement.appendChild(reactionDisplay);
            }
        }
        reactionDisplay.innerHTML = reactions.map(function(r) {
            const type = r.type || r.reaction_type || '';
            const count = r.count || 0;
            const emoji = emojis[type] || type || '👍';
            return `<button type="button" class="reaction-badge" style="background:#f0f0f0;padding:2px 6px;border:0;border-radius:12px;font-size:12px;cursor:pointer;" data-legacy-chat-action="reaction" data-message-id="${messageId}" data-reaction-type="${type}">${emoji} ${count}</button>`;
        }).join('');
        return true;
    }

    window.GroupChatLegacyMessageMutations = {
        edit(item) {
            return updateMessageContentDom(item.content_id || item.message_id || item.id, item.content || item.message || '', item.edited !== false);
        },
        delete(item) {
            removeMessageDom(item.content_id || item.message_id || item.id);
            return true;
        },
        reaction(item) {
            return updateMessageReactionsDom(item.content_id || item.message_id || item.id, item.reactions || []);
        },
        'mark-read'(item) {
            return updateMessageReadReceiptDom(item.content_id || item.message_id || item.id, item.read_count || 0);
        }
    };

    function mutateMessageThroughPipeline(action, payload, source) {
        const item = {
            ...(payload || {}),
            action,
            content_type: 'message',
            content_id: payload?.content_id || payload?.message_id || payload?.id
        };
        if (window.__groupChatModularFrontend && window.GroupChat?.feed) {
            return window.GroupChat.feed.mutate(item, source);
        }
        const adapter = window.GroupChatLegacyMessageMutations[action];
        return typeof adapter === 'function' ? adapter(item) : false;
    }

    function removePostDom(postId) {
        const el = document.getElementById('blog-' + postId);
        fadeRemoveElement(el ? (el.closest('.post-wrapper') || el.parentElement || el) : null);
    }

    function removePollDom(pollId) {
        const el = document.getElementById('poll-' + pollId) || document.querySelector(`[data-poll-id="${pollId}"]`);
        fadeRemoveElement(el ? (el.closest('.poll-wrapper') || el) : null);
    }

    window.GroupChatLegacyFeedRenderers = {
        post: {
            render(item) {
                return appendRenderedFeedHtml(item.html, item.content_id || item.post_id || item.id, 'post');
            },
            update(item) {
                const id = item.content_id || item.post_id || item.id;
                return item.html
                    ? replaceRenderedFeedHtml('#blog-' + id, item.html)
                    : updatePostFieldsDom({ ...item, id });
            },
            delete(item) {
                removePostDom(item.content_id || item.post_id || item.id);
                return true;
            },
            reaction(item) {
                const id = item.content_id || item.post_id || item.id;
                const container = document.querySelector(`.reaction-buttons[data-post-id="${id}"]`);
                if (!container) return false;
                const like = container.querySelector('.like-count');
                const dislike = container.querySelector('.dislike-count');
                if (like) like.textContent = item.likes ?? 0;
                if (dislike) dislike.textContent = item.dislikes ?? 0;
                return true;
            },
            read(item) {
                return updatePostReadReceiptDom(item.content_id || item.post_id || item.id, item.read_count || 0);
            },
        },
        poll: {
            render(item) {
                return appendRenderedFeedHtml(item.html, item.content_id || item.poll_id || item.id, 'poll');
            },
            update(item) {
                const id = item.content_id || item.poll_id || item.id;
                return replaceRenderedFeedHtml('#poll-' + id + ', [data-poll-id="' + id + '"]', item.html);
            },
            delete(item) {
                removePollDom(item.content_id || item.poll_id || item.id);
                return true;
            },
            read(item) {
                return updatePollReadReceiptDom(item.content_id || item.poll_id || item.id, item.read_count || 0);
            },
        },
        comment: {
            render(item) {
                const postId = item.blog_id || item.post_id;
                const link = postId ? document.querySelector(`#blog-${postId} .post-card__comments`) : null;
                if (link && Number.isFinite(Number(item.comments_count))) {
                    link.replaceChildren();
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-comment-dots';
                    link.append(icon, document.createTextNode(` نظر دهید (${Number(item.comments_count)})`));
                }
                document.dispatchEvent(new CustomEvent('group-comment-updated', { detail: item }));
                return Boolean(link);
            },
            update(item) {
                document.dispatchEvent(new CustomEvent('group-comment-updated', { detail: item }));
                return true;
            },
            delete(item) {
                document.dispatchEvent(new CustomEvent('group-comment-updated', { detail: item }));
                return true;
            },
            reaction(item) {
                document.dispatchEvent(new CustomEvent('group-comment-updated', { detail: item }));
                return true;
            },
        },
    };

    function applyFeedItemThroughPipeline(contentType, operation, payload, source) {
        const contentId = payload?.content_id || payload?.[contentType + '_id'] || payload?.id;
        const item = { ...(payload || {}), content_type: contentType, content_id: contentId, action: operation };
        const adapter = window.GroupChatLegacyFeedRenderers?.[contentType];
        if (window.__groupChatModularFrontend && window.GroupChat?.feed) {
            return operation === 'create'
                ? window.GroupChat.feed.apply([item], source)[0] || false
                : window.GroupChat.feed.mutate(item, source);
        }
        return operation === 'create'
            ? adapter?.render?.(item, { source }) || false
            : adapter?.[operation]?.(item, { source }) || false;
    }

    window.GroupChatFeedBridge = Object.freeze({
        create(contentType, payload, source = 'local') {
            const applied = applyFeedItemThroughPipeline(contentType, 'create', payload, source);
            if (applied && contentType === 'post') {
                updateLastPostCursor(payload?.post_id || payload?.content_id || payload?.id);
            }
            return applied;
        },
        mutate(contentType, operation, payload, source = 'local') {
            return applyFeedItemThroughPipeline(contentType, operation, payload, source);
        },
    });

    const remoteTypingUsers = new Map();
    let typingClearTimer = null;

    function getTypingIndicatorElement() {
        const feed = getFeedElement();
        if (!feed) return null;

        let indicator = document.getElementById('group-typing-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'group-typing-indicator';
            indicator.className = 'typing-indicator';
            indicator.style.display = 'none';
            feed.appendChild(indicator);
        }
        return indicator;
    }

    function renderTypingIndicator() {
        const indicator = getTypingIndicatorElement();
        if (!indicator) return;

        const feed = getFeedElement();
        if (feed && indicator.parentElement === feed && feed.lastElementChild !== indicator) {
            feed.appendChild(indicator);
        }

        const names = Array.from(remoteTypingUsers.values()).filter(Boolean);
        if (names.length === 0) {
            indicator.style.display = 'none';
            indicator.textContent = '';
            return;
        }

        let message = 'در حال تایپ...';
        if (names.length === 1) {
            message = `${names[0]} در حال تایپ...`;
        } else if (names.length === 2) {
            message = `${names[0]} و ${names[1]} در حال تایپ...`;
        } else {
            message = `${names[0]} و ${names.length - 1} نفر دیگر در حال تایپ...`;
        }

        indicator.textContent = message;
        indicator.style.display = 'block';
    }

    function scheduleTypingCleanup() {
        clearTimeout(typingClearTimer);
        typingClearTimer = setTimeout(function() {
            remoteTypingUsers.clear();
            renderTypingIndicator();
        }, 3000);
    }

    function updateMessageReadReceiptDom(messageId, readCount) {
        if (!messageId) return false;
        const bubble = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
        if (!bubble) return false;

        const receipt = bubble.querySelector('.read-receipt span');
        if (!receipt) return false;

        const count = Number.isFinite(Number(readCount)) ? Number(readCount) : 0;
        if (count > 0) {
            receipt.style.color = '#10b981';
            receipt.innerHTML = `<i class="fas fa-check-double"></i> ${count} نفر خوانده‌اند`;
        } else {
            receipt.style.color = '#9ca3af';
            receipt.innerHTML = '<i class="fas fa-check"></i> ارسال شده';
        }
        return true;
    }

    function updatePostReadReceiptDom(postId, readCount) {
        if (!postId) return false;
        const post = document.getElementById('blog-' + postId);
        if (!post) return false;

        const receipt = post.querySelector('.post-read-receipt span');
        if (!receipt) return false;

        const count = Number.isFinite(Number(readCount)) ? Number(readCount) : 0;
        if (count > 0) {
            receipt.style.color = '#10b981';
            receipt.innerHTML = `<i class="fas fa-check-double"></i> ${count} نفر دیده‌اند`;
        } else {
            receipt.style.color = '#9ca3af';
            receipt.innerHTML = '<i class="fas fa-check"></i> ارسال شده';
        }
        return true;
    }

    function updatePollReadReceiptDom(pollId, readCount) {
        if (!pollId) return false;
        const poll = document.getElementById('poll-' + pollId);
        if (!poll) return false;

        const receipt = poll.querySelector('.poll-read-receipt span');
        if (!receipt) return false;

        const count = Number.isFinite(Number(readCount)) ? Number(readCount) : 0;
        if (count > 0) {
            receipt.style.color = '#10b981';
            receipt.innerHTML = `<i class="fas fa-check-double"></i> ${count} نفر دیده‌اند`;
        } else {
            receipt.style.color = '#9ca3af';
            receipt.innerHTML = '<i class="fas fa-check"></i> ارسال شده';
        }
        return true;
    }

    function applyMessageEvent(event) {
        if (!event) return;
        markRealtimeHealthy();
        debugLog('[typing] incoming group.message.updated', {
            action: event.action || event?.payload?.action || null,
            actor_id: event.actor_id || null,
            payload: event.payload || null,
        });
        if (event.actor_id && event.actor_id === window.authUserId) return;

        if (event.message) {
            const message = event.message;
            if (!message.id || document.getElementById('msg-' + message.id)) {
                updateLastMessageCursor(message.id);
                return;
            }
            renderMessageThroughPipeline(message, 'websocket-legacy');
            updateLastMessageCursor(message.id);
            return;
        }

        const payload = event.payload || {};
        const action = event.action || payload.action || '';

        if (action === 'typing') {
            const typingUserId = payload.user_id || payload.id;
            if (!typingUserId || typingUserId === window.authUserId) {
                debugLog('[typing] ignored typing event', {
                    reason: !typingUserId ? 'missing user id' : 'self actor',
                    typingUserId,
                    authUserId: window.authUserId,
                });
                return;
            }

            const isTyping = payload.is_typing !== false;
            if (isTyping) {
                remoteTypingUsers.set(typingUserId, payload.user_name || 'کاربر');
                renderTypingIndicator();
                scheduleTypingCleanup();
                debugLog('[typing] show typing indicator', {
                    typingUserId,
                    user_name: payload.user_name || 'کاربر',
                    users: Array.from(remoteTypingUsers.entries()),
                });
            } else {
                remoteTypingUsers.delete(typingUserId);
                renderTypingIndicator();
                debugLog('[typing] hide typing for user', {
                    typingUserId,
                    users: Array.from(remoteTypingUsers.entries()),
                });
            }
            return;
        }

        const messageId = payload.message_id || payload.id;
        if (!messageId) return;

        if (['edit', 'delete', 'reaction', 'mark-read'].includes(action)) {
            mutateMessageThroughPipeline(action, { ...payload, message_id: messageId }, 'websocket-legacy');
        } else if (action === 'pin') {
            document.dispatchEvent(new CustomEvent('group-message-pin-updated', { detail: payload }));
        }
    }

    function applyFeedEvent(event) {
        if (!event) return;
        markRealtimeHealthy();

        const payload = event.payload || {};
        const action = event.action || payload.action || '';
        const isCurrentActor = event.actor_id && event.actor_id === window.authUserId;

        // Poll events are safe/idempotent to apply for all clients (including actor)
        // so local UI does not get stale when optimistic updates diverge.
        if (isCurrentActor && !String(action).startsWith('poll_')) return;

        const match = /^(post|poll|comment)_(created|updated|deleted|reaction|read)$/.exec(action);
        if (!match) return;
        const contentType = match[1];
        const operation = { created: 'create', updated: 'update', deleted: 'delete' }[match[2]] || match[2];
        const applied = applyFeedItemThroughPipeline(contentType, operation, payload, 'websocket-legacy');
        if (applied && contentType === 'post') updateLastPostCursor(payload.post_id || payload.content_id || payload.id);
    }

    window.initGroupRealtimeListeners = function initGroupRealtimeListeners() {
        if (realtimeState.initialized || !window.groupId || !window.Echo || typeof window.Echo.private !== 'function') {
            return realtimeState.initialized;
        }

        try {
            const channel = window.Echo.private(`group.${window.groupId}`);
            channel
                .subscribed(function() {
                    setConnectionStatus('connecting');
                    syncGroupDelta().finally(markRealtimeHealthy);
                })
                .error(function(error) {
                    console.warn('Realtime channel subscription error; polling fallback remains active.', error);
                    realtimeState.connected = false;
                    realtimeState.usingFallback = true;
                    setConnectionStatus(navigator.onLine === false ? 'offline' : 'connecting');
                })
                .listen('.group.message.created', applyMessageEvent)
                .listen('.group.message.updated', applyMessageEvent)
                .listen('.group.feed.updated', applyFeedEvent)
                .listen('.group.realtime.event', applyRealtimeEnvelope)
                .listen('.group.poll.updated', function(event) {
                    markRealtimeHealthy();
                    const poll = (event && (event.poll || event.payload)) || {};
                    if (poll.id || poll.poll_id) updatePollUI(poll);
                })
                .listen('.group.election.updated', function(event) {
                    markRealtimeHealthy();
                    document.dispatchEvent(new CustomEvent('group-election-updated', { detail: event || {} }));
                });

            realtimeState.initialized = true;
            realtimeState.usingFallback = false;

            if (window.Echo.connector && window.Echo.connector.pusher && window.Echo.connector.pusher.connection) {
                const connection = window.Echo.connector.pusher.connection;
                if (connection.state === 'connected') {
                    markRealtimeHealthy();
                }
                connection.bind('connected', markRealtimeHealthy);
                connection.bind('unavailable', function() {
                    realtimeState.connected = false;
                    realtimeState.usingFallback = true;
                });
                connection.bind('disconnected', function() {
                    realtimeState.connected = false;
                    realtimeState.usingFallback = true;
                    setConnectionStatus(navigator.onLine === false ? 'offline' : 'connecting');
                });
                connection.bind('error', function() {
                    realtimeState.connected = false;
                    realtimeState.usingFallback = true;
                });
            }
            return true;
        } catch (error) {
            console.warn('Realtime subscription failed; polling fallback remains active.', error);
            realtimeState.initialized = false;
            realtimeState.connected = false;
            realtimeState.usingFallback = true;
            return false;
        }
    };
    
    // تابع برای دریافت آخرین message ID از صفحه
    function getLastMessageId() {
        const chatBox = document.getElementById('chat-box');
        if (!chatBox) {
            pollWarn('⚠️ chat-box not found');
            return null;
        }
        
        // جستجو در message-row و message-bubble
        const messages = chatBox.querySelectorAll('.message-row[data-message-id], .message-bubble[data-message-id], [data-message-id]');
        if (messages.length === 0) {
            pollWarn('⚠️ No messages found in chat-box');
            return null;
        }
        
        let maxId = 0;
        messages.forEach(msg => {
            const msgId = parseInt(msg.getAttribute('data-message-id'));
            if (msgId && !isNaN(msgId) && msgId > maxId) {
                maxId = msgId;
            }
        });
        
        const result = maxId > 0 ? maxId : null;
        pollLog('📋 getLastMessageId result:', result, 'from', messages.length, 'messages');
        return result;
    }
    
    // تعریف startPolling در scope global برای دسترسی از DOMContentLoaded
    window.startPolling = function startPolling() {
        if (pollingStarted) {
            pollLog('⚠️ Polling already started');
            return;
        }
        
        // بررسی وجود groupId - فقط از window.groupId استفاده می‌کنیم
        if (typeof window.groupId === 'undefined' || !window.groupId) {
            console.error('❌ window.groupId not found! Cannot start polling. window.groupId:', window.groupId);
            console.log('🔍 Available window properties:', Object.keys(window).filter(k => k.includes('group')));
            return;
        }
        
        const currentGroupId = window.groupId;
        pollLog('🚀 Starting polling for group:', currentGroupId);
        
        // دریافت آخرین message ID از صفحه فعلی
        lastMessageId = getLastMessageId();
        pollLog('📋 Initial lastMessageId:', lastMessageId);
        
        // بررسی کن که آیا scroll restore شده است
        // اگر بعد از 5 ثانیه هنوز restore نشده، polling را شروع کن
        let attempts = 0;
        const maxAttempts = 10;
        function waitForScrollRestore() {
            attempts++;
            if (window.scrollPositionRestored || attempts >= maxAttempts) {
                pollingStarted = true;
                pollLog('✅ Polling started after', attempts, 'attempts');
                
                // حالا polling را شروع کن - برای تجربه نزدیک‌تر به بلادرنگ
                let _isPollingPending = false;
                const messagePollIntervalMs = 1000;
                pollingInterval = realtimeLifecycle.interval(function() {
                    if (!shouldPollFallback()) {
                        return;
                    }
                    // اگر درخواست قبلی هنوز جواب نداده، skip کن
                    if (_isPollingPending) {
                        pollLog('⏭️ Skipping poll - previous request still pending');
                        return;
                    }
                    // دریافت groupId از window
                    if (typeof window.groupId === 'undefined' || !window.groupId) {
                        console.error('❌ window.groupId not found during polling!');
                        return;
                    }
                    
                    const currentGroupId = window.groupId;
                    
                    // دریافت آخرین message ID قبل از درخواست
                    const currentLastId = getLastMessageId();
                    const requestLastId = lastMessageId || currentLastId;
                    
                    pollLog('🔄🔄🔄 POLLING REQUEST 🔄🔄🔄');
                    pollLog('Group ID:', currentGroupId);
                    pollLog('Last Message ID:', requestLastId);
                    pollLog('URL:', '/api/groups/' + currentGroupId + '/messages');
                    pollLog('Timestamp:', new Date().toISOString());
                    
                    // بررسی وجود jQuery
                    if (typeof $ === 'undefined' || typeof $.ajax === 'undefined') {
                        console.error('❌ jQuery not loaded! Cannot make AJAX request.');
                        return;
                    }
                    
                    _isPollingPending = true;
                    $.ajax({
                        url: '/api/groups/' + currentGroupId + '/messages',
                        method: 'GET',
                        data: {
                            last_message_id: requestLastId
                        },
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        },
                        dataType: 'json',
                        success: function(response) {
                            // FORCE LOG - همیشه نمایش بده
                            pollLog('✅✅✅ POLLING RESPONSE RECEIVED ✅✅✅');
                            pollLog('Response:', response);
                            pollLog('Response type:', typeof response);
                            pollLog('Response.messages:', response?.messages);
                            pollLog('Response.messages length:', response?.messages?.length);
                            
                            // Store the current scroll position
                            const chatBox = document.getElementById('chat-box');
                            if (!chatBox) {
                                console.error('❌ chat-box element not found!');
                                return;
                            }
                            
                            // بررسی دقیق‌تر "در پایین بودن" - فقط اگر کاربر واقعاً خودش به پایین رفته باشد
                            const scrollBottom = chatBox.scrollHeight - chatBox.scrollTop;
                            const threshold = 50;
                            const isScrolledToBottom = scrollBottom <= chatBox.clientHeight + threshold;
                            
                            // Store the current edit form state if it exists
                            const activeEditForm = document.querySelector('.edit-form[style*="display: block"]');
                            const activeEditFormId = activeEditForm ? activeEditForm.id : null;
                            const activeEditContent = activeEditForm ? document.getElementById(`edit-message-${activeEditFormId.split('-')[2]}`).value : null;
                            
                            // Parse JSON response
                            let newMessages = [];
                            if (response && response.messages && Array.isArray(response.messages)) {
                                // فقط پیام‌های جدید را فیلتر کن
                                const existingMessageIds = new Set();
                                document.querySelectorAll('[data-message-id]').forEach(msg => {
                                    const msgId = parseInt(msg.getAttribute('data-message-id'));
                                    if (msgId) {
                                        existingMessageIds.add(msgId);
                                    }
                                });
                                
                                newMessages = response.messages.filter(msg => {
                                    return msg && msg.type === 'message' && msg.id && !existingMessageIds.has(msg.id);
                                });
                                
                                pollLog('📨 New messages found:', newMessages.length, 'out of', response.messages.length);
                            } else {
                                pollWarn('⚠️ Invalid response format:', response);
                            }
                            
                            // Append only new messages
                            const hadNewMessages = newMessages.length > 0;
                            if (hadNewMessages) {
                                pollLog('➕ Adding', newMessages.length, 'new messages');
                            }
                            
                            newMessages.forEach(function(messageData, index) {
                                pollLog(`📝 Processing message ${index + 1}/${newMessages.length}:`, messageData);
                                
                                // استفاده از تابع appendMessage که قبلاً تعریف شده
                                if (typeof appendMessage === 'function') {
                                    try {
                                        pollLog('🔄 Calling appendMessage for message ID:', messageData.id);
                                        renderMessageThroughPipeline(messageData, 'polling');
                                        pollLog('✅✅✅ Message successfully added to DOM:', messageData.id);
                                        
                                        // به‌روزرسانی lastMessageId
                                        if (messageData.id && (!lastMessageId || messageData.id > lastMessageId)) {
                                            lastMessageId = messageData.id;
                                            pollLog('🔄 Updated lastMessageId to:', lastMessageId);
                                        }
                                    } catch (error) {
                                        console.error('❌❌❌ CRITICAL ERROR adding message:', error);
                                        console.error('Error stack:', error.stack);
                                        console.error('Message data:', messageData);
                                        // نمایش alert برای debugging
                                        debugWarn('Append message failed:', error.message);
                                    }
                                } else {
                                    console.error('❌❌❌ appendMessage function NOT FOUND!');
                                    console.error('Available functions:', Object.keys(window).filter(k => k.includes('append')));
                                    debugWarn('appendMessage function not found during polling update');
                                }
                            });
                            
                            // به‌روزرسانی lastMessageId از response
                            if (response.latest_message_id && (!lastMessageId || response.latest_message_id > lastMessageId)) {
                                lastMessageId = response.latest_message_id;
                            }

                            // ===== Handle edited messages =====
                            if (response.updated_messages && Array.isArray(response.updated_messages)) {
                                response.updated_messages.forEach(function(msg) {
                                    mutateMessageThroughPipeline('edit', msg, 'polling');
                                });
                            }

                            // ===== Handle deleted messages =====
                            if (response.deleted_message_ids && Array.isArray(response.deleted_message_ids)) {
                                response.deleted_message_ids.forEach(function(msgId) {
                                    mutateMessageThroughPipeline('delete', { id: msgId }, 'polling');
                                });
                            }
                            
                            reapplySkillListState();
                            startPollCountdowns();

                            // Restore the edit form state if it existed
                            if (activeEditFormId && activeEditContent) {
                                const editForm = document.getElementById(activeEditFormId);
                                const editInput = document.getElementById(`edit-message-${activeEditFormId.split('-')[2]}`);
                                if (editForm && editInput) {
                                    editForm.style.display = 'block';
                                    editInput.value = activeEditContent;
                                    editInput.focus();
                                }
                            }

                            // Scroll to bottom ONLY if:
                            // 1. Scroll restore کامل شده است
                            // 2. کاربر واقعاً خودش به پایین رفته باشد (نه در لود اولیه)
                            // 3. پیام جدید اضافه شده باشد
                            if (window.scrollPositionRestored && isScrolledToBottom && hadNewMessages) {
                                chatBox.scrollTop = chatBox.scrollHeight;
                            }
                        },
                        complete: function() {
                            _isPollingPending = false;
                        },
                        error: function(xhr, status, error) {
                            console.error('❌❌❌ POLLING ERROR ❌❌❌');
                            console.error('Status:', xhr.status);
                            console.error('Status Text:', xhr.statusText);
                            console.error('Error:', error);
                            console.error('Response Text:', xhr.responseText);
                            console.error('Response Headers:', xhr.getAllResponseHeaders());
                            
                            // اگر response text وجود دارد، سعی کن parse کن
                            if (xhr.responseText) {
                                try {
                                    const parsed = JSON.parse(xhr.responseText);
                                    console.error('Parsed error response:', parsed);
                                } catch (e) {
                                    console.error('Could not parse error response as JSON');
                                }
                            }
                            
                            // در صورت خطا، polling را ادامه بده (ممکن است مشکل موقتی باشد)
                        }
                    });
                }, messagePollIntervalMs);

                // ===== Posts polling =====
                var _isPostPollPending = false;
                // مقدار اولیه lastPostId را از DOM بخوان
                if (lastPostId === 0) {
                    var domPosts = document.querySelectorAll('[id^="blog-"]');
                    domPosts.forEach(function(el) {
                        var pid = parseInt((el.id || '').replace('blog-', ''));
                        if (pid > lastPostId) lastPostId = pid;
                    });
                }
                realtimeState.postTimer = realtimeLifecycle.interval(function() {
                    if (!shouldPollFallback()) return;
                    if (_isPostPollPending) return;
                    if (!window.groupId) return;
                    _isPostPollPending = true;
                    fetch('/api/groups/' + window.groupId + '/posts/feed?after_id=' + lastPostId, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function(r) { return r.ok ? r.json() : null; })
                    .then(function(data) {
                        if (!data) return;
                        // Handle new posts
                        if (data.posts && data.posts.length) {
                            data.posts.forEach(function(p) {
                                if (!p.html) return;
                                window.GroupChatFeedBridge.create('post', p, 'polling-fallback');
                            });
                        }
                        updateLastPostCursor(data.latest_post_id);
                        // Handle deleted posts
                        if (data.deleted_post_ids && data.deleted_post_ids.length) {
                            data.deleted_post_ids.forEach(function(pid) {
                                window.GroupChatFeedBridge.mutate('post', 'delete', { id: pid }, 'polling-fallback');
                            });
                        }
                        // Handle updated posts
                        if (data.updated_posts && data.updated_posts.length) {
                            data.updated_posts.forEach(function(p) {
                                if (!p.html) return;
                                window.GroupChatFeedBridge.mutate('post', 'update', p, 'polling-fallback');
                            });
                        }
                    })
                    .catch(function() {})
                    .finally(function() { _isPostPollPending = false; });
                }, 3000);

                // ===== Reconcile check every 10s: ask server which visible posts were deleted =====
                var _isReconcilePending = false;
                realtimeState.reconcileTimer = realtimeLifecycle.interval(function() {
                    if (!shouldPollFallback()) return;
                    if (_isReconcilePending || !window.groupId) return;
                    // collect all post IDs currently visible in DOM
                    var visibleIds = []; 

                    document.querySelectorAll('[id^="blog-"]').forEach(function(el) {
                        var pid = parseInt(el.id.replace('blog-', ''));
                        if (pid > 0) visibleIds.push(pid);
                    });
                    if (!visibleIds.length) return;
                    _isReconcilePending = true;
                    var csrfToken = (typeof getCsrfToken === 'function') ? getCsrfToken() :
                        (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    fetch('/api/groups/' + window.groupId + '/posts/reconcile', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ ids: visibleIds })
                    })
                    .then(function(r) { return r.ok ? r.json() : null; })
                    .then(function(data) {
                        if (!data || !data.deleted_ids || !data.deleted_ids.length) return;
                        data.deleted_ids.forEach(function(pid) {
                            window.GroupChatFeedBridge.mutate('post', 'delete', { id: pid }, 'reconcile-fallback');
                        });
                    })
                    .catch(function() {})
                    .finally(function() { _isReconcilePending = false; });
                }, 10000); // every 10 seconds
            } else {
                realtimeLifecycle.timeout(waitForScrollRestore, 500);
            }
        }
        realtimeLifecycle.timeout(waitForScrollRestore, 500); // هر 500ms چک کن
    }

document.addEventListener('DOMContentLoaded', function() {
    const chatBox = document.getElementById('chat-box');
    const form = document.getElementById('chatForm');
    const voiceFileInput = document.getElementById('voice-file-input');
    const voiceFilePreview = document.getElementById('voice-file-preview');
    const voiceFileName = document.getElementById('voice-file-name');
    const voiceFileSize = document.getElementById('voice-file-size');
    const voiceFileRemove = document.getElementById('voice-file-remove');

    // Format file size helper
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 بایت';
        const k = 1024;
        const sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    const updateVoiceFilePreview = (file) => {
        if (!voiceFilePreview) return;

        if (file) {
            if (voiceFileName) {
                voiceFileName.textContent = file.name;
            }
            if (voiceFileSize) {
                voiceFileSize.textContent = formatFileSize(file.size);
            }
            voiceFilePreview.style.display = 'flex';
            voiceFilePreview.style.setProperty('display', 'flex', 'important');
        } else {
            voiceFilePreview.style.display = 'none';
            voiceFilePreview.style.setProperty('display', 'none', 'important');
            if (voiceFileName) voiceFileName.textContent = '';
            if (voiceFileSize) voiceFileSize.textContent = '';
        }
    };

    voiceFileInput?.addEventListener('change', () => {
        updateVoiceFilePreview(voiceFileInput.files?.[0] || null);
    });

    voiceFileRemove?.addEventListener('click', (event) => {
        event.preventDefault();
        if (voiceFileInput) {
            voiceFileInput.value = '';
        }
        updateVoiceFilePreview(null);
    });

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            debugLog('[TIMING] JS T0 - form submit started:', Date.now());

            const ckeditor = window.CKEDITOR;

            // Sync CKEditor قبل از خواندن محتوا
            if (ckeditor && ckeditor.instances) {
                for (const instance of Object.values(ckeditor.instances)) {
                    instance.updateElement();
                }
            }
            
            const formData = new FormData(form);
            const clientMessageIdInput = getOrCreateClientMessageIdInput(form);
            formData.set('client_message_id', clientMessageIdInput.value);
            const parentIdInput = document.getElementById('parent_id');
            const replyContainer = document.getElementById('reply-indicator-container');
            const parentId = parentIdInput ? String(parentIdInput.value || '').trim() : '';
            const isReplyUiActive = !!(
                replyContainer &&
                replyContainer.style.display !== 'none' &&
                replyContainer.innerHTML &&
                replyContainer.innerHTML.trim() !== ''
            );

            // Safety guard: never send hidden/stale parent_id values.
            if (parentId && isReplyUiActive) {
                formData.set('parent_id', parentId);
            } else {
                formData.delete('parent_id');
                if (parentIdInput) {
                    parentIdInput.value = '';
                }
            }

            // بررسی محتوای پیام قبل از ارسال
            const hasVoiceFile = voiceFileInput && voiceFileInput.files && voiceFileInput.files.length > 0;
            const messageEditor = ckeditor?.instances?.message_editor || null;
            let messageText = '';
            let messageHtml = '';
            
            if (messageEditor) {
                messageHtml = messageEditor.getData().trim();
                // تبدیل HTML به plain text با حفظ line breaks
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = messageHtml;
                // تبدیل <br> و <p> به line breaks
                const brs = tempDiv.querySelectorAll('br');
                brs.forEach(br => br.replaceWith('\n'));
                const ps = tempDiv.querySelectorAll('p, div');
                ps.forEach(p => {
                    if (p.nextSibling) {
                        p.appendChild(document.createTextNode('\n'));
                    }
                });
                messageText = tempDiv.textContent || tempDiv.innerText || '';
                messageText = messageText.trim();
            } else {
                const messageTextarea = document.getElementById('message_editor');
                if (messageTextarea) {
                    messageText = messageTextarea.value.trim();
                }
            }
            
            // تنظیم محتوا در formData (اگر CKEditor استفاده می‌شود، plain text را ارسال کن)
            if (messageEditor && messageText) {
                formData.set('message', messageText);
            }

            // اگر هم پیام خالی است و هم فایل صوتی نیست، از ارسال جلوگیری کن
            if (!messageText && !hasVoiceFile) {
                groupChatNotify('پیام نمی‌تواند خالی باشد.', 'error');
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
            
            // ذخیره موقعیت قبل از submit (اگر تابع موجود باشد)
            if (typeof saveScrollPositionBeforeSubmit === 'function') {
                saveScrollPositionBeforeSubmit();
            }

            // اگر فایل صوتی انتخاب شده و message خالی است، یک مقدار پیش‌فرض اضافه کن
            if (hasVoiceFile && !messageText) {
                if (messageEditor) {
                    messageEditor.setData('🎤 پیام صوتی');
                } else {
                    const messageTextarea = document.getElementById('message_editor');
                    if (messageTextarea) {
                        messageTextarea.value = '🎤 پیام صوتی';
                    }
                }
                formData.set('message', '🎤 پیام صوتی');
            }

            // ====== OPTIMISTIC UPDATE: نمایش فوری پیام قبل از پاسخ سرور ======
            debugLog('[TIMING] JS T1 - before optimistic update:', Date.now());
            const tempMsgId = 'temp_' + Date.now();
            const optimisticMsg = {
                id: tempMsgId,
                user_id: window.authUserId,
                sender: 'شما',
                message: messageHtml || messageText,
                created_at: new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }),
                reactions: [],
                parent_id: null,
                state: 'pending'
            };
            renderMessageThroughPipeline(optimisticMsg, 'optimistic');
            debugLog('[TIMING] JS T2 - after appendMessage (message should be visible NOW):', Date.now());
            // نمایش حالت «در حال ارسال» با کاهش شفافیت
            const tempMsgEl = document.getElementById('msg-' + tempMsgId);
            if (tempMsgEl) {
                tempMsgEl.dataset.deliveryState = 'pending';
                tempMsgEl.style.opacity = '0.65';
                const pendingBubble = tempMsgEl.querySelector('.message-bubble');
                if (pendingBubble) {
                    const existingReadReceipt = pendingBubble.querySelector('.read-receipt span');
                    if (existingReadReceipt) existingReadReceipt.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ارسال...';
                }
            }
            // اسکرول به پایین بعد از نمایش فوری
            const chatBoxScroll = document.getElementById('chat-box');
            if (chatBoxScroll) chatBoxScroll.scrollTop = chatBoxScroll.scrollHeight;
            // ====== END OPTIMISTIC UPDATE ======

            try {
                debugLog('[TIMING] JS T3 - before fetch (server request starting):', Date.now());
                
                // بررسی اینکه parent_id هنوز معتبره (پیام مرجع هنوز در DOM هست)
                const parentIdVal = document.getElementById('parent_id')?.value;
                if (parentIdVal && /^\d+$/.test(parentIdVal)) {
                    const parentMsgEl = document.getElementById('msg-' + parentIdVal);
                    if (!parentMsgEl) {
                        // پیام مرجع حذف شده - reply indicator رو پاک کن
                        if (typeof cancelReply === 'function') cancelReply();
                        formData.set('parent_id', '');
                    }
                }
                
                const response = await groupChatFetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server did not return JSON');
                }

                const responseData = await response.json();
                const serverTime = response.headers.get('X-Chat-Server-Time-Ms');
                debugLog('[TIMING] JS T4 - server responded:', Date.now(), 'status:', response.status, 'server_time_ms:', serverTime);

                if (!response.ok) {
                    if (response.status === 422) {
                        const errorMessage = responseData.message || 'خطا در اعتبارسنجی داده‌ها';
                        const errors = responseData.errors ? Object.values(responseData.errors).flat().join('\n') : '';
                        // حذف پیام موقت
                        const tempEl422 = document.getElementById('msg-' + tempMsgId);
                        if (tempEl422) tempEl422.remove();
                        groupChatNotify(`${errorMessage}\n${errors}`, 'error');
                        return;
                    } else if (response.status === 500) {
                        console.error('Server Error Details:', responseData);
                        // حذف پیام موقت
                        const tempEl500 = document.getElementById('msg-' + tempMsgId);
                        if (tempEl500) tempEl500.remove();
                        groupChatNotify('خطا در سرور. لطفاً دوباره تلاش کنید. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.', 'error');
                        return;
                    } else {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }
                
                if (responseData.status === 'success') {
                    // حذف پیام موقت و جایگزینی با پیام واقعی از سرور
                    const tempElSuccess = document.getElementById('msg-' + tempMsgId);
                    const nextSibling = tempElSuccess ? tempElSuccess.nextSibling : null;
                    const realMessageRow = renderMessageThroughPipeline(responseData.message, 'submit-response');
                    if (realMessageRow) realMessageRow.dataset.deliveryState = responseData.message.state || 'sent';
                    if (tempElSuccess && realMessageRow && realMessageRow.parentElement) {
                        realMessageRow.parentElement.insertBefore(realMessageRow, nextSibling);
                        tempElSuccess.remove();
                    }
                    // اسکرول به پایین بعد از دریافت پاسخ
                    const chatBoxFinal = document.getElementById('chat-box');
                    if (chatBoxFinal) chatBoxFinal.scrollTop = chatBoxFinal.scrollHeight;
                    // به‌روزرسانی lastMessageId بعد از ارسال پیام
                    if (responseData.message && responseData.message.id) {
                        if (!lastMessageId || responseData.message.id > lastMessageId) {
                            lastMessageId = responseData.message.id;
                        }
                    }
                    form.reset();
                    clientMessageIdInput.value = '';
                    
                    // Clear voice file input and preview
                    if (voiceFileInput) {
                        voiceFileInput.value = '';
                    }
                    updateVoiceFilePreview(null);
                    
                    // Clear CKEditor if exists
                    const activeMessageEditor = ckeditor?.instances?.message_editor || null;
                    if (activeMessageEditor) {
                        activeMessageEditor.setData('');
                    }
                    
                    // Clear parent_id after successful submission
                    document.getElementById('parent_id').value = '';
                    // Hide reply indicator
                    const replyIndicator = document.querySelector('.reply-indicator');
                    if (replyIndicator) {
                        replyIndicator.remove();
                    }
                } else {
                    // حذف پیام موقت در صورت خطا سرور
                    const tempElFail = document.getElementById('msg-' + tempMsgId);
                    if (tempElFail) tempElFail.remove();
                    groupChatNotify('خطا در ارسال پیام: ' + (responseData.message || 'خطای ناشناخته'), 'error');
                }
            } catch (error) {
                // حذف پیام موقت در صورت خطا شبکه
                const tempElCatch = document.getElementById('msg-' + tempMsgId);
                if (tempElCatch) tempElCatch.remove();
                console.error('Error:', error);
                if (error.message.includes('Failed to fetch')) {
                    groupChatNotify('خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'error');
                } else {
                    groupChatNotify('خطا در ارسال پیام. لطفاً دوباره تلاش کنید.', 'error');
                }
            }
        });
    }
    
    // ========== POLLING MECHANISM ==========
    // شروع polling برای دریافت پیام‌های جدید
    pollLog('🔍🔍🔍 INITIALIZING POLLING MECHANISM 🔍🔍🔍');
    pollLog('Current time:', new Date().toISOString());
    
    // بررسی وجود window.groupId
    if (typeof window.groupId === 'undefined') {
        console.error('❌❌❌ window.groupId is NOT DEFINED! ❌❌❌');
        console.error('Available window properties:', Object.keys(window).filter(k => k.toLowerCase().includes('group')));
        console.error('All window properties starting with "group":', Object.keys(window).filter(k => k.toLowerCase().includes('group')));
    } else {
        pollLog('✅✅✅ window.groupId FOUND:', window.groupId);
        pollLog('Type:', typeof window.groupId);
        pollLog('Value:', window.groupId);
        
        // شروع polling بعد از 2 ثانیه برای اطمینان از لود کامل
        realtimeLifecycle.timeout(function() {
            pollLog('🚀🚀🚀 ATTEMPTING TO START POLLING 🚀🚀🚀');
            pollLog('Group ID:', window.groupId);
            pollLog('initGroupRealtimeListeners function exists:', typeof window.initGroupRealtimeListeners === 'function');
            pollLog('startPolling function exists:', typeof window.startPolling === 'function');

            let realtimeInitResult = false;
            if (typeof window.initGroupRealtimeListeners === 'function') {
                realtimeInitResult = window.initGroupRealtimeListeners();
                pollLog('Realtime init result:', realtimeInitResult);
            }
            
            if (typeof window.startPolling === 'function') {
                if (shouldPollFallback()) {
                    pollLog('✅ Starting polling because fallback is needed.');
                    window.startPolling();
                } else {
                    pollLog('✅ Realtime healthy; polling deferred until fallback is needed.');
                    realtimeState.fallbackMonitorTimer = realtimeLifecycle.interval(function() {
                        if (shouldPollFallback()) {
                            pollLog('⚠️ Realtime degraded; starting polling fallback.');
                            window.clearInterval(realtimeState.fallbackMonitorTimer);
                            realtimeState.fallbackMonitorTimer = null;
                            window.startPolling();
                        }
                    }, 5000);
                }
            } else {
                console.error('❌❌❌ window.startPolling function NOT FOUND! ❌❌❌');
                console.error('Available functions:', Object.keys(window).filter(k => typeof window[k] === 'function' && k.toLowerCase().includes('poll')));
            }
        }, 2000);
    }
    // ========== END POLLING MECHANISM ==========
});

function renderMessageThroughPipeline(message, source = 'legacy') {
    if (window.__groupChatModularFrontend && window.GroupChat?.feed && window.GroupChat?.renderer?.supports('message')) {
        return window.GroupChat.feed.apply([{ ...message, content_type: 'message' }], source)[0] || null;
    }
    return appendMessage(message);
}

function appendMessage(message) {
    const chatBox = document.getElementById('chat-box');
    if (!chatBox) return;

    if (message && message.id) {
        const existing = document.getElementById('msg-' + message.id);
        if (existing) return existing;
    }

    const isMine = message.user_id == authUserId;
    const senderName = message.sender || 'کاربر';
    const initials = senderName.split(' ').map(n => n.charAt(0)).join(' ').trim() || '؟';
    // محتوا از backend با <br> برای line breaks می‌آید، مستقیماً استفاده می‌کنیم
    const messageContent = message.message || '';

    function renderMentionLinks(html) {
        if (!html || typeof html !== 'string') return '';
        if (/<a\b[^>]*class=["'][^"']*mention-link/i.test(html)) return html;
        const withBracketMentions = html.replace(/(^|[\s>])@\[([0-9]+)\]/g, function(match, prefix, userId) {
            return `${prefix}<a href="/profile-member/${userId}" class="mention-link" data-mention-user-id="${userId}">@${userId}</a>`;
        });
        return withBracketMentions.replace(/(^|[\s>])@([0-9]+)\b/g, function(match, prefix, userId) {
            return `${prefix}<a href="/profile-member/${userId}" class="mention-link" data-mention-user-id="${userId}">@${userId}</a>`;
        });
    }

    const messageContentHtml = renderMentionLinks(messageContent);
    const formattedTime = message.created_at || new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
    
    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Strip HTML helper
    function stripHtml(html) {
        const tmp = document.createElement('DIV');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }
    
    // Generate reactions HTML
    function generateReactionsHTML(messageId, reactions) {
        if (!reactions || reactions.length === 0) return '';
        const emojis = {
            like: '👍',
            love: '❤️',
            laugh: '😂',
            wow: '😮',
            sad: '😢',
            angry: '😠',
            '👍': '👍',
            '❤️': '❤️',
            '😂': '😂',
            '😮': '😮',
            '😢': '😢',
            '🔥': '🔥',
            '👎': '👎'
        };
        return `<div class="message-reactions" style="display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;">${reactions.map(r => {
            const type = r.type || r.reaction_type || '';
            const count = r.count || 0;
            const emoji = emojis[type] || type || '👍';
            return `<button type="button" class="reaction-badge" style="background:#f0f0f0;padding:2px 6px;border:0;border-radius:12px;font-size:12px;cursor:pointer;" data-legacy-chat-action="reaction" data-message-id="${messageId}" data-reaction-type="${type}">${emoji} ${count}</button>`;
        }).join('')}</div>`;
    }
    
    const messageRow = document.createElement('div');
    messageRow.className = `message-row ${isMine ? 'you' : 'other'}`;
    messageRow.setAttribute('data-message-id', message.id);
    messageRow.setAttribute('data-feed-item', 'true');
    messageRow.setAttribute('data-feed-type', 'message');
    messageRow.setAttribute('data-feed-id', message.id);
    messageRow.setAttribute('data-feed-author-id', message.user_id);
    messageRow.setAttribute('data-feed-unread', isMine ? '0' : '1');
    messageRow.id = `msg-${message.id}`;
    
    let messageHTML = '';
    
    // آواتار (فقط برای پیام‌های دیگران)
    if (!isMine) {
        messageHTML += `<a href="/profile/member/${message.user_id}" class="avatar-link"><span class="avatar"><span>${initials}</span></span></a>`;
    }
    
    // Reply preview
    let replyPreviewHTML = '';
    if (message.parent_id && message.parent_sender && message.parent_content) {
        replyPreviewHTML = `<div class="reply-preview"><div class="reply-sender">${escapeHtml(message.parent_sender)}</div><div class="reply-text">${escapeHtml(message.parent_content.substring(0, 80))}</div></div>`;
    }
    
    // Voice message
    let voiceMessageHTML = '';
    if (message.voice_message || message.voice_message_url) {
        // Prefer backend stream URL when available to avoid path/mime mismatches.
        let voiceUrl = message.voice_message_url || message.voice_message;
        if (!voiceUrl.startsWith('http://') && !voiceUrl.startsWith('https://')) {
            if (voiceUrl.startsWith('/messages/')) {
                // Dedicated voice stream endpoint; keep relative URL as-is.
            } else {
                let normalizedPath = voiceUrl.startsWith('/') ? voiceUrl.substring(1) : voiceUrl;

                // Avoid creating /storage/storage/... for already-prefixed paths.
                if (normalizedPath.startsWith('storage/')) {
                    normalizedPath = normalizedPath.substring('storage/'.length);
                }

                // Build full URL - encode each part separately to handle spaces.
                const pathParts = normalizedPath.split('/');
                const encodedParts = pathParts.map(part => encodeURIComponent(part));
                voiceUrl = window.location.origin + '/storage/' + encodedParts.join('/');
            }
        }
        let voiceType = String(message.file_type || 'audio/webm').toLowerCase();
        if (voiceType.includes('webm') || voiceType.includes('opus')) {
            voiceType = 'audio/webm';
        } else if (voiceType.includes('ogg')) {
            voiceType = 'audio/ogg';
        } else if (voiceType.includes('wav')) {
            voiceType = 'audio/wav';
        } else if (voiceType.includes('mp3') || voiceType.includes('mpeg')) {
            voiceType = 'audio/mpeg';
        } else if (!voiceType.startsWith('audio/')) {
            voiceType = 'audio/webm';
        }
        voiceMessageHTML = `<div class="voice-message-container" style="margin-top: 12px; padding: 12px; background: ${isMine ? '#e3f2fd' : '#f5f5f5'}; border-radius: 12px; border: 1px solid ${isMine ? '#90caf9' : '#e0e0e0'}; direction: ltr;"><div style="display: flex; align-items: center; gap: 12px;"><div style="width: 40px; height: 40px; border-radius: 50%; background: ${isMine ? '#2196f3' : '#757575'}; display: flex; align-items: center; justify-content: center; color: white;"><i class="fas fa-microphone"></i></div><div class="voice-message-content" style="flex: 1; min-width: 220px; width: 100%;"><div style="font-size: 12px; color: #666; margin-bottom: 4px;"><i class="fas fa-headphones"></i> پیام صوتی</div><audio class="voice-player" controls style="width: 100%;" preload="metadata" src="${voiceUrl}" type="${voiceType}">مرورگر شما از پخش صدا پشتیبانی نمی‌کند.</audio></div></div></div>`;
    }
    
    const hasVoiceMessage = Boolean(message.voice_message || message.voice_message_url);
    messageHTML += `
        <div class="message-bubble ${isMine ? 'you' : 'other'} ${hasVoiceMessage ? 'message-bubble--voice' : ''}" data-message-id="${message.id}" data-user-id="${message.user_id}" data-edit-url="/messages/${message.id}/edit" data-delete-url="/messages/${message.id}/delete" data-report-url="/messages/${message.id}/report" data-content-raw="${escapeHtml(stripHtml(messageContentHtml))}">
            <div class="message-head">
                ${isMine ? 
                    // برای پیام‌های خود کاربر: سه نقطه در سمت چپ، نام در سمت راست
                    `<div class="action-menu message-action" data-action-menu>
                        <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="action-menu__list">
                            <button type="button" data-legacy-chat-action="reply" data-message-id="${message.id}" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                            <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                            ${([2,3].includes(window.yourRole || 0)) ? `<button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="${message.id}"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                            <button type="button" class="action-menu__item btn-edit"><i class="fas fa-edit"></i> ویرایش</button>
                            <button type="button" data-group-chat-action="delete-message" data-message-id="${message.id}" class="action-menu__item action-menu__item--danger btn-delete"><i class="fas fa-trash"></i> حذف</button>
                            <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                        </div>
                    </div>
                    <div class="message-head__info">
                        <span class="message-sender message-sender--self">شما</span>
                    </div>` :
                    // برای پیام‌های دیگران: نام کاربر در سمت چپ، سه نقطه در سمت راست
                    `<div class="message-head__info">
                        <a href="/profile-member/${message.user_id}" class="message-sender">${escapeHtml(senderName)}</a>
                    </div>
                    <div class="action-menu message-action" data-action-menu>
                        <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="action-menu__list">
                            <button type="button" data-legacy-chat-action="reply" data-message-id="${message.id}" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                            <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                            ${([2,3].includes(window.yourRole || 0)) ? `<button type="button" class="action-menu__item btn-pin" data-legacy-chat-action="pin" data-message-id="${message.id}"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                            <button type="button" data-group-chat-action="report-message" data-message-id="${message.id}" class="action-menu__item btn-report"><i class="fas fa-flag"></i> گزارش</button>
                            <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                        </div>
                    </div>`
                }
            </div>
            ${replyPreviewHTML}
            <p class="message-content">${messageContentHtml}</p>
            ${voiceMessageHTML}
            <div class="message-timestamp" style="display: flex !important; align-items: center; gap: 6px; margin-top: 4px; flex-wrap: wrap; margin-left: -10px !important; margin-right: -10px !important; padding-left: 10px !important; padding-right: 10px !important; justify-content: space-between !important; float: none !important; text-align: left !important; direction: ltr !important;">
                ${isMine ? '<div class="read-receipt" style="font-size: 10px; text-align: left; direction: ltr; margin-right: auto; margin-left: 0;"><span style="color: #9ca3af;"><i class="fas fa-check"></i> ارسال شده</span></div>' : ''}
                <div style="display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center;">
                    ${(message.reactions && message.reactions.length > 0) ? generateReactionsHTML(message.id, message.reactions) : ''}
                </div>
                <div style="display: flex; align-items: center; gap: 4px; margin-left: auto;">
                    <span class="message-time">${formattedTime}</span>
                </div>
            </div>
        </div>
    `;
    
    messageRow.innerHTML = messageHTML;
    const typingIndicator = document.getElementById('group-typing-indicator');
    if (typingIndicator && typingIndicator.parentElement === chatBox) {
        chatBox.insertBefore(messageRow, typingIndicator);
    } else {
        chatBox.appendChild(messageRow);
    }
    
    // اضافه کردن Thread button به پیام جدید (مثل پیام‌های Blade-rendered)
    const newBubble = messageRow.querySelector('[data-message-id]');
    if (newBubble && typeof window.addThreadButton === 'function') {
        window.addThreadButton(newBubble);
    }
    
    // Initialize reaction button for this message
    if (typeof addReactionButton === 'function') {
        const messageBubble = messageRow.querySelector('.message-bubble');
        if (messageBubble) {
            addReactionButton(message.id);
        }
    }
    
    // Initialize click handler for profile link
    const profileLink = messageRow.querySelector('a.message-sender');
    if (profileLink) {
        profileLink.addEventListener('click', function(e) {
            // اجازه بده لینک کار کند
            e.stopPropagation();
            e.preventDefault();
            // اگر href وجود دارد، به آن برو
            const href = this.getAttribute('href');
            if (href && !href.includes('#')) {
                window.location.href = href;
            }
        });
    }
    
    // Scroll to the bottom of the chat - فقط اگر scroll restore کامل شده باشد
    // و کاربر خودش به پایین رفته باشد
    // در غیر این صورت، scroll restore خودش موقعیت را تنظیم می‌کند
    // این کد حذف شد چون با scroll restore تداخل دارد
    return messageRow;
}

// ✅ NEW: Helper to update poll UI without page reload
function updatePollUI(pollData) {
    try {
        const pollId = pollData.id || pollData.poll_id;
        const pollElement = document.getElementById(`poll-${pollId}`);
        if (!pollElement) {
            console.warn('Poll element not found:', pollId);
            return;
        }

        const options = Array.isArray(pollData.options) ? pollData.options : [];
        const optionsById = {};
        options.forEach(function(option) {
            optionsById[String(option.id)] = option;
        });

        pollElement.querySelectorAll('.poll-option[data-option-id]').forEach(function(optionButton) {
            const optionId = optionButton.getAttribute('data-option-id');
            const option = optionsById[String(optionId)];
            if (!option) return;

            const percent = Number.isFinite(Number(option.percent)) ? Number(option.percent) : 0;
            const statEl = optionButton.querySelector('.poll-option__stat');
            if (statEl) {
                statEl.textContent = `${percent}%`;
            }

            const selectedOptionId = parseInt(pollData.user_option_id, 10);
            const currentOptionId = parseInt(optionId, 10);
            const isSelected = selectedOptionId && selectedOptionId === currentOptionId;
            optionButton.classList.toggle('poll-option--selected', Boolean(isSelected));
            optionButton.classList.toggle('voted', Boolean(isSelected));
        });

        const totalVotes = Number.isFinite(Number(pollData.total_votes))
            ? Number(pollData.total_votes)
            : options.reduce(function(sum, option) {
                return sum + (Number.isFinite(Number(option.count)) ? Number(option.count) : 0);
            }, 0);

        const totalEl = pollElement.querySelector('.poll-card__total');
        if (totalEl) {
            totalEl.textContent = `تعداد رأی: ${totalVotes}`;
        }

        console.log('Poll updated successfully:', pollId);
    } catch (error) {
        console.error('Error updating poll UI:', error);
    }
}

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
  closeElectionBox(); 
  cancelPostForm();
  cancelPollForm()
  closeSkill()
  cancelelectionForm()
}

function startPollCountdowns() {
  const lifecycle = window.GroupChatLifecycle;
  document.querySelectorAll('.poll-timer').forEach(timer => {
    if (timer.dataset.timerSet === "true") return;

    const expiresAtStr = timer.getAttribute('data-expires');
    if (!expiresAtStr) {
      timer.innerText = 'بدون زمان پایان';
      return;
    }

    const expiresAt = new Date(expiresAtStr);

    let intervalId = null;
    function stopCountdown(label) {
      if (label) timer.innerText = label;
      if (intervalId !== null) lifecycle?.clearInterval(intervalId);
      intervalId = null;
      timer.dataset.timerSet = 'complete';
    }

    function updateTimer() {
      if (!timer.isConnected) {
        stopCountdown();
        return false;
      }
      const now = new Date();
      const diffMs = expiresAt - now;

      if (isNaN(diffMs)) {
        stopCountdown('تاریخ نامعتبر');
        return false;
      }

      if (diffMs <= 0) {
        stopCountdown('پایان یافته');
        return false;
      }

      const totalSeconds = Math.floor(diffMs / 1000);
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;
if (hours > 24) {
    const days = Math.floor(hours / 24);
    const remainingHours = hours % 24;

    timer.innerText = `${days} روز ${remainingHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
} else {
    timer.innerText = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

      return true;

    }

    if (updateTimer()) {
      intervalId = lifecycle?.interval(updateTimer, 1000) ?? setInterval(updateTimer, 1000);
      timer.dataset.timerSet = "true";
    }
  });
}

// Voice recording functionality
let mediaRecorder;
let audioChunks = [];
let isRecording = false;

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    const voiceRecordBtn = document.getElementById('voice-record-btn');
    const stopRecordingBtn = document.getElementById('stop-recording');

    if (voiceRecordBtn) {
        voiceRecordBtn.addEventListener('click', startRecording);
    }

    if (stopRecordingBtn) {
        stopRecordingBtn.addEventListener('click', stopRecording);
    }
});

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        
        
        mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
        };
        
        mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
            const reader = new FileReader();
            reader.readAsDataURL(audioBlob);
            reader.onloadend = async () => {
                const voiceMessageInput = document.getElementById('voice_message');
                if (voiceMessageInput) {
                    voiceMessageInput.value = reader.result;
                    
                    const form = document.getElementById('chatForm');
                    if (form) {
                        const formData = new FormData(form);
                        const clientMessageIdInput = getOrCreateClientMessageIdInput(form);
                        formData.set('client_message_id', clientMessageIdInput.value);
                        // Add an empty message field to satisfy server validation
                        formData.append('message', '[پیام صوتی]');
                        
                        try {
                            const response = await groupChatFetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': getCsrfToken(),
                                    'Accept': 'application/json'
                                },
                                body: formData
                            });

                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                throw new Error('Server did not return JSON');
                            }

                            const responseData = await response.json();

                            if (!response.ok) {
                                if (response.status === 422) {
                                    const errorMessage = responseData.message || 'خطا در اعتبارسنجی داده‌ها';
                                    const errors = responseData.errors ? Object.values(responseData.errors).flat().join('\n') : '';
                                    groupChatNotify(`${errorMessage}\n${errors}`, 'error');
                                } else if (response.status === 500) {
                                    console.error('Server Error Details:', responseData);
                                    groupChatNotify('خطا در سرور. لطفاً دوباره تلاش کنید. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.', 'error');
                                } else {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                            }
                            
                            if (responseData.status === 'success') {
                                renderMessageThroughPipeline(responseData.message, 'voice-response');
                                form.reset();
                                clientMessageIdInput.value = '';
                            } else {
                                groupChatNotify('خطا در ارسال پیام صوتی: ' + (responseData.message || 'خطای ناشناخته'), 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            if (error.message.includes('Failed to fetch')) {
                                groupChatNotify('خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'error');
                            } else {
                                groupChatNotify('خطا در ارسال پیام صوتی. لطفاً دوباره تلاش کنید.', 'error');
                            }
                        }
                    }
                }
            };
        };
        
        mediaRecorder.start();
        isRecording = true;
        
        const voiceRecording = document.getElementById('voice-recording');
        const voiceRecordBtn = document.getElementById('voice-record-btn');
        
        if (voiceRecording) {
            voiceRecording.style.display = 'flex';
        }
        if (voiceRecordBtn) {
            voiceRecordBtn.style.display = 'none';
        }
        
    } catch (err) {
        console.error('Error accessing microphone:', err);
        groupChatNotify('دسترسی به میکروفون امکان‌پذیر نیست. لطفاً دسترسی را بررسی کنید.', 'error');
    }
}

function stopRecording() {
    if (isRecording && mediaRecorder) {
        mediaRecorder.stop();
        isRecording = false;
        
        // Stop all tracks
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
        
        // Hide recording UI
        const voiceRecording = document.getElementById('voice-recording');
        const voiceRecordBtn = document.getElementById('voice-record-btn');
        
        if (voiceRecording) {
            voiceRecording.style.display = 'none';
        }
        if (voiceRecordBtn) {
            voiceRecordBtn.style.display = 'block';
        }
    }
}

// Add click handler for reply (using event delegation for dynamic messages)
document.addEventListener('DOMContentLoaded', function() {
    const chatBoxEl = document.getElementById('chat-box');
    if (chatBoxEl) {
        // NOTE: Do not auto-set parent_id by clicking message bubbles.
        // Reply must only be initiated via explicit reply actions (btn-rep / replyToMessage)
        // to prevent accidental threaded replies during reaction/menu interactions.
    }
});

function replyToMessage(messageId, senderName, content) {
    const sanitize = function(value) {
        const div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML;
    };

    const normalizedSender = String(senderName || 'کاربر').trim() || 'کاربر';
    const contentAsText = String(content || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
    const previewText = contentAsText.substring(0, 120);

    // Find the reply indicator container
    const replyContainer = document.getElementById('reply-indicator-container');
    if (!replyContainer) {
        console.error('Reply indicator container not found');
        return;
    }

    // Create reply indicator content directly in container (بدون wrapper اضافی)
    replyContainer.innerHTML = `
        <div class="reply-info">
            <div class="reply-arrow"></div>
            <div style="flex: 1; min-width: 0;">
                <div class="reply-sender-name">${sanitize(normalizedSender)}</div>
                <div class="reply-content">${sanitize(previewText)}</div>
            </div>
        </div>
        <button type="button" class="btn-cancel-reply" data-legacy-chat-action="cancel-reply">
            <i class="fas fa-times"></i>
        </button>
    `;
    replyContainer.style.display = 'block';

    // Set parent_id in form
    const parentIdInput = document.getElementById('parent_id');
    if (parentIdInput) {
        parentIdInput.value = messageId;
    }

    // Scroll to input
    const chatForm = document.getElementById('chatForm');
    if (chatForm) {
        chatForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function replyToMessageFromButton(button, fallbackMessageId) {
    const bubble = button?.closest('.message-bubble');
    const row = button?.closest('.message-row');
    const messageId = String(
        fallbackMessageId ||
        bubble?.getAttribute('data-message-id') ||
        row?.getAttribute('data-message-id') ||
        ''
    ).trim();

    if (!messageId) {
        console.warn('Reply failed: message id not found from button context.');
        return;
    }

    const senderEl = bubble?.querySelector('.message-sender');
    const senderName = (senderEl?.textContent || '').trim() || (bubble?.classList.contains('you') ? 'شما' : 'کاربر');
    const rawContent =
        bubble?.getAttribute('data-content-raw') ||
        bubble?.querySelector('.message-content')?.textContent ||
        '';

    replyToMessage(messageId, senderName, rawContent);
}

window.replyToMessageFromButton = replyToMessageFromButton;

function cancelReply() {
    // Hide reply indicator container
    const replyContainer = document.getElementById('reply-indicator-container');
    if (replyContainer) {
        replyContainer.innerHTML = '';
        replyContainer.style.display = 'none';
    }
    
    // Remove reply indicator (fallback for old code)
    const replyIndicator = document.querySelector('.reply-indicator');
    if (replyIndicator && replyIndicator.parentElement === document.body) {
        replyIndicator.remove();
    }

    // Clear parent_id
    const parentIdInput = document.getElementById('parent_id');
    if (parentIdInput) {
        parentIdInput.value = '';
    }
}

// Add event listener for form submit to clear reply after sending
const chatForm = document.getElementById('chatForm');
if (chatForm) {
    chatForm.addEventListener('submit', function() {
        setTimeout(cancelReply, 100);
    });
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
        if (parentInput?.value == messageId && typeof cancelReply === 'function') cancelReply();
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
                window.GroupChatFeedBridge.mutate('post', 'delete', { id: postId }, 'local-post-delete');
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
            setTimeout(function() {
                document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }, 350);
            const updatedPost = data.post?.html
                ? { ...data.post, id: data.post.id || postId }
                : data.blog;
            if (updatedPost) {
                window.GroupChatFeedBridge.mutate('post', 'update', updatedPost, 'local-post-edit');
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

function openChatSearch() {
    const searchBox = document.createElement('div');
    searchBox.className = 'chat-search-box';
    searchBox.innerHTML = `
        <div class="search-header">
            <input type="text" id="chatSearchInput" placeholder="جستجو در پیام‌ها...">
            <button type="button" data-legacy-chat-action="close-search">×</button>
        </div>
        <div id="searchResults" class="search-results"></div>
    `;
    document.body.appendChild(searchBox);
    
    const searchInput = document.getElementById('chatSearchInput');
    searchInput.focus();
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const searchTerm = this.value.toLowerCase();
            const messages = document.querySelectorAll('.message-wrapper');
            const results = [];
            const seenIds = new Set();
            
            messages.forEach(msg => {
                const messageId = msg.getAttribute('data-message-id');
                if (seenIds.has(messageId)) return;
                seenIds.add(messageId);
                
                const content = msg.querySelector('.message-bubble p')?.textContent.toLowerCase() || '';
                if (content.includes(searchTerm)) {
                    results.push(msg);
                }
            });
            
            const resultsContainer = document.getElementById('searchResults');
            resultsContainer.innerHTML = '';
            
            results.forEach(msg => {
                const clone = msg.cloneNode(true);
                clone.addEventListener('click', () => {
                    msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    msg.style.backgroundColor = '#ffeb3b';
                    setTimeout(() => msg.style.backgroundColor = '', 2000);
                });
                resultsContainer.appendChild(clone);
            });
        }, 300); // تاخیر 300 میلی‌ثانیه برای جلوگیری از جستجوی مکرر
    });
}

function closeChatSearch() {
    const searchBox = document.querySelector('.chat-search-box');
    if (searchBox) {
        searchBox.remove();
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

// ===== IntersectionObserver for marking posts and polls as read =====
(function() {
    if (!window.groupId) return;
    
    const markedAsRead = new Set();
    const csrfToken = (typeof getCsrfToken === 'function') ? getCsrfToken() :
        (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                const element = entry.target;
                const postId = element.id.replace('blog-', '');
                const pollId = element.id.replace('poll-', '');
                
                // Mark blog post as read
                if (element.id.startsWith('blog-') && !markedAsRead.has('blog-' + postId)) {
                    const key = 'blog-' + postId;
                    markedAsRead.add(key);
                    fetch(`/blog/${postId}/mark-read`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        element.dataset.feedUnread = '0';
                        window.dispatchEvent(new CustomEvent('group-feed:read-state-changed'));
                    }).catch(() => {
                        markedAsRead.delete(key);
                    });
                }
                
                // Mark poll as read
                if (element.id.startsWith('poll-') && !markedAsRead.has('poll-' + pollId)) {
                    const key = 'poll-' + pollId;
                    markedAsRead.add(key);
                    fetch(`/poll/${pollId}/mark-read`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        element.dataset.feedUnread = '0';
                        window.dispatchEvent(new CustomEvent('group-feed:read-state-changed'));
                    }).catch(() => {
                        markedAsRead.delete(key);
                    });
                }
            }
        });
    }, {
        threshold: 0.5,
        rootMargin: '0px'
    });
    
    // Observe all existing posts and polls
    function observePostsAndPolls() {
        document.querySelectorAll('[id^="blog-"], [id^="poll-"]').forEach(el => {
            if (!el.dataset.observed) {
                el.dataset.observed = 'true';
                observer.observe(el);
            }
        });
    }
    
    // Initial observation
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observePostsAndPolls);
    } else {
        observePostsAndPolls();
    }
    
    // Re-observe after polling injects new content without monkey-patching DOM prototypes.
    const feedRoot = document.getElementById('chat-box') || document.getElementById('group-feed') || document.body;
    if (feedRoot && typeof MutationObserver !== 'undefined') {
        const feedObserver = new MutationObserver(function(mutations) {
            let needsObserve = false;
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (!(node instanceof HTMLElement)) return;
                    if (
                        (node.id && (node.id.startsWith('blog-') || node.id.startsWith('poll-'))) ||
                        node.querySelector?.('[id^="blog-"], [id^="poll-"]')
                    ) {
                        needsObserve = true;
                    }
                });
            });

            if (needsObserve) {
                observePostsAndPolls();
            }
        });

        feedObserver.observe(feedRoot, { childList: true, subtree: true });
    }
})();
