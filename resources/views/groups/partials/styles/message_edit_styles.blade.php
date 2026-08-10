    <style>
    .edit-modal.hidden {
        display: none;
    }

    .edit-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
    }

    .edit-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .35);
    }

    .edit-modal__panel {
        direction: rtl;
        position: relative;
        margin: 5vh auto 0;
        max-width: 640px;
        width: clamp(320px, 90vw, 640px);
        max-height: 90vh;
        background: #fff;
        border-radius: 12px;
        padding: 1rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
        display: flex;
        flex-direction: column;
    }

    .edit-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .edit-modal__body {
        margin-top: .5rem;
        flex: 1;
        overflow-y: auto;
    }

    .edit-textarea {
        width: 100%;
        min-height: 120px;
        padding: .75rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font: inherit;
        line-height: 1.5;
        resize: vertical;
    }

    .edit-modal__footer {
        display: flex;
        justify-content: flex-end;
        gap: .5rem;
        margin-top: .75rem;
    }

    .btn {
        padding: .5rem .9rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        cursor: pointer;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .edit-close {
        background: transparent;
        border: none;
        font-size: 1.25rem;
        cursor: pointer;
        line-height: 1;
    }

    @media (max-width: 480px) {
        .edit-modal__panel {
            margin: 2vh auto 0;
            width: 94vw;
            padding: .75rem;
        }
    }
    </style>
