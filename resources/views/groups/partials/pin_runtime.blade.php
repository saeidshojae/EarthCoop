<script>
// Add pin/unpin functionality
async function pinMessage(messageId) {
    if (!await window.groupChatConfirm('آیا مایل به سنجاق کردن این پیام هستید؟')) return;

    fetch(`/groups/messages/${messageId}/pin`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // ✅ FIXED: No location.reload() - update DOM smoothly
                const messageEl = document.getElementById(`msg-${messageId}`);
                if (messageEl && !messageEl.querySelector('.pinned-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'pinned-badge';
                    badge.textContent = '📌 سنجاق شده';
                    messageEl.appendChild(badge);
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'موفقیت‌آمیز',
                        text: 'پیام با موفقیت سنجاق شد.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    window.groupChatNotify('پیام با موفقیت سنجاق شد.', 'success');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: data.message
                    });
                } else {
                    window.groupChatNotify(data.message, 'error');
                }
            }
        });
}

async function unpinMessage(messageId) {
    if (!await window.groupChatConfirm('آیا مایل به برداشتن این پیام از حالت سنجاق هستید؟')) return;

    fetch(`/groups/messages/${messageId}/unpin`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // ✅ FIXED: No location.reload() - update DOM smoothly
                const messageEl = document.getElementById(`msg-${messageId}`);
                if (messageEl) {
                    const pinnedBadge = messageEl.querySelector('.pinned-badge');
                    if (pinnedBadge) pinnedBadge.remove();
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'موفقیت‌آمیز',
                        text: 'پیام از حالت سنجاق خارج شد.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    window.groupChatNotify('پیام از حالت سنجاق خارج شد.', 'success');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: data.message
                    });
                } else {
                    window.groupChatNotify(data.message, 'error');
                }
            }
        });
}
</script>
