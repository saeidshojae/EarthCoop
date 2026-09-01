const conversationPage = document.querySelector('[data-private-conversation]');

if (conversationPage) {
    const VIEWPORT_GUTTER = 8;
    let activeTrigger = null;
    let activePicker = null;

    const closePicker = () => {
        if (!activePicker) return;
        activePicker.classList.remove('show');
        activePicker.style.removeProperty('position');
        activePicker.style.removeProperty('left');
        activePicker.style.removeProperty('right');
        activePicker.style.removeProperty('top');
        activePicker.style.removeProperty('bottom');
        activePicker.style.removeProperty('z-index');
        activePicker = null;
        activeTrigger = null;
    };

    const positionReactionPicker = (trigger, picker) => {
        if (!trigger || !picker || !picker.classList.contains('show')) return;

        if (picker.parentElement !== document.body) {
            document.body.appendChild(picker);
        }

        picker.style.position = 'fixed';
        picker.style.left = '0px';
        picker.style.right = 'auto';
        picker.style.top = '0px';
        picker.style.bottom = 'auto';
        picker.style.zIndex = '20050';

        const triggerRect = trigger.getBoundingClientRect();
        const pickerRect = picker.getBoundingClientRect();
        const viewportWidth = document.documentElement.clientWidth;
        const viewportHeight = document.documentElement.clientHeight;

        const preferredLeft = triggerRect.left + ((triggerRect.width - pickerRect.width) / 2);
        const maxLeft = Math.max(VIEWPORT_GUTTER, viewportWidth - pickerRect.width - VIEWPORT_GUTTER);
        const clampedLeft = Math.min(Math.max(preferredLeft, VIEWPORT_GUTTER), maxLeft);

        let top = triggerRect.top - pickerRect.height - VIEWPORT_GUTTER;
        if (top < VIEWPORT_GUTTER) {
            top = Math.min(
                triggerRect.bottom + VIEWPORT_GUTTER,
                viewportHeight - pickerRect.height - VIEWPORT_GUTTER
            );
        }

        picker.style.left = `${Math.round(clampedLeft)}px`;
        picker.style.top = `${Math.max(VIEWPORT_GUTTER, Math.round(top))}px`;

        activeTrigger = trigger;
        activePicker = picker;
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.reaction-trigger-btn');
        if (!trigger) return;

        // The existing Blade handler owns open/close state. Position after that
        // handler has toggled the picker so this runtime only owns geometry.
        requestAnimationFrame(() => {
            const picker = document.querySelector(`.reaction-picker[data-picker-for="${trigger.dataset.messageId}"]`);
            if (!picker?.classList.contains('show')) {
                closePicker();
                return;
            }
            positionReactionPicker(trigger, picker);
        });
    }, true);

    document.addEventListener('click', (event) => {
        if (event.target.closest('.reaction-picker, .reaction-trigger-btn')) return;
        closePicker();
    });

    window.addEventListener('resize', () => {
        if (activeTrigger && activePicker?.classList.contains('show')) {
            positionReactionPicker(activeTrigger, activePicker);
        }
    });

    document.addEventListener('scroll', () => {
        if (activePicker?.classList.contains('show')) closePicker();
    }, true);
}
