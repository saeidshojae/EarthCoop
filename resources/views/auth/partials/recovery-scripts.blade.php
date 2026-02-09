<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-password-toggle]').forEach(trigger => {
            trigger.addEventListener('click', () => {
                const targetId = trigger.getAttribute('data-password-toggle');
                const input = document.getElementById(targetId);

                if (!input) {
                    return;
                }

                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');

                trigger.querySelectorAll('[data-password-icon]').forEach(icon => {
                    const showIcon = icon.getAttribute('data-password-icon') === 'show';
                    icon.classList.toggle('hidden', !isPassword && showIcon);
                    icon.classList.toggle('hidden', isPassword && !showIcon);
                });
            });
        });
    });
</script>

