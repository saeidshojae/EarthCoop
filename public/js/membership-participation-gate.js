(() => {
    'use strict';

    function renderGate() {
        const config = window.GroupChatConfig && window.GroupChatConfig.membershipParticipation;
        if (!config || config.eligible) return;

        const form = document.getElementById('chatForm');
        if (!form) return;

        const noAccount = config.status === 'no_najm_bahar_account';
        const feeDue = config.status === 'membership_fee_due';
        if (!noAccount && !feeDue) return;

        const card = document.createElement('div');
        card.className = noAccount
            ? 'rounded-2xl border border-blue-200 bg-blue-50 p-5 text-right'
            : 'rounded-2xl border border-amber-200 bg-amber-50 p-5 text-right';
        card.setAttribute('role', 'status');
        card.setAttribute('data-membership-participation-gate', config.status);

        const title = document.createElement('strong');
        title.className = noAccount ? 'block text-blue-950' : 'block text-amber-950';
        title.textContent = noAccount
            ? 'برای مشارکت، ابتدا حساب نجم بهار را فعال کنید'
            : 'برای مشارکت، حق عضویت دوره جاری را پرداخت کنید';

        const description = document.createElement('p');
        description.className = noAccount
            ? 'mt-2 mb-0 leading-7 text-blue-900'
            : 'mt-2 mb-0 leading-7 text-amber-900';
        description.textContent = noAccount
            ? 'ورود به فعالیت‌های مشارکتی EarthCoop پس از مطالعه و تأیید توافقنامه مالی نجم بهار و ایجاد حساب اصلی امکان‌پذیر است. شما همچنان می‌توانید پیام‌ها و فعالیت‌های این گروه را مشاهده کنید.'
            : 'پرداخت حق عضویت، نخستین اقدام مشارکت آگاهانه در EarthCoop است. پس از پرداخت دوره جاری، باکس ارسال پیام برای شما فعال می‌شود. مشاهده محتوای گروه همچنان آزاد است.';

        const action = document.createElement('a');
        action.className = noAccount
            ? 'mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-700 px-5 py-3 text-white font-bold no-underline'
            : 'mt-4 inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-3 text-white font-bold no-underline';
        action.href = noAccount ? config.agreementUrl : config.dashboardUrl;
        action.textContent = noAccount
            ? 'مشاهده و تأیید توافقنامه مالی'
            : 'رفتن به نجم بهار و پرداخت حق عضویت';

        card.appendChild(title);
        card.appendChild(description);
        card.appendChild(action);
        form.replaceWith(card);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderGate, { once: true });
    } else {
        renderGate();
    }
})();
