<style>
    .auth-recovery-wrapper {
        position: relative;
        min-height: calc(100vh - 9rem);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 1rem 4rem;
        overflow: hidden;
    }

    .auth-recovery-wrapper::before,
    .auth-recovery-wrapper::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        filter: blur(90px);
        opacity: 0.12;
        pointer-events: none;
        transform: translate(-50%, -50%);
    }

    .auth-recovery-wrapper::before {
        top: 18%;
        left: 18%;
        width: 28rem;
        height: 28rem;
        background: rgba(16, 185, 129, 0.45);
    }

    .auth-recovery-wrapper::after {
        bottom: -6%;
        right: -8%;
        width: 32rem;
        height: 32rem;
        background: rgba(37, 99, 235, 0.35);
    }

    .auth-recovery-card {
        background: rgba(255, 255, 255, 0.97);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(226, 232, 240, 0.6);
        box-shadow: 0 32px 80px rgba(15, 23, 42, 0.18);
        border-radius: 1.75rem;
    }

    .dark .auth-recovery-card {
        background: rgba(15, 23, 42, 0.92);
        border-color: rgba(148, 163, 184, 0.35);
        box-shadow: 0 18px 70px rgba(148, 163, 184, 0.12);
    }

    .auth-recovery-badge {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.14), rgba(59, 130, 246, 0.14));
        border: 1px solid rgba(16, 185, 129, 0.35);
        color: #047857;
    }

    .dark .auth-recovery-badge {
        border-color: rgba(16, 185, 129, 0.45);
        color: rgba(32, 210, 150, 0.95);
    }

    .auth-recovery-input:focus {
        border-color: rgba(16, 185, 129, 0.7);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
    }

    .dark .auth-recovery-input:focus {
        border-color: rgba(45, 212, 191, 0.9);
        box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.22);
    }

    .auth-recovery-glow {
        position: absolute;
        inset: -25% auto auto -15%;
        width: 22rem;
        height: 22rem;
        background: radial-gradient(circle at center, rgba(45, 212, 191, 0.35), transparent 60%);
        filter: blur(40px);
        pointer-events: none;
    }

    .dark .auth-recovery-glow {
        background: radial-gradient(circle at center, rgba(56, 189, 248, 0.35), transparent 65%);
    }
</style>

