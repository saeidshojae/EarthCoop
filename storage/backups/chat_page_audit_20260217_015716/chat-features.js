// Chat Features - قابلیت‌های جدید چت
(function() {
    'use strict';
    
    const groupId = window.groupId || null;
    const authUserId = window.authUserId || null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    
    // تعریف توابع مدیریت اعضا در window scope قبل از return
    // ========== مدیریت اعضا ==========
    window.showManageMembersModal = async function() {
        console.log('showManageMembersModal called');
        
        // بررسی وجود groupId و csrfToken
        const currentGroupId = window.groupId || groupId;
        const currentCsrfToken = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
        
        console.log('currentGroupId:', currentGroupId);
        console.log('currentCsrfToken:', currentCsrfToken ? 'exists' : 'not found');
        
        if (!currentGroupId) {
            console.error('groupId not found');
            alert('خطا: شناسه گروه یافت نشد');
            return;
        }
        
        if (!currentCsrfToken) {
            console.error('CSRF token not found');
            alert('خطا: توکن امنیتی یافت نشد');
            return;
        }
        
        // صبر کردن برای اطمینان از لود شدن DOM
        let modal = document.getElementById('manageMembersModal');
        let attempts = 0;
        const maxAttempts = 10;
        
        while (!modal && attempts < maxAttempts) {
            await new Promise(resolve => setTimeout(resolve, 100));
            modal = document.getElementById('manageMembersModal');
            attempts++;
        }
        
        console.log('modal element:', modal);
        console.log('attempts:', attempts);
        
        if (!modal) {
            console.error('Manage members modal not found after', maxAttempts, 'attempts');
            // بررسی اینکه آیا دکمه مدیریت اعضا وجود دارد
            const manageBtn = document.getElementById('manage-members-btn');
            console.log('manage-members-btn exists:', !!manageBtn);
            
            // بررسی تمام modal-shell elements
            const allModals = document.querySelectorAll('.modal-shell');
            console.log('All modals found:', allModals.length);
            allModals.forEach((m, i) => {
                console.log(`Modal ${i}:`, m.id, m);
            });
            
            alert('خطا: Modal مدیریت اعضا یافت نشد.\n\nلطفاً:\n1. مطمئن شوید که به عنوان مدیر وارد شده‌اید\n2. صفحه را رفرش کنید\n3. اگر مشکل ادامه داشت، با پشتیبانی تماس بگیرید');
            return;
        }
        
        // حذف inline style display:none !important
        modal.removeAttribute('style');
        modal.style.display = 'flex';
        modal.style.setProperty('display', 'flex', 'important');
        modal.style.zIndex = '99999';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        modal.style.backdropFilter = 'blur(4px)';
        
        // اطمینان از نمایش dialog
        const dialog = modal.querySelector('.modal-shell__dialog');
        if (dialog) {
            dialog.style.display = 'block';
            dialog.style.visibility = 'visible';
            dialog.style.opacity = '1';
            dialog.style.position = 'relative';
            dialog.style.zIndex = '100000';
            dialog.style.minHeight = '200px';
            dialog.style.height = 'auto';
        }
        
        // اطمینان از نمایش form
        const form = modal.querySelector('.modal-shell__form');
        if (form) {
            form.style.display = 'flex';
            form.style.visibility = 'visible';
            form.style.opacity = '1';
            form.style.minHeight = '150px';
            form.style.height = 'auto';
        }
        
        // اطمینان از نمایش members-list
        const membersListEl = document.getElementById('members-list');
        if (membersListEl) {
            membersListEl.style.display = 'block';
            membersListEl.style.visibility = 'visible';
            membersListEl.style.opacity = '1';
            membersListEl.style.minHeight = '100px';
        }
        
        console.log('Modal styles applied:', {
            modalDisplay: window.getComputedStyle(modal).display,
            modalHeight: modal.offsetHeight,
            dialogDisplay: dialog ? window.getComputedStyle(dialog).display : 'not found',
            dialogHeight: dialog ? dialog.offsetHeight : 0,
            formDisplay: form ? window.getComputedStyle(form).display : 'not found',
            formHeight: form ? form.offsetHeight : 0,
            membersListHeight: membersListEl ? membersListEl.offsetHeight : 0
        });
        
        console.log('Modal displayed, calling loadMembers with groupId:', currentGroupId);
        console.log('Modal computed styles:', window.getComputedStyle(modal).display, window.getComputedStyle(modal).zIndex);
        console.log('loadMembers function type:', typeof loadMembers);
        
        // صبر کردن کمی برای اطمینان از رندر شدن modal
        setTimeout(() => {
            console.log('Timeout finished, calling loadMembers...');
            console.log('loadMembers function exists:', typeof window.loadMembers);
            if (typeof window.loadMembers === 'function') {
                console.log('Calling loadMembers now...');
                window.loadMembers(currentGroupId, currentCsrfToken);
            } else {
                console.error('loadMembers is not a function! Available in scope:', Object.keys(window).filter(k => k.includes('load') || k.includes('member')));
                alert('خطا: تابع loadMembers یافت نشد. لطفاً صفحه را رفرش کنید.');
            }
        }, 200);
    };

    window.closeManageMembersModal = function() {
        const modal = document.getElementById('manageMembersModal');
        if (modal) {
            modal.style.display = 'none';
            modal.style.setProperty('display', 'none', 'important');
        }
    };
    
    // تعریف loadMembers در window scope برای دسترسی از همه جا
    window.loadMembers = function(currentGroupId, currentCsrfToken) {
        console.log('loadMembers called with:', { currentGroupId, currentCsrfToken: currentCsrfToken ? 'exists' : 'missing' });
        
        const loadingEl = document.getElementById('members-loading');
        const errorEl = document.getElementById('members-error');
        const errorTextEl = document.getElementById('members-error-text');
        const membersListEl = document.getElementById('members-list');
        
        console.log('Modal elements:', {
            loadingEl: !!loadingEl,
            errorEl: !!errorEl,
            errorTextEl: !!errorTextEl,
            membersListEl: !!membersListEl
        });
        
        if (!loadingEl || !errorEl || !errorTextEl || !membersListEl) {
            console.error('Members modal elements not found', {
                loadingEl: !!loadingEl,
                errorEl: !!errorEl,
                errorTextEl: !!errorTextEl,
                membersListEl: !!membersListEl
            });
            return;
        }

        // نمایش loading
        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        membersListEl.innerHTML = '';
        
        console.log('Fetching members from:', `/groups/${currentGroupId}/members`);

        const membersUrl = `/groups/${currentGroupId}/members`;
        console.log('Final members URL:', membersUrl, 'current location:', window.location.href);

        fetch(membersUrl, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': currentCsrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(async response => {
            console.log('Response status:', response.status, 'Response URL:', response.url);
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Error response body:', errorText);
                throw new Error(`HTTP error! status: ${response.status} | body: ${errorText.substring(0, 200)}...`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Members data received:', data);
            loadingEl.style.display = 'none';
            
            if (data.status === 'success') {
                console.log('Displaying', data.members?.length || 0, 'members');
                if (typeof window.displayMembers === 'function') {
                    window.displayMembers(data.members);
                } else {
                    console.error('displayMembers function not found');
                    errorTextEl.textContent = 'خطا: تابع نمایش اعضا یافت نشد';
                    errorEl.style.display = 'block';
                }
            } else {
                console.error('Error in response:', data.message);
                errorTextEl.textContent = data.message || 'خطا در بارگذاری لیست اعضا';
                errorEl.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading members:', error);
            loadingEl.style.display = 'none';
            errorTextEl.textContent = 'خطا در ارتباط با سرور: ' + error.message;
            errorEl.style.display = 'block';
        });
    }

    // تعریف displayMembers در window scope
    window.displayMembers = function(members) {
        console.log('displayMembers called with:', members);
        const membersListEl = document.getElementById('members-list');
        if (!membersListEl) {
            console.error('members-list element not found');
            return;
        }

        if (members.length === 0) {
            membersListEl.innerHTML = `
                <div class="text-center py-8 text-slate-500">
                    <i class="fas fa-users text-4xl mb-3"></i>
                    <p>هیچ عضوی یافت نشد</p>
                </div>
            `;
            return;
        }

        // مخفی کردن loading و error
        const loadingEl = document.getElementById('members-loading');
        const errorEl = document.getElementById('members-error');
        if (loadingEl) loadingEl.style.display = 'none';
        if (errorEl) errorEl.style.display = 'none';
        
        // استفاده از inline styles برای اطمینان از نمایش
        membersListEl.style.display = 'block';
        membersListEl.style.visibility = 'visible';
        membersListEl.style.opacity = '1';
        membersListEl.style.minHeight = '100px';
        membersListEl.style.height = 'auto';
        membersListEl.style.overflow = 'visible';
        
        const membersHTML = members.map(member => `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: #f8fafc; border-radius: 0.5rem; border: 1px solid #e2e8f0; margin-bottom: 0.5rem; visibility: visible; opacity: 1;" data-user-id="${member.id}">
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #0f172a; margin-bottom: 0.25rem;">${member.name}</div>
                    <div style="font-size: 0.875rem; color: #64748b;">${member.email}</div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; ${
                        member.role === 1 
                            ? 'background: #dcfce7; color: #166534;' 
                            : 'background: #f3f4f6; color: #374151;'
                    }">
                        ${member.role_label}
                    </span>
                    <button 
                        type="button"
                        onclick="if(typeof window.toggleMemberRole === 'function') { window.toggleMemberRole(${member.id}, ${member.role}); } else { alert('تابع تغییر نقش یافت نشد'); }"
                        style="padding: 0.5rem 1rem; background: #2563eb; color: white; border: none; border-radius: 0.5rem; font-size: 0.875rem; cursor: pointer; transition: background 0.2s;"
                        onmouseover="this.style.background='#1d4ed8'"
                        onmouseout="this.style.background='#2563eb'"
                        title="تغییر نقش">
                        <i class="fas fa-exchange-alt" style="margin-left: 0.25rem;"></i>
                        تغییر به ${member.role === 0 ? 'فعال' : 'ناظر'}
                    </button>
                </div>
            </div>
        `).join('');
        
        // Insert HTML
        membersListEl.innerHTML = membersHTML;
        
        // Force reflow to ensure rendering
        void membersListEl.offsetHeight;
        
        // Wait a bit and check again
        setTimeout(() => {
            const firstChild = membersListEl.firstElementChild;
            console.log('Members HTML inserted, length:', membersListEl.innerHTML.length);
            console.log('Members list element styles:', {
                display: window.getComputedStyle(membersListEl).display,
                visibility: window.getComputedStyle(membersListEl).visibility,
                opacity: window.getComputedStyle(membersListEl).opacity,
                height: membersListEl.offsetHeight,
                scrollHeight: membersListEl.scrollHeight,
                children: membersListEl.children.length,
                firstChild: firstChild ? {
                    display: window.getComputedStyle(firstChild).display,
                    height: firstChild.offsetHeight,
                    innerHTML: firstChild.innerHTML.substring(0, 50)
                } : null
            });
            
            // اگر هنوز height 0 است، force کنیم
            if (membersListEl.offsetHeight === 0 && firstChild) {
                console.warn('Height is still 0, forcing display...');
                firstChild.style.display = 'flex';
                firstChild.style.visibility = 'visible';
                firstChild.style.height = 'auto';
                firstChild.style.minHeight = '80px';
            }
        }, 100);
    };
    
    window.toggleMemberRole = function(userId, currentRole) {
        // بررسی وجود groupId و csrfToken
        const currentGroupId = window.groupId || groupId;
        const currentCsrfToken = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
        
        if (!currentGroupId) {
            alert('خطا: شناسه گروه یافت نشد');
            return;
        }
        
        if (!currentCsrfToken) {
            alert('خطا: توکن امنیتی یافت نشد');
            return;
        }
        
        if (!confirm(`آیا مطمئن هستید که می‌خواهید نقش این کاربر را تغییر دهید؟`)) {
            return;
        }

        const button = event.target.closest('button');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> در حال تغییر...';
        }

        fetch(`/groups/${currentGroupId}/users/${userId}/toggle-role`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': currentCsrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // نمایش پیام موفقیت
                const successMsg = document.createElement('div');
                successMsg.className = 'bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4';
                successMsg.innerHTML = `<i class="fas fa-check-circle ml-2"></i> ${data.message}`;
                const membersListEl = document.getElementById('members-list');
                if (membersListEl && membersListEl.parentNode) {
                    membersListEl.parentNode.insertBefore(successMsg, membersListEl);
                    setTimeout(() => successMsg.remove(), 3000);
                }
                
                // بارگذاری مجدد لیست اعضا
                const currentGroupId = window.groupId || groupId;
                const currentCsrfToken = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
                if (currentGroupId && currentCsrfToken) {
                    loadMembers(currentGroupId, currentCsrfToken);
                }
            } else {
                alert(data.message || 'خطا در تغییر نقش');
                if (button) {
                    button.disabled = false;
                    button.innerHTML = `<i class="fas fa-exchange-alt ml-1"></i> تغییر به ${currentRole === 0 ? 'فعال' : 'ناظر'}`;
                }
            }
        })
        .catch(error => {
            console.error('Error toggling role:', error);
            alert('خطا در ارتباط با سرور');
            if (button) {
                button.disabled = false;
                button.innerHTML = `<i class="fas fa-exchange-alt ml-1"></i> تغییر به ${currentRole === 0 ? 'فعال' : 'ناظر'}`;
            }
        });
    };
    
    if (!groupId || !authUserId) {
        console.warn('Chat features: groupId or authUserId not found');
        return;
    }

    // ========== Typing Indicator ==========
    let typingTimeout = null;
    let isTyping = false;
    const typingUsers = new Set();
    
    const messageInput = document.getElementById('message_editor');
    const typingIndicator = document.createElement('div');
    typingIndicator.id = 'typing-indicator';
    typingIndicator.className = 'typing-indicator';
    typingIndicator.style.display = 'none';
    typingIndicator.innerHTML = '<span>در حال تایپ...</span>';
    
    if (messageInput) {
        const chatBox = document.getElementById('chat-box');
        if (chatBox) {
            chatBox.appendChild(typingIndicator);
        }

        messageInput.addEventListener('input', function() {
            if (!isTyping) {
                isTyping = true;
                sendTypingStatus(true);
            }
            
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                isTyping = false;
                sendTypingStatus(false);
            }, 1000);
        });

        messageInput.addEventListener('blur', function() {
            clearTimeout(typingTimeout);
            isTyping = false;
            sendTypingStatus(false);
        });
    }

    function sendTypingStatus(typing) {
        fetch(`/groups/${groupId}/typing`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ is_typing: typing })
        }).catch(err => console.error('Typing error:', err));
    }

    // ========== Mention Autocomplete ==========
    let mentionDropdown = null;
    let mentionStart = -1;
    let mentionQuery = '';
    
    function initMentionAutocomplete() {
        const textarea = document.getElementById('message_editor');
        if (!textarea) {
            setTimeout(initMentionAutocomplete, 500);
            return;
        }
        
        // Wait for CKEditor to be ready
        setTimeout(() => {
            const ckEditor = window.CKEDITOR && window.CKEDITOR.instances && window.CKEDITOR.instances.message_editor;
            
            if (ckEditor) {
                // CKEditor is used - listen to key events
                ckEditor.on('key', function(e) {
                    const keyCode = e.data.keyCode;
                    const char = String.fromCharCode(keyCode);
                    
                    // Check if @ was typed
                    if (char === '@' || e.data.domEvent.$.key === '@') {
                        setTimeout(() => {
                            checkMentionInCKEditor(ckEditor);
                        }, 100);
                    } else {
                        // Check for mention on any key after @
                        const data = ckEditor.getData();
                        const plainText = data.replace(/<[^>]*>/g, '');
                        if (plainText.includes('@')) {
                            setTimeout(() => {
                                checkMentionInCKEditor(ckEditor);
                            }, 100);
                        } else {
                            hideMentionDropdown();
                        }
                    }
                });
                
                ckEditor.on('contentDom', function() {
                    ckEditor.document.on('keyup', function(e) {
                        setTimeout(() => {
                            checkMentionInCKEditor(ckEditor);
                        }, 100);
                    });
                });
            } else {
                // Regular textarea
                textarea.addEventListener('input', function(e) {
                    const cursorPos = this.selectionStart || this.value.length;
                    const text = this.value;
                    checkMentionInTextarea(text, cursorPos);
                });
            }
        }, 1500); // Wait for CKEditor initialization
    }
    
    function checkMentionInCKEditor(ckEditor) {
        try {
            const data = ckEditor.getData();
            const plainText = data.replace(/<[^>]*>/g, '');
            const selection = ckEditor.getSelection();
            const range = selection.getRanges()[0];
            
            if (!range) return;
            
            // Get cursor position in plain text
            let cursorPos = 0;
            const walker = new CKEDITOR.dom.walker(range.clone());
            let node;
            while ((node = walker.next())) {
                if (node.type === CKEDITOR.NODE_TEXT) {
                    cursorPos += node.getText().length;
                }
            }
            
            checkMentionInTextarea(plainText, cursorPos);
        } catch(err) {
            console.error('CKEditor mention check error:', err);
        }
    }
    
    function checkMentionInTextarea(text, cursorPos) {
        try {
            const beforeCursor = text.substring(0, cursorPos);
            // Match @ followed by any characters (including Persian) - but not if it's already @[number]
            const match = beforeCursor.match(/@(?!\[)([^\s@\[\]]*)$/);
            
            if (match) {
                mentionStart = cursorPos - match[0].length;
                mentionQuery = match[1];
                
                // Show dropdown even with just @ or with query
                searchMentionUsers(mentionQuery);
            } else {
                hideMentionDropdown();
            }
        } catch(err) {
            console.error('Mention check error:', err);
        }
    }

    // Setup keydown for mention dropdown
    document.addEventListener('keydown', function(e) {
        if (mentionDropdown && mentionDropdown.style.display !== 'none') {
            const items = mentionDropdown.querySelectorAll('.mention-item');
            const active = mentionDropdown.querySelector('.mention-item.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (active) {
                    active.classList.remove('active');
                    const next = active.nextElementSibling || items[0];
                    if (next) next.classList.add('active');
                } else if (items[0]) {
                    items[0].classList.add('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (active) {
                    active.classList.remove('active');
                    const prev = active.previousElementSibling || items[items.length - 1];
                    if (prev) prev.classList.add('active');
                } else if (items[items.length - 1]) {
                    items[items.length - 1].classList.add('active');
                }
            } else if (e.key === 'Enter' && active) {
                e.preventDefault();
                insertMention(active.dataset.userId, active.dataset.userName);
            } else if (e.key === 'Escape') {
                hideMentionDropdown();
            }
        }
    });


    function insertMention(userId, userName) {
        const mention = `@[${userId}]${userName} `;
        const textarea = document.getElementById('message_editor');
        const ckEditor = window.CKEDITOR && window.CKEDITOR.instances && window.CKEDITOR.instances.message_editor;
        
        if (ckEditor) {
            // Insert into CKEditor
            const data = ckEditor.getData();
            const plainText = data.replace(/<[^>]*>/g, '');
            const before = plainText.substring(0, mentionStart);
            const after = plainText.substring(mentionStart + mentionQuery.length + 1);
            const newText = before + mention + after;
            ckEditor.setData(newText);
            ckEditor.focus();
        } else if (textarea) {
            // Insert into textarea
            const text = textarea.value;
            const before = text.substring(0, mentionStart);
            const after = text.substring(mentionStart + mentionQuery.length + 1);
            textarea.value = before + mention + after;
            textarea.focus();
            textarea.setSelectionRange(before.length + mention.length, before.length + mention.length);
        }
        hideMentionDropdown();
    }
    
    function showMentionDropdown(users) {
        const textarea = document.getElementById('message_editor');
        if (!textarea) return;
        
        if (!mentionDropdown) {
            mentionDropdown = document.createElement('div');
            mentionDropdown.className = 'mention-dropdown';
            mentionDropdown.style.cssText = `
                position: absolute;
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-height: 200px;
                overflow-y: auto;
                z-index: 10000;
                display: none;
                direction: rtl;
                min-width: 250px;
            `;
            const inputContainer = textarea.closest('.flex-fill') || textarea.parentElement;
            if (inputContainer) {
                inputContainer.style.position = 'relative';
                inputContainer.appendChild(mentionDropdown);
            }
        }
        
        // Position dropdown below input
        const rect = textarea.getBoundingClientRect();
        const container = mentionDropdown.parentElement;
        if (container) {
            mentionDropdown.style.top = '100%';
            mentionDropdown.style.left = '0';
            mentionDropdown.style.right = '0';
            mentionDropdown.style.marginTop = '5px';
        }

        if (users.length === 0) {
            mentionDropdown.innerHTML = '<div class="mention-item" style="padding: 8px; color: #999;">کاربری یافت نشد</div>';
        } else {
            mentionDropdown.innerHTML = users.map((user, index) => `
                <div class="mention-item ${index === 0 ? 'active' : ''}" 
                     data-user-id="${user.id}" 
                     data-user-name="${user.name}"
                     style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;">
                    <strong>${user.name}</strong>
                </div>
            `).join('');

            mentionDropdown.querySelectorAll('.mention-item').forEach(item => {
                item.addEventListener('click', function() {
                    insertMention(this.dataset.userId, this.dataset.userName);
                });
                item.addEventListener('mouseenter', function() {
                    mentionDropdown.querySelectorAll('.mention-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        }

        mentionDropdown.style.display = 'block';
    }

    function hideMentionDropdown() {
        if (mentionDropdown) {
            mentionDropdown.style.display = 'none';
        }
    }

    function searchMentionUsers(query) {
        // Debounce search
        clearTimeout(window.mentionSearchTimeout);
        window.mentionSearchTimeout = setTimeout(() => {
            fetch(`/groups/${groupId}/mention-users?q=${encodeURIComponent(query || '')}`, {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.json();
            })
            .then(users => {
                if (Array.isArray(users)) {
                    showMentionDropdown(users);
                }
            })
            .catch(err => {
                console.error('Mention search error:', err);
                hideMentionDropdown();
            });
        }, 300);
    }
    
    // Initialize mention when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initMentionAutocomplete, 1500); // Wait for CKEditor
        });
    } else {
        setTimeout(initMentionAutocomplete, 1500); // Wait for CKEditor
    }

    // ========== Read Receipts ==========
    function markMessageAsRead(messageId) {
        if (!messageId) return;
        
        fetch(`/messages/${messageId}/mark-read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        }).catch(err => console.error('Mark read error:', err));
    }

    // Mark messages as read when visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const messageId = entry.target.getAttribute('data-message-id');
                if (messageId && entry.target.querySelector('.message-bubble')) {
                    const messageBubble = entry.target.querySelector('.message-bubble');
                    const userId = messageBubble?.getAttribute('data-user-id') || messageBubble?.dataset?.userId;
                    if (userId && userId != authUserId) {
                        markMessageAsRead(messageId);
                    }
                }
            }
        });
    }, { threshold: 0.5 });

    // Observe existing messages after page load
    setTimeout(() => {
        document.querySelectorAll('[data-message-id]').forEach(msg => {
            observer.observe(msg);
        });
    }, 1000);

    // Observe new messages added dynamically
    const chatBox = document.getElementById('chat-box');
    if (chatBox) {
        const messageObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1 && node.hasAttribute && node.hasAttribute('data-message-id')) {
                        observer.observe(node);
                    }
                });
            });
        });
        messageObserver.observe(chatBox, { childList: true, subtree: true });
    }

    // ========== Message Reactions ==========
    function addReactionButton(messageElement) {
        const messageId = messageElement.getAttribute('data-message-id');
        if (!messageId) return;

        const actionMenu = messageElement.querySelector('.action-menu__list');
        if (!actionMenu) return;

        // Check if reaction button already exists
        let reactionBtn = actionMenu.querySelector('.btn-reaction');
        
        if (!reactionBtn) {
            // Create new button if it doesn't exist
            reactionBtn = document.createElement('button');
            reactionBtn.type = 'button';
            reactionBtn.className = 'action-menu__item btn-reaction';
            reactionBtn.innerHTML = '<i class="fas fa-smile"></i> واکنش';

            // Insert after reply button (or after first button if reply doesn't exist)
            const replyBtn = actionMenu.querySelector('.btn-rep');
            if (replyBtn && replyBtn.nextSibling) {
                // Insert after reply button
                actionMenu.insertBefore(reactionBtn, replyBtn.nextSibling);
            } else if (replyBtn) {
                // If reply is the last item, append after it
                actionMenu.appendChild(reactionBtn);
            } else {
                // If no reply button, insert before delete button or at the beginning
                const deleteBtn = actionMenu.querySelector('.btn-delete');
                if (deleteBtn) {
                    actionMenu.insertBefore(reactionBtn, deleteBtn);
                } else {
                    // Insert at the beginning, before menu-meta-time if exists
                    const metaTime = actionMenu.querySelector('.menu-meta-time');
                    if (metaTime) {
                        actionMenu.insertBefore(reactionBtn, metaTime);
                    } else {
                        actionMenu.appendChild(reactionBtn);
                    }
                }
            }
        }
        
        // Add or update event handler (even if button already exists in HTML)
        reactionBtn.onclick = (e) => {
            e.stopPropagation();
            e.preventDefault();
            showReactionPicker(messageId, e.target.closest('.message-bubble'));
        };
    }

    function showReactionPicker(messageId, triggerElement) {
        const reactions = ['like', 'love', 'laugh', 'wow', 'sad', 'angry'];
        const emojis = { like: '👍', love: '❤️', laugh: '😂', wow: '😮', sad: '😢', angry: '😠' };
        
        // Remove existing picker if any
        const existingPicker = document.querySelector('.reaction-picker');
        if (existingPicker) {
            existingPicker.remove();
        }

        // Fallback برای پیدا کردن المان پیام
        const targetElement = triggerElement || document.querySelector(`[data-message-id="${messageId}"]`);
        if (!targetElement) {
            return;
        }

        // اطمینان از این‌که ظرف پیام position: relative دارد تا picker به آن نسبی باشد
        const container = targetElement.closest('.message-row') || targetElement;
        if (container && getComputedStyle(container).position === 'static') {
            container.style.position = 'relative';
        }
        
        const picker = document.createElement('div');
        picker.className = 'reaction-picker';
        picker.style.cssText = `
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 6px 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            gap: 6px;
            align-items: center;
        `;

        reactions.forEach(reaction => {
            const btn = document.createElement('button');
            btn.innerHTML = emojis[reaction];
            btn.style.cssText = 'font-size: 20px; border: none; background: none; cursor: pointer; padding: 2px;';
            btn.onclick = (e) => {
                e.stopPropagation();
                toggleReaction(messageId, reaction);
                picker.remove();
            };
            picker.appendChild(btn);
        });

        // اضافه کردن picker داخل ظرف پیام تا با اسکرول جابه‌جا شود
        (container || document.body).appendChild(picker);
        
        // Position picker: بالای حباب پیام، با کنترل لبه‌های صفحه در موبایل
        const bubbleRect = targetElement.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;

        // محاسبه موقعیت افقی نسبت به container
        let left = bubbleRect.left - containerRect.left + (bubbleRect.width / 2) - (picker.offsetWidth / 2);
        // اطمینان از این‌که در موبایل/دسکتاپ از صفحه بیرون نزند
        const minLeft = 8;
        const maxLeft = viewportWidth - picker.offsetWidth - 8 - containerRect.left;
        left = Math.min(Math.max(left, minLeft), maxLeft);

        picker.style.left = `${left}px`;
        picker.style.bottom = `${containerRect.bottom - bubbleRect.top + 8}px`;

        // بستن با کلیک بیرون
        setTimeout(() => {
            const closePicker = function(e) {
                if (!picker.contains(e.target) && !targetElement.contains(e.target)) {
                    picker.remove();
                    document.removeEventListener('click', closePicker);
                }
            };
            document.addEventListener('click', closePicker);
        }, 100);
    }

    window.toggleReaction = function(messageId, reactionType) {
        fetch(`/messages/${messageId}/reaction`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ reaction_type: reactionType })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                updateReactionsDisplay(messageId, data.reactions);
            }
        })
        .catch(err => console.error('Reaction error:', err));
    };

    function updateReactionsDisplay(messageId, reactions) {
        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageElement) {
            console.warn('Message element not found for messageId:', messageId);
            return;
        }

        let reactionDisplay = messageElement.querySelector('.message-reactions');
        
        // If no reactions, remove display if exists
        if (!reactions || reactions.length === 0) {
            if (reactionDisplay) {
                reactionDisplay.remove();
            }
            return;
        }

        if (!reactionDisplay) {
            reactionDisplay = document.createElement('div');
            reactionDisplay.className = 'message-reactions';
            reactionDisplay.style.cssText = 'display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;';
            const messageBubble = messageElement.querySelector('.message-bubble');
            if (messageBubble) {
                messageBubble.appendChild(reactionDisplay);
            } else {
                messageElement.appendChild(reactionDisplay);
            }
        }

        reactionDisplay.innerHTML = reactions.map(r => `
            <span class="reaction-badge" style="
                background: #f0f0f0;
                padding: 2px 6px;
                border-radius: 12px;
                font-size: 12px;
                cursor: pointer;
            " onclick="toggleReaction(${messageId}, '${r.type}')">
                ${getReactionEmoji(r.type)} ${r.count}
            </span>
        `).join('');
    }

    function getReactionEmoji(type) {
        const emojis = { like: '👍', love: '❤️', laugh: '😂', wow: '😮', sad: '😢', angry: '😠' };
        return emojis[type] || '👍';
    }

    // Initialize reactions for existing messages
    document.querySelectorAll('[data-message-id]').forEach(msg => {
        addReactionButton(msg);
    });

    // ========== Voice Message ==========
    // Voice recording is now handled by voice-recorder.js
    // This section is kept for future file upload functionality if needed

    // ========== Threading ==========
    function addThreadButton(messageElement) {
        const messageId = messageElement.getAttribute('data-message-id');
        if (!messageId) return;

        const actionMenu = messageElement.querySelector('.action-menu__list');
        if (!actionMenu) return;

        if (actionMenu.querySelector('.btn-thread')) return;

        const threadBtn = document.createElement('button');
        threadBtn.type = 'button';
        threadBtn.className = 'action-menu__item btn-thread';
        threadBtn.innerHTML = '<i class="fas fa-comments"></i> Thread';
        threadBtn.onclick = () => showThread(messageId);

        const replyBtn = actionMenu.querySelector('.btn-rep');
        if (replyBtn) {
            actionMenu.insertBefore(threadBtn, replyBtn.nextSibling);
        } else {
            actionMenu.appendChild(threadBtn);
        }
    }

    window.showThread = function(messageId) {
        fetch(`/messages/${messageId}/thread`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Show thread modal
                const modal = document.createElement('div');
                modal.style.cssText = `
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.5);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                `;
                const modalContent = document.createElement('div');
                modalContent.style.cssText = 'background: white; padding: 2rem; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; direction: rtl;';
                modalContent.innerHTML = `
                    <h3 style="margin-bottom: 1rem;">Thread: ${data.reply_count} پاسخ</h3>
                    <div style="margin-bottom: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 8px;">
                        <strong>${data.thread_root.sender}</strong>
                        <p>${data.thread_root.message}</p>
                        <small>${data.thread_root.created_at}</small>
                    </div>
                    <div>
                        <h4>پاسخ‌ها:</h4>
                        ${data.replies.map(reply => `
                            <div style="margin: 1rem 0; padding: 1rem; background: #f9fafb; border-radius: 8px; border-right: 3px solid #10b981;">
                                <strong>${reply.sender}</strong>
                                <p>${reply.message}</p>
                                <small>${reply.created_at}</small>
                            </div>
                        `).join('')}
                    </div>
                    <button class="thread-modal-close-btn" 
                            style="margin-top: 1rem; padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        بستن
                    </button>
                `;
                modal.appendChild(modalContent);
                document.body.appendChild(modal);
                
                // Event listener برای بستن modal با کلیک روی دکمه
                const closeBtn = modalContent.querySelector('.thread-modal-close-btn');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        modal.remove();
                    });
                }
                
                // Event listener برای بستن modal با کلیک روی background (فقط روی modal خودش، نه روی content)
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.remove();
                    }
                });
                
                // جلوگیری از بسته شدن modal هنگام کلیک روی محتوا
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        })
        .catch(err => console.error('Thread error:', err));
    };

    // Initialize threading for existing messages
    document.querySelectorAll('[data-message-id]').forEach(msg => {
        addThreadButton(msg);
    });

    // ========== Group Settings ==========

    // ========== مدیریت گزارش‌ها ==========
    window.showManageReportsModal = function() {
        const modal = document.getElementById('manageReportsModal');
        if (!modal) {
            console.error('Manage reports modal not found');
            return;
        }
        
        modal.style.display = 'flex';
        modal.style.setProperty('display', 'flex', 'important');
        
        loadReports();
    };

    window.closeManageReportsModal = function() {
        const modal = document.getElementById('manageReportsModal');
        if (modal) {
            modal.style.display = 'none';
            modal.style.setProperty('display', 'none', 'important');
        }
    };

    function loadReports() {
        const loadingEl = document.getElementById('reports-loading');
        const errorEl = document.getElementById('reports-error');
        const errorTextEl = document.getElementById('reports-error-text');
        const reportsListEl = document.getElementById('reports-list');
        
        if (!loadingEl || !errorEl || !errorTextEl || !reportsListEl) {
            console.error('Reports modal elements not found');
            return;
        }

        // نمایش loading
        loadingEl.style.display = 'block';
        errorEl.style.display = 'none';
        reportsListEl.innerHTML = '';

        fetch(`/groups/${groupId}/reports`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            loadingEl.style.display = 'none';
            
            if (data.status === 'success') {
                displayReports(data.reports);
                updateReportsBadge(data.reports.length);
            } else {
                errorTextEl.textContent = data.message || 'خطا در بارگذاری لیست گزارش‌ها';
                errorEl.style.display = 'block';
            }
        })
        .catch(error => {
            loadingEl.style.display = 'none';
            errorTextEl.textContent = 'خطا در ارتباط با سرور';
            errorEl.style.display = 'block';
            console.error('Error loading reports:', error);
        });
    }

    function displayReports(reports) {
        const reportsListEl = document.getElementById('reports-list');
        if (!reportsListEl) return;

        if (reports.length === 0) {
            reportsListEl.innerHTML = `
                <div class="text-center py-8 text-slate-500">
                    <i class="fas fa-flag text-4xl mb-3"></i>
                    <p>هیچ گزارشی در انتظار بررسی وجود ندارد</p>
                </div>
            `;
            return;
        }

        reportsListEl.innerHTML = reports.map(report => `
            <div class="bg-slate-50 dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600 p-4" data-report-id="${report.id}">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full font-semibold">
                                گزارش از: ${report.reporter_name}
                            </span>
                            <span class="text-xs text-slate-500">${report.created_at_human}</span>
                        </div>
                        <div class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                            <strong>دلیل گزارش:</strong> ${report.reason}
                        </div>
                        ${report.description ? `
                            <div class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                                <strong>توضیحات:</strong> ${report.description}
                            </div>
                        ` : ''}
                        <div class="text-sm text-slate-600 dark:text-slate-400">
                            <strong>پیام:</strong> ${report.message_content.substring(0, 100)}${report.message_content.length > 100 ? '...' : ''}
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            نویسنده پیام: ${report.message_author}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-200 dark:border-slate-600">
                    <button 
                        type="button"
                        onclick="handleReportAction(${report.id}, 'resolve')"
                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm"
                        title="حل کردن - پیام را حذف می‌کند">
                        <i class="fas fa-check ml-1"></i>
                        حل کردن
                    </button>
                    <button 
                        type="button"
                        onclick="handleReportAction(${report.id}, 'dismiss')"
                        class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm"
                        title="رد کردن - گزارش را رد می‌کند">
                        <i class="fas fa-times ml-1"></i>
                        رد کردن
                    </button>
                    <button 
                        type="button"
                        onclick="handleReportAction(${report.id}, 'escalate')"
                        class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm"
                        title="ارجاع به ادمین سایت">
                        <i class="fas fa-arrow-up ml-1"></i>
                        ارجاع به ادمین
                    </button>
                </div>
            </div>
        `).join('');
    }

    function updateReportsBadge(count) {
        const badge = document.getElementById('reports-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    window.handleReportAction = function(reportId, action) {
        const actionLabels = {
            'resolve': 'حل کردن',
            'dismiss': 'رد کردن',
            'escalate': 'ارجاع به ادمین'
        };

        if (!confirm(`آیا مطمئن هستید که می‌خواهید این گزارش را "${actionLabels[action]}" کنید؟`)) {
            return;
        }

        const reportElement = document.querySelector(`[data-report-id="${reportId}"]`);
        const buttons = reportElement?.querySelectorAll('button');
        if (buttons) {
            buttons.forEach(btn => btn.disabled = true);
        }

        fetch(`/groups/${groupId}/reports/${reportId}/review`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: action })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // نمایش پیام موفقیت
                const successMsg = document.createElement('div');
                successMsg.className = 'bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4';
                successMsg.innerHTML = `<i class="fas fa-check-circle ml-2"></i> ${data.message}`;
                const reportsListEl = document.getElementById('reports-list');
                if (reportsListEl && reportsListEl.parentNode) {
                    reportsListEl.parentNode.insertBefore(successMsg, reportsListEl);
                    setTimeout(() => successMsg.remove(), 3000);
                }
                
                // بارگذاری مجدد لیست گزارش‌ها
                loadReports();
            } else {
                alert(data.message || 'خطا در انجام عملیات');
                if (buttons) {
                    buttons.forEach(btn => btn.disabled = false);
                }
            }
        })
        .catch(error => {
            console.error('Error handling report action:', error);
            alert('خطا در ارتباط با سرور');
            if (buttons) {
                buttons.forEach(btn => btn.disabled = false);
            }
        });
    };

    // بارگذاری تعداد گزارش‌های در انتظار بررسی هنگام لود صفحه
    // فقط برای مدیران (role === 3)
    const userRole = window.yourRole || (typeof yourRole !== 'undefined' ? yourRole : null);
    if (typeof groupId !== 'undefined' && groupId && userRole === 3) {
        fetch(`/groups/${groupId}/reports`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok && response.status === 403) {
                // کاربر مدیر نیست، خطا را ignore کن
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (data && data.status === 'success') {
                updateReportsBadge(data.reports.length);
            }
        })
        .catch(error => {
            // فقط خطاهای غیر 403 را log کن
            if (error.status !== 403) {
                console.error('Error loading reports count:', error);
            }
        });
    }

    window.showGroupSettingsModal = function() {
        // بررسی وجود groupId و csrfToken
        const currentGroupId = window.groupId || groupId;
        const currentCsrfToken = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content;
        
        if (!currentGroupId) {
            console.error('groupId not found');
            alert('خطا: شناسه گروه یافت نشد');
            return;
        }
        
        if (!currentCsrfToken) {
            console.error('CSRF token not found');
            alert('خطا: توکن امنیتی یافت نشد');
            return;
        }
        
        fetch(`/groups/${currentGroupId}/settings`, {
            method: 'GET',
            headers: { 
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const settings = data.settings;
                
                // Create modal با استفاده از modal-shell style
                const modal = document.createElement('div');
                modal.id = 'groupSettingsModal';
                modal.className = 'modal-shell';
                modal.style.cssText = 'display: flex; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem; direction: rtl;';
                modal.setAttribute('onclick', 'handleModalClick(event, "groupSettingsModal")');
                
                modal.innerHTML = `
                    <div class="modal-shell__dialog" onclick="event.stopPropagation()" style="position: relative; width: min(500px, 94vw); background: #fff; border-radius: 28px; padding: 1.75rem; box-shadow: 0 45px 95px -45px rgba(15, 23, 42, 0.6);">
                        <div class="modal-shell__header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                            <h3 class="modal-shell__title" style="font-size: 1.1rem; font-weight: 800; color: #0f4c3a; display: flex; align-items: center; gap: .5rem;">
                                <i class="fas fa-cog me-2 text-emerald-500"></i>
                                تنظیمات گروه
                            </h3>
                            <button type="button" class="modal-shell__close" onclick="closeGroupSettingsModal()" style="border: none; background: rgba(248, 250, 252, 0.9); width: 34px; height: 34px; border-radius: 999px; font-size: 1.2rem; color: #334155; display: flex; align-items: center; justify-content: center; cursor: pointer;">×</button>
                        </div>
                        <div class="modal-shell__form" style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; flex-direction: column; gap: .45rem;">
                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 12px; background: #f8fafc; border-radius: 12px; transition: background 0.2s;">
                                    <input type="checkbox" id="mute-checkbox" ${settings.muted ? 'checked' : ''} style="width: 18px; height: 18px; cursor: pointer;">
                                    <span style="font-weight: 600; color: #0f172a;">بی‌صدا کردن گروه</span>
                                </label>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: .45rem;">
                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 12px; background: #f8fafc; border-radius: 12px; transition: background 0.2s;">
                                    <input type="checkbox" id="archive-checkbox" ${settings.archived ? 'checked' : ''} style="width: 18px; height: 18px; cursor: pointer;">
                                    <span style="font-weight: 600; color: #0f172a;">بایگانی کردن گروه</span>
                                </label>
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: .75rem; margin-top: .5rem;">
                                <button onclick="closeGroupSettingsModal()" style="padding: 0.75rem 1.5rem; background: #e2e8f0; color: #475569; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                                    بستن
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                
                // Event listeners برای checkbox ها
                const muteCheckbox = document.getElementById('mute-checkbox');
                const archiveCheckbox = document.getElementById('archive-checkbox');
                
                if (muteCheckbox) {
                    muteCheckbox.addEventListener('change', function() {
                        toggleMuteSetting(currentGroupId, currentCsrfToken, this.checked);
                    });
                }
                
                if (archiveCheckbox) {
                    archiveCheckbox.addEventListener('change', function() {
                        toggleArchiveSetting(currentGroupId, currentCsrfToken, this.checked);
                    });
                }
            } else {
                alert(data.message || 'خطا در بارگذاری تنظیمات');
            }
        })
        .catch(err => {
            console.error('Settings error:', err);
            alert('خطا در ارتباط با سرور');
        });
    };
    
    window.closeGroupSettingsModal = function() {
        const modal = document.getElementById('groupSettingsModal');
        if (modal) {
            modal.remove();
        }
    };
    
    function toggleMuteSetting(groupId, csrfToken, muted) {
        fetch(`/groups/${groupId}/toggle-mute`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('Mute toggled:', data.muted);
            } else {
                alert(data.message || 'خطا در تغییر وضعیت بی‌صدا');
            }
        })
        .catch(err => {
            console.error('Toggle mute error:', err);
            alert('خطا در ارتباط با سرور');
        });
    }
    
    function toggleArchiveSetting(groupId, csrfToken, archived) {
        fetch(`/groups/${groupId}/toggle-archive`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('Archive toggled:', data.archived);
            } else {
                alert(data.message || 'خطا در تغییر وضعیت بایگانی');
            }
        })
        .catch(err => {
            console.error('Toggle archive error:', err);
            alert('خطا در ارتباط با سرور');
        });
    }

})();

