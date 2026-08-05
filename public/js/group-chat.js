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
            // alert('Polling Debug: window.groupId is NOT defined! Check console.');
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

    if (!confirm('آیا از حذف این نظرسنجی مطمئن هستید؟')) {
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
                const response = await fetch(pollForm.action, {
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
        const response = await fetch(form.action, {
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

document.addEventListener('DOMContentLoaded', function () {
  if (typeof window._initReactionButtons === 'function') {
    window._initReactionButtons(document);
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

if (!document._globalActionMenuDismissRegistered) {
    document._globalActionMenuDismissRegistered = true;

    // Close open action menus on any page click, except clicking the toggle itself.
    document.addEventListener('click', function(event) {
        if (event.target.closest('.action-menu__toggle')) {
            return;
        }
        closeAllActionMenus();
    }, true);

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllActionMenus();
        }
    });

    if (!window._actionMenuRepositionBound) {
        window._actionMenuRepositionBound = true;
        window.addEventListener('resize', repositionOpenActionMenus);
        document.addEventListener('scroll', repositionOpenActionMenus, true);
    }
}

document.addEventListener('DOMContentLoaded', () => {
  const menus = Array.from(document.querySelectorAll('[data-action-menu]'));

  const closeAllMenus = (except = null) => {
    menus.forEach(menu => {
      if (menu !== except) {
        menu.classList.remove('is-open');
        menu.querySelector('.action-menu__toggle')?.setAttribute('aria-expanded', 'false');
      }
    });
  };

  menus.forEach(menu => {
    const toggle = menu.querySelector('.action-menu__toggle');
    const list = menu.querySelector('.action-menu__list');

    toggle?.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      const isOpen = menu.classList.contains('is-open');
      closeAllMenus();
      if (!isOpen) {
        menu.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
                requestAnimationFrame(() => positionActionMenu(menu));
      }
    });

    list?.querySelectorAll('button, a').forEach(item => {
            item.addEventListener('click', event => {
                if (item.classList.contains('btn-reaction')) {
                    event.stopPropagation();
                    return;
                }
                closeAllMenus();
            });
    });
  });

    // Global click/escape dismissal is already registered once above.
    // Keeping it single-source avoids duplicate handlers on long sessions.
});


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
    const realtimeState = {
        initialized: false,
        connected: false,
        usingFallback: false,
        lastEventAt: 0,
        fallbackDelayMs: 15000,
        maxFallbackDelayMs: 120000,
        messageTimer: null,
        postTimer: null,
        reconcileTimer: null
    };

    window.getGroupRealtimeState = function getGroupRealtimeState() {
        return { ...realtimeState };
    };

    function markRealtimeHealthy() {
        realtimeState.connected = true;
        realtimeState.usingFallback = false;
        realtimeState.lastEventAt = Date.now();
        realtimeState.fallbackDelayMs = 15000;
    }

    function shouldPollFallback() {
        if (!realtimeState.initialized) return true;
        return realtimeState.usingFallback || !realtimeState.connected;
    }

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
        if (typeof window._initPostMenus === 'function') window._initPostMenus(newEl);
        if (typeof window._initReactionButtons === 'function') window._initReactionButtons(newEl);
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
        if (typeof window._initPostMenus === 'function') window._initPostMenus(newEl);
        if (typeof window._initReactionButtons === 'function') window._initReactionButtons(newEl);
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
            return `<span class="reaction-badge" style="background:#f0f0f0;padding:2px 6px;border-radius:12px;font-size:12px;cursor:pointer;" onclick="if(typeof toggleReaction === 'function') toggleReaction(${messageId}, '${type}')">${emoji} ${count}</span>`;
        }).join('');
        return true;
    }

    function removePostDom(postId) {
        const el = document.getElementById('blog-' + postId);
        fadeRemoveElement(el ? (el.closest('.post-wrapper') || el.parentElement || el) : null);
    }

    function removePollDom(pollId) {
        const el = document.getElementById('poll-' + pollId) || document.querySelector(`[data-poll-id="${pollId}"]`);
        fadeRemoveElement(el ? (el.closest('.poll-wrapper') || el) : null);
    }

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
            appendMessage(message);
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

        if (action === 'edit') {
            updateMessageContentDom(messageId, payload.content || payload.message || '', payload.edited);
        } else if (action === 'delete') {
            removeMessageDom(messageId);
        } else if (action === 'reaction') {
            updateMessageReactionsDom(messageId, payload.reactions || []);
        } else if (action === 'mark-read') {
            updateMessageReadReceiptDom(messageId, payload.read_count || 0);
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

        if (action === 'post_created') {
            const postId = payload.post_id || payload.id;
            if (appendRenderedFeedHtml(payload.html, postId, 'post')) updateLastPostCursor(postId);
            return;
        }
        if (action === 'post_updated') {
            replaceRenderedFeedHtml('#blog-' + payload.post_id, payload.html);
            updateLastPostCursor(payload.post_id);
            return;
        }
        if (action === 'post_deleted') {
            removePostDom(payload.post_id);
            return;
        }
        if (action === 'post_reaction') {
            const container = document.querySelector(`.reaction-buttons[data-post-id="${payload.post_id}"]`);
            if (container) {
                const like = container.querySelector('.like-count');
                const dislike = container.querySelector('.dislike-count');
                if (like) like.textContent = payload.likes ?? 0;
                if (dislike) dislike.textContent = payload.dislikes ?? 0;
            }
            return;
        }
        if (action === 'post_read') {
            updatePostReadReceiptDom(payload.post_id, payload.read_count || 0);
            return;
        }
        if (action === 'poll_created') {
            appendRenderedFeedHtml(payload.html, payload.poll_id, 'poll');
            return;
        }
        if (action === 'poll_updated') {
            replaceRenderedFeedHtml('#poll-' + payload.poll_id + ', [data-poll-id="' + payload.poll_id + '"]', payload.html);
            return;
        }
        if (action === 'poll_read') {
            updatePollReadReceiptDom(payload.poll_id, payload.read_count || 0);
            return;
        }
        if (action === 'poll_deleted') {
            removePollDom(payload.poll_id);
        }
    }

    window.initGroupRealtimeListeners = function initGroupRealtimeListeners() {
        if (realtimeState.initialized || !window.groupId || !window.Echo || typeof window.Echo.private !== 'function') {
            return realtimeState.initialized;
        }

        try {
            const channel = window.Echo.private(`group.${window.groupId}`);
            channel
                .subscribed(function() {
                    markRealtimeHealthy();
                })
                .error(function(error) {
                    console.warn('Realtime channel subscription error; polling fallback remains active.', error);
                    realtimeState.connected = false;
                    realtimeState.usingFallback = true;
                })
                .listen('.group.message.created', applyMessageEvent)
                .listen('.group.message.updated', applyMessageEvent)
                .listen('.group.feed.updated', applyFeedEvent)
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
        const checkInterval = setInterval(function() {
            attempts++;
            if (window.scrollPositionRestored || attempts >= maxAttempts) {
                clearInterval(checkInterval);
                pollingStarted = true;
                pollLog('✅ Polling started after', attempts, 'attempts');
                
                // حالا polling را شروع کن - برای تجربه نزدیک‌تر به بلادرنگ
                let _isPollingPending = false;
                const messagePollIntervalMs = 1000;
                pollingInterval = setInterval(function() {
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
                                        appendMessage(messageData);
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
                                    const bubble = document.querySelector(`.message-bubble[data-message-id="${msg.id}"]`);
                                    if (!bubble) return;
                                    const contentEl = bubble.querySelector('.message-content');
                                    if (contentEl) contentEl.innerHTML = msg.message || '';
                                    bubble.setAttribute('data-content-raw', (msg.message || '').replace(/<[^>]*>/g, ''));
                                    // نشانگر ویرایش
                                    if (msg.edited && !bubble.querySelector('.edited-icon')) {
                                        const ts = bubble.querySelector('.message-timestamp');
                                        if (ts) {
                                            const badge = document.createElement('span');
                                            badge.className = 'edited-icon';
                                            badge.style.cssText = 'font-size:10px;color:#9ca3af;margin-left:4px;';
                                            badge.textContent = '(ویرایش شده)';
                                            ts.prepend(badge);
                                        }
                                    }
                                });
                            }

                            // ===== Handle deleted messages =====
                            if (response.deleted_message_ids && Array.isArray(response.deleted_message_ids)) {
                                response.deleted_message_ids.forEach(function(msgId) {
                                    const msgRow = document.getElementById('msg-' + msgId);
                                    if (msgRow) {
                                        msgRow.style.transition = 'opacity 0.3s ease-out';
                                        msgRow.style.opacity = '0';
                                        setTimeout(() => msgRow.remove(), 300);
                                    }
                                    // اگر این پیام reply target بود، indicator رو پاک کن
                                    const parentInput = document.getElementById('parent_id');
                                    if (parentInput && parentInput.value == msgId) {
                                        if (typeof cancelReply === 'function') cancelReply();
                                    }
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
                setInterval(function() {
                    if (!shouldPollFallback()) return;
                    if (_isPostPollPending) return;
                    if (!window.groupId) return;
                    // sync posts injected by current user's own form submit
                    if (window._lastKnownPostId && window._lastKnownPostId > lastPostId) {
                        lastPostId = window._lastKnownPostId;
                    }
                    _isPostPollPending = true;
                    fetch('/api/groups/' + window.groupId + '/posts/feed?after_id=' + lastPostId, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function(r) { return r.ok ? r.json() : null; })
                    .then(function(data) {
                        if (!data) return;
                        var chatBox = document.getElementById('chat-box');
                        // Handle new posts
                        if (chatBox && data.posts && data.posts.length) {
                            data.posts.forEach(function(p) {
                                if (!p.html || document.getElementById('blog-' + p.id)) return;
                                var tmp = document.createElement('div');
                                tmp.innerHTML = p.html;
                                var el = tmp.firstElementChild;
                                if (el) {
                                    chatBox.appendChild(el);
                                    if (typeof window._initPostMenus === 'function') window._initPostMenus(el);
                                    if (typeof window._initReactionButtons === 'function') window._initReactionButtons(el);
                                }
                                if (p.id > lastPostId) lastPostId = p.id;
                            });
                        }
                        if (data.latest_post_id > lastPostId) lastPostId = data.latest_post_id;
                        // Handle deleted posts
                        if (data.deleted_post_ids && data.deleted_post_ids.length) {
                            data.deleted_post_ids.forEach(function(pid) {
                                var el = document.getElementById('blog-' + pid);
                                // remove outer post-wrapper too
                                var toRemove = el ? (el.closest('.post-wrapper') || el.parentElement || el) : null;
                                if (toRemove) {
                                    toRemove.style.transition = 'opacity 0.3s';
                                    toRemove.style.opacity = '0';
                                    setTimeout(function() { toRemove.remove(); }, 300);
                                }
                            });
                        }
                        // Handle updated posts
                        if (data.updated_posts && data.updated_posts.length) {
                            data.updated_posts.forEach(function(p) {
                                if (!p.html) return;
                                var existing = document.getElementById('blog-' + p.id);
                                if (existing) {
                                    // post-card is inside post-wrapper; replace the outer wrapper
                                    var wrapper = existing.closest('.post-wrapper') || existing.parentElement || existing;
                                    var tmp = document.createElement('div');
                                    tmp.innerHTML = p.html;
                                    var newEl = tmp.firstElementChild;
                                    if (newEl) {
                                        wrapper.replaceWith(newEl);
                                        if (typeof window._initPostMenus === 'function') window._initPostMenus(newEl);
                                        if (typeof window._initReactionButtons === 'function') window._initReactionButtons(newEl);
                                    }
                                }
                            });
                        }
                    })
                    .catch(function() {})
                    .finally(function() { _isPostPollPending = false; });
                }, 3000);

                // ===== Reconcile check every 10s: ask server which visible posts were deleted =====
                var _isReconcilePending = false;
                setInterval(function() {
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
                            var el = document.getElementById('blog-' + pid);
                            var toRemove = el ? (el.closest('.post-wrapper') || el.parentElement || el) : null;
                            if (toRemove) {
                                toRemove.style.transition = 'opacity 0.3s';
                                toRemove.style.opacity = '0';
                                setTimeout(function() { toRemove.remove(); }, 300);
                            }
                        });
                    })
                    .catch(function() {})
                    .finally(function() { _isReconcilePending = false; });
                }, 10000); // every 10 seconds
            }
        }, 500); // هر 500ms چک کن
    }

    // تابع مشترک برای راه‌اندازی منوهای action-menu در عناصر تازه اضافه‌شده
    window._initPostMenus = function(container) {
        var menus = (container || document).querySelectorAll('[data-action-menu]');
        menus.forEach(function(menu) {
            if (menu._menuInit) return;
            menu._menuInit = true;
            var toggle = menu.querySelector('.action-menu__toggle');
            var list = menu.querySelector('.action-menu__list');
            if (!toggle) return;
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var isOpen = menu.classList.contains('is-open');
                document.querySelectorAll('[data-action-menu].is-open').forEach(function(m) {
                    if (m !== menu) m.classList.remove('is-open');
                });
                menu.classList.toggle('is-open', !isOpen);
                if (!isOpen) {
                    requestAnimationFrame(function() { positionActionMenu(menu); });
                }
            });
            if (list) list.querySelectorAll('button, a').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    if (item.classList.contains('btn-reaction')) {
                        e.stopPropagation();
                        return;
                    }
                    menu.classList.remove('is-open');
                });
            });
        });
        // Global dismissal listeners are registered in one place earlier.
    };

    // تابع مشترک برای راه‌اندازی دکمه‌های لایک/دیسلایک در عناصر تازه اضافه‌شده
    window._initReactionButtons = function(container) {
        var scope = container || document;
        scope.querySelectorAll('.reaction-buttons').forEach(function(rc) {
            if (rc._reactionInit) return;
            rc._reactionInit = true;
            var blogId = rc.dataset.postId;
            var btnLike = rc.querySelector('.btn-like');
            var btnDislike = rc.querySelector('.btn-dislike');
            if (btnLike) btnLike.addEventListener('click', function() {
                sendReaction(blogId, '1', rc);
            });
            if (btnDislike) btnDislike.addEventListener('click', function() {
                sendReaction(blogId, '0', rc);
            });
        });
    };
    // مقداردهی اولیه برای همه دکمه‌های واکنش موجود در صفحه
    window._initReactionButtons(document);

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
                alert('پیام نمی‌تواند خالی باشد.');
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
                parent_id: null
            };
            appendMessage(optimisticMsg);
            debugLog('[TIMING] JS T2 - after appendMessage (message should be visible NOW):', Date.now());
            // نمایش حالت «در حال ارسال» با کاهش شفافیت
            const tempMsgEl = document.getElementById('msg-' + tempMsgId);
            if (tempMsgEl) {
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
                
                const response = await fetch(form.action, {
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
                        alert(`${errorMessage}\n${errors}`);
                        return;
                    } else if (response.status === 500) {
                        console.error('Server Error Details:', responseData);
                        // حذف پیام موقت
                        const tempEl500 = document.getElementById('msg-' + tempMsgId);
                        if (tempEl500) tempEl500.remove();
                        alert('خطا در سرور. لطفاً دوباره تلاش کنید. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.');
                        return;
                    } else {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                }
                
                if (responseData.status === 'success') {
                    // حذف پیام موقت و جایگزینی با پیام واقعی از سرور
                    const tempElSuccess = document.getElementById('msg-' + tempMsgId);
                    const nextSibling = tempElSuccess ? tempElSuccess.nextSibling : null;
                    const realMessageRow = appendMessage(responseData.message);
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
                    alert('خطا در ارسال پیام: ' + (responseData.message || 'خطای ناشناخته'));
                }
            } catch (error) {
                // حذف پیام موقت در صورت خطا شبکه
                const tempElCatch = document.getElementById('msg-' + tempMsgId);
                if (tempElCatch) tempElCatch.remove();
                console.error('Error:', error);
                if (error.message.includes('Failed to fetch')) {
                    alert('خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.');
                } else {
                    alert('خطا در ارسال پیام. لطفاً دوباره تلاش کنید.');
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
        setTimeout(function() {
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
                    setInterval(function() {
                        if (shouldPollFallback()) {
                            pollLog('⚠️ Realtime degraded; starting polling fallback.');
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
            return `<span class="reaction-badge" style="background: #f0f0f0; padding: 2px 6px; border-radius: 12px; font-size: 12px; cursor: pointer;" onclick="if(typeof toggleReaction === 'function') toggleReaction(${messageId}, '${type}')">${emoji} ${count}</span>`;
        }).join('')}</div>`;
    }
    
    const messageRow = document.createElement('div');
    messageRow.className = `message-row ${isMine ? 'you' : 'other'}`;
    messageRow.setAttribute('data-message-id', message.id);
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
                            <button type="button" onclick="replyToMessageFromButton(this, '${message.id}')" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                            <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                            ${([2,3].includes(window.yourRole || 0)) ? `<button type="button" class="action-menu__item btn-pin" onclick="pinMessage('${message.id}')"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                            <button type="button" class="action-menu__item btn-edit"><i class="fas fa-edit"></i> ویرایش</button>
                            <button type="button" class="action-menu__item action-menu__item--danger btn-delete"><i class="fas fa-trash"></i> حذف</button>
                            <div class="menu-meta-time"><div class="menu-meta-time__item"><i class="fas fa-paper-plane" style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;"></i><span class="menu-meta-time__label">ارسال شده:</span><span class="menu-meta-time__value">${formattedTime}</span></div></div>
                        </div>
                    </div>
                    <div class="message-head__info">
                        <span class="message-sender message-sender--self">شما</span>
                    </div>` :
                    // برای پیام‌های دیگران: نام کاربر در سمت چپ، سه نقطه در سمت راست
                    `<div class="message-head__info">
                        <a href="/profile-member/${message.user_id}" class="message-sender" onclick="event.stopPropagation(); window.location.href='/profile-member/${message.user_id}'; return false;">${escapeHtml(senderName)}</a>
                    </div>
                    <div class="action-menu message-action" data-action-menu>
                        <button type="button" class="action-menu__toggle"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="action-menu__list">
                            <button type="button" onclick="replyToMessageFromButton(this, '${message.id}')" class="action-menu__item btn-rep"><i class="fas fa-reply"></i> پاسخ</button>
                            <button type="button" class="action-menu__item btn-reaction"><i class="fas fa-smile"></i> واکنش</button>
                            ${([2,3].includes(window.yourRole || 0)) ? `<button type="button" class="action-menu__item btn-pin" onclick="pinMessage('${message.id}')"><i class="fas fa-thumbtack"></i> سنجاق کردن</button>` : ''}
                            <button type="button" class="action-menu__item btn-report"><i class="fas fa-flag"></i> گزارش</button>
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
    
    // Initialize action menu handlers if function exists
    if (typeof initializeMessageActions === 'function') {
        initializeMessageActions(messageRow);
    }
    
    // Initialize action menu toggle
    const menu = messageRow.querySelector('[data-action-menu]');
    if (menu) {
        const toggle = menu.querySelector('.action-menu__toggle');
        const list = menu.querySelector('.action-menu__list');
        
        if (toggle && list) {
            toggle.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                const isOpen = menu.classList.contains('is-open');
                // Close all other menus
                document.querySelectorAll('[data-action-menu].is-open').forEach(m => {
                    if (m !== menu) m.classList.remove('is-open');
                });
                menu.classList.toggle('is-open', !isOpen);
                if (!isOpen) {
                    requestAnimationFrame(() => positionActionMenu(menu));
                }
            });
            
            // بستن منو هنگام کلیک روی دکمه‌های منو (به جز دکمه واکنش)
            list.querySelectorAll('button, a').forEach(item => {
                // برای دکمه واکنش، event listener جداگانه اضافه نمی‌کنیم
                // چون addReactionButton خودش event handler اضافه می‌کند
                if (item.classList.contains('btn-reaction')) {
                    return;
                }
                
                item.addEventListener('click', function(e) {
                    menu.classList.remove('is-open');
                    toggle?.setAttribute('aria-expanded', 'false');
                });
            });
        }
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
function updateBlogUI(blogData) {
    try {
        const blogElement = document.getElementById(`blog-${blogData.id}`);
        if (!blogElement) {
            console.warn('Blog element not found:', blogData.id);
            return;
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
    } catch (error) {
        console.error('Error updating blog UI:', error);
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
        alert(message);
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
  document.querySelectorAll('.poll-timer').forEach(timer => {
    if (timer.dataset.timerSet === "true") return;

    const expiresAtStr = timer.getAttribute('data-expires');
    if (!expiresAtStr) {
      timer.innerText = 'بدون زمان پایان';
      return;
    }

    const expiresAt = new Date(expiresAtStr);

    function updateTimer() {
      const now = new Date();
      const diffMs = expiresAt - now;

      if (isNaN(diffMs)) {
        timer.innerText = 'تاریخ نامعتبر';
        return;
      }

      if (diffMs <= 0) {
        timer.innerText = 'پایان یافته';
        return;
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

    }

    updateTimer();
    setInterval(updateTimer, 1000);

    timer.dataset.timerSet = "true";
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
                            const response = await fetch(form.action, {
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
                                    alert(`${errorMessage}\n${errors}`);
                                } else if (response.status === 500) {
                                    console.error('Server Error Details:', responseData);
                                    alert('خطا در سرور. لطفاً دوباره تلاش کنید. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید.');
                                } else {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                            }
                            
                            if (responseData.status === 'success') {
                                appendMessage(responseData.message);
                                form.reset();
                                clientMessageIdInput.value = '';
                            } else {
                                alert('خطا در ارسال پیام صوتی: ' + (responseData.message || 'خطای ناشناخته'));
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            if (error.message.includes('Failed to fetch')) {
                                alert('خطا در اتصال به سرور. لطفاً اتصال اینترنت خود را بررسی کنید.');
                            } else {
                                alert('خطا در ارسال پیام صوتی. لطفاً دوباره تلاش کنید.');
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
        alert('دسترسی به میکروفون امکان‌پذیر نیست. لطفاً دسترسی را بررسی کنید.');
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
        // Event listener برای لینک‌های پروفایل - باید اول اجرا شود
        chatBoxEl.addEventListener('click', function(e) {
            const link = e.target.closest('a.message-sender');
            if (link && link.href) {
                // اجازه بده لینک کار کند - هیچ کاری نکن
                return;
            }
        }, true); // استفاده از capture phase برای اجرای زودتر

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
        <button class="btn-cancel-reply" onclick="cancelReply()">
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
                alert('پیام با موفقیت ویرایش شد');
            } else {
                alert(response.message || 'خطا در ویرایش پیام');
            }
        },
        error: function() {
            alert('خطا در ارتباط با سرور');
        }
    });
}

function reportMessage(messageId) {
    const reason = prompt('لطفاً دلیل گزارش این پیام را وارد کنید:');
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
                alert('پیام با موفقیت گزارش شد.');
            } else {
                alert('خطا در گزارش پیام.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در گزارش پیام.');
        });
    }
}

function deletePost(postId) {
    if (confirm('آیا از حذف این پست اطمینان دارید؟')) {
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
                var el = document.getElementById('blog-' + postId);
                // remove the outer post-wrapper too so no empty shell remains
                var toRemove = el ? (el.closest('.post-wrapper') || el.parentElement || el) : null;
                if (toRemove) {
                    toRemove.style.transition = 'opacity 0.3s';
                    toRemove.style.opacity = '0';
                    setTimeout(function() { toRemove.remove(); }, 300);
                }
            } else {
                alert(data.message || 'خطا در حذف پست');
            }
        })
        .catch(function() { alert('خطا در ارتباط با سرور'); });
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
        alert('لطفاً عنوان پست را وارد کنید');
        return;
    }
    if (!content.trim()) {
        alert('لطفاً محتوای پست را وارد کنید');
        return;
    }
    if (!categoryId) {
        alert('لطفاً دسته‌بندی را انتخاب کنید');
        return;
    }
    
    try {
        const response = await fetch(`/blog/${postId}`, {
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
            // Replace full post HTML with server-rendered version
            if (data.post && data.post.html) {
                var blogEl = document.getElementById('blog-' + postId);
                // post-card is inside post-wrapper; replace the outer wrapper to preserve correct nesting
                var wrapperEl = blogEl ? (blogEl.closest('.post-wrapper') || blogEl.parentElement || blogEl) : null;
                if (wrapperEl) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = data.post.html;
                    var newEl = tmp.firstElementChild;
                    if (newEl) {
                        wrapperEl.replaceWith(newEl);
                        if (typeof window._initPostMenus === 'function') window._initPostMenus(newEl);
                        if (typeof window._initReactionButtons === 'function') window._initReactionButtons(newEl);
                    }
                }
            } else if (data.blog) {
                updateBlogUI(data.blog);
            }
            showSuccessAlert('پست با موفقیت ویرایش شد');
        } else {
            alert(data.message || 'خطا در ویرایش پست');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور');
    }
}

function openChatSearch() {
    const searchBox = document.createElement('div');
    searchBox.className = 'chat-search-box';
    searchBox.innerHTML = `
        <div class="search-header">
            <input type="text" id="chatSearchInput" placeholder="جستجو در پیام‌ها...">
            <button onclick="closeChatSearch()">×</button>
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

function clearChatHistory() {
    if (confirm('آیا از پاک کردن تاریخچه چت اطمینان دارید؟')) {
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
                alert('تاریخچه چت با موفقیت پاک شد');
            } else {
                alert('خطا در پاک کردن تاریخچه چت');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در پاک کردن تاریخچه چت');
        });
    }
}

function deleteChat() {
    if (confirm('آیا از حذف این چت اطمینان دارید؟ این عمل غیرقابل بازگشت است.')) {
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
                alert('خطا در حذف چت');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در حذف چت');
        });
    }
}

function reportUser() {
    const reportBox = document.createElement('div');
    reportBox.className = 'report-box';
    reportBox.innerHTML = `
        <div class="report-header">
            <h3>گزارش کاربر</h3>
            <button onclick="closeReportBox()">×</button>
        </div>
        <div class="report-content">
            <select id="reportReason">
                <option value="spam">اسپم</option>
                <option value="harassment">آزار و اذیت</option>
                <option value="inappropriate">محتوا نامناسب</option>
                <option value="other">سایر</option>
            </select>
            <textarea id="reportDescription" placeholder="توضیحات بیشتر..."></textarea>
            <button onclick="submitReport()">ارسال گزارش</button>
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
            alert('گزارش با موفقیت ارسال شد');
            closeReportBox();
        } else {
            alert('خطا در ارسال گزارش');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارسال گزارش');
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
                    markedAsRead.add('blog-' + postId);
                    fetch(`/blog/${postId}/mark-read`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).catch(() => {});
                }
                
                // Mark poll as read
                if (element.id.startsWith('poll-') && !markedAsRead.has('poll-' + pollId)) {
                    markedAsRead.add('poll-' + pollId);
                    fetch(`/poll/${pollId}/mark-read`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).catch(() => {});
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
