@php
    $currentUserId = (int) auth()->id();
    $targetUserId = (int) $user->id;
    $requestState = $existingRequest?->status;
    $isIncomingPending = $existingRequest
        && $requestState === 'pending'
        && (int) $existingRequest->receiver_id === $currentUserId;
    $isOutgoingPending = $existingRequest
        && $requestState === 'pending'
        && (int) $existingRequest->sender_id === $currentUserId;
    $hasConversation = $existingRequest
        && $requestState === 'accepted'
        && $existingRequest->private_conversation_id;
    $sheetId = 'private-request-sheet-' . $targetUserId;
@endphp

@once
    @push('styles')
    <style>
        .pm-profile-request {
            direction: rtl;
            width: 100%;
            margin: .8rem 0;
        }

        .pm-profile-request__button,
        .pm-profile-request__link {
            display: inline-flex;
            width: 100%;
            min-height: 48px;
            padding: .65rem 1rem;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            border: 1px solid transparent;
            border-radius: 15px;
            font-family: inherit;
            font-size: .9rem;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .pm-profile-request__button--primary,
        .pm-profile-request__link--primary {
            background: #237a56;
            color: #fff;
        }

        .pm-profile-request__button--quiet,
        .pm-profile-request__link--quiet {
            border-color: #dfe7e2;
            background: #f6f9f7;
            color: #456055;
        }

        .pm-profile-request__button[disabled] {
            cursor: default;
            opacity: .78;
        }

        .pm-profile-request__hint {
            margin: .45rem .15rem 0;
            color: #7c8982;
            font-size: .74rem;
            line-height: 1.7;
        }

        body.pm-private-request-open {
            overflow: hidden !important;
            overscroll-behavior: none;
        }

        .pm-request-sheet-overlay {
            position: fixed;
            inset: 0;
            z-index: 20000;
            display: none;
            padding: 0;
            align-items: flex-end;
            justify-content: center;
            overflow: hidden;
            background: rgba(16, 29, 23, .52);
            transform: none !important;
        }

        .pm-request-sheet-overlay.is-open {
            display: flex;
        }

        .pm-request-sheet {
            width: 100%;
            max-height: min(88dvh, 44rem);
            padding: 1rem 1rem calc(1rem + env(safe-area-inset-bottom));
            overflow-y: auto;
            overscroll-behavior: contain;
            border-radius: 24px 24px 0 0;
            background: #fff;
            box-shadow: 0 -16px 42px rgba(18, 33, 26, .18);
            transform: none !important;
        }

        .pm-request-sheet__handle {
            width: 42px;
            height: 4px;
            margin: 0 auto .9rem;
            border-radius: 999px;
            background: #d5ddd9;
        }

        .pm-request-sheet__header {
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr) 40px;
            gap: .7rem;
            align-items: center;
        }

        .pm-request-sheet__avatar {
            width: 48px;
            height: 48px;
            border: 1px solid #e0e7e3;
            border-radius: 50%;
            object-fit: cover;
            background: #edf2ef;
        }

        .pm-request-sheet__identity {
            min-width: 0;
        }

        .pm-request-sheet__title {
            margin: 0;
            overflow: hidden;
            color: #1e2a24;
            font-size: 1rem;
            font-weight: 850;
            line-height: 1.5;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pm-request-sheet__subtitle {
            margin: .08rem 0 0;
            color: #829087;
            font-size: .72rem;
        }

        .pm-request-sheet__close {
            display: inline-flex;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 12px;
            background: #f3f6f4;
            color: #5d6b64;
            cursor: pointer;
        }

        .pm-request-sheet__intro {
            margin: 1rem 0 .75rem;
            color: #5f6e66;
            font-size: .82rem;
            line-height: 1.9;
        }

        .pm-request-sheet__label {
            display: block;
            margin-bottom: .42rem;
            color: #34453c;
            font-size: .8rem;
            font-weight: 750;
        }

        .pm-request-sheet__textarea {
            display: block;
            width: 100%;
            min-height: 116px;
            padding: .75rem .8rem;
            border: 1px solid #dce5e0;
            border-radius: 15px;
            outline: 0;
            background: #f8faf9;
            color: #27342e;
            font: inherit;
            font-size: .86rem;
            line-height: 1.8;
            resize: vertical;
        }

        .pm-request-sheet__textarea:focus {
            border-color: #a8cdb9;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(35, 122, 86, .09);
        }

        .pm-request-sheet__actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: .55rem;
            margin-top: .8rem;
        }

        .pm-request-sheet__submit,
        .pm-request-sheet__cancel {
            min-height: 46px;
            padding: .6rem .85rem;
            border: 0;
            border-radius: 13px;
            font-family: inherit;
            font-size: .84rem;
            font-weight: 800;
            cursor: pointer;
        }

        .pm-request-sheet__submit {
            background: #237a56;
            color: #fff;
        }

        .pm-request-sheet__cancel {
            background: #f1f4f2;
            color: #59675f;
        }

        .pm-profile-request__button:focus-visible,
        .pm-profile-request__link:focus-visible,
        .pm-request-sheet__close:focus-visible,
        .pm-request-sheet__submit:focus-visible,
        .pm-request-sheet__cancel:focus-visible {
            outline: 3px solid rgba(35, 122, 86, .24);
            outline-offset: 2px;
        }

        @media (min-width: 769px) {
            .pm-profile-request {
                max-width: 22rem;
            }

            .pm-request-sheet-overlay {
                padding: max(1.5rem, env(safe-area-inset-top)) 1.5rem 1.5rem;
                align-items: center;
            }

            .pm-request-sheet {
                max-width: 31rem;
                max-height: calc(100dvh - 3rem);
                padding: 1.15rem;
                border-radius: 22px;
                box-shadow: 0 22px 60px rgba(18, 33, 26, .2);
            }

            .pm-request-sheet__handle {
                display: none;
            }
        }
    </style>
    @endpush
@endonce

<div class="pm-profile-request" data-private-request-action>
    @if($hasConversation)
        <a class="pm-profile-request__link pm-profile-request__link--primary"
           href="{{ route('private-chats.show', $existingRequest->private_conversation_id) }}">
            <i class="far fa-comments" aria-hidden="true"></i>
            <span>ادامه گفتگو</span>
        </a>
    @elseif($isIncomingPending)
        <a class="pm-profile-request__link pm-profile-request__link--primary"
           href="{{ route('chat-requests.index', ['section' => 'requests', 'box' => 'received', 'status' => 'pending']) }}">
            <i class="far fa-envelope-open" aria-hidden="true"></i>
            <span>مشاهده درخواست</span>
        </a>
        <p class="pm-profile-request__hint">این عضو برای شما درخواست گفتگو فرستاده است.</p>
    @elseif($isOutgoingPending)
        <button type="button" class="pm-profile-request__button pm-profile-request__button--quiet" disabled>
            <i class="far fa-clock" aria-hidden="true"></i>
            <span>درخواست ارسال شده</span>
        </button>
        <p class="pm-profile-request__hint">پس از پذیرش طرف مقابل، گفتگو در بخش گفتگوهای خصوصی ظاهر می‌شود.</p>
    @else
        <button type="button"
                class="pm-profile-request__button pm-profile-request__button--primary"
                data-open-private-request="{{ $sheetId }}">
            <i class="far fa-comment-dots" aria-hidden="true"></i>
            <span>{{ $requestState === 'rejected' ? 'درخواست مجدد گفتگو' : 'شروع گفتگو' }}</span>
        </button>
    @endif
</div>

@if(!$hasConversation && !$isIncomingPending && !$isOutgoingPending)
    <div class="pm-request-sheet-overlay"
         id="{{ $sheetId }}"
         data-private-request-sheet
         role="dialog"
         aria-modal="true"
         aria-labelledby="{{ $sheetId }}-title"
         aria-hidden="true">
        <div class="pm-request-sheet" tabindex="-1">
            <div class="pm-request-sheet__handle" aria-hidden="true"></div>
            <div class="pm-request-sheet__header">
                <img src="{{ $user->avatar ? asset('images/users/' . $user->avatar) : asset('images/default-avatar.png') }}"
                     alt=""
                     class="pm-request-sheet__avatar">
                <div class="pm-request-sheet__identity">
                    <h2 class="pm-request-sheet__title" id="{{ $sheetId }}-title">{{ $user->fullName() }}</h2>
                    <p class="pm-request-sheet__subtitle">درخواست گفتگوی خصوصی</p>
                </div>
                <button type="button" class="pm-request-sheet__close" data-close-private-request aria-label="بستن">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <p class="pm-request-sheet__intro">
                برای شروع گفتگو، یک پیام کوتاه درباره موضوع یا دلیل ارتباط بنویسید. گفتگو فقط پس از پذیرش این درخواست آغاز می‌شود.
            </p>

            <form action="{{ route('chat-requests.send', $user->id) }}" method="POST">
                @csrf
                <label class="pm-request-sheet__label" for="private-request-description-{{ $user->id }}">پیام معرفی</label>
                <textarea id="private-request-description-{{ $user->id }}"
                          class="pm-request-sheet__textarea"
                          name="description"
                          maxlength="5000"
                          required
                          placeholder="مثلاً موضوعی که می‌خواهید درباره آن گفتگو کنید…">{{ old('description') }}</textarea>

                <div class="pm-request-sheet__actions">
                    <button type="submit" class="pm-request-sheet__submit">
                        {{ $requestState === 'rejected' ? 'ارسال دوباره درخواست' : 'ارسال درخواست' }}
                    </button>
                    <button type="button" class="pm-request-sheet__cancel" data-close-private-request>انصراف</button>
                </div>
            </form>
        </div>
    </div>
@endif

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let activeSheet = null;
        let returnFocusTo = null;

        // The profile card uses hover transforms. A fixed-position dialog inside a
        // transformed ancestor is no longer viewport-fixed and can jitter as the
        // hover state changes. Portal every request sheet directly under <body>.
        document.querySelectorAll('[data-private-request-sheet]').forEach(function(sheet) {
            document.body.appendChild(sheet);
        });

        function closeSheet(sheet) {
            if (!sheet) return;
            sheet.classList.remove('is-open');
            sheet.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('pm-private-request-open');
            if (returnFocusTo && document.contains(returnFocusTo)) returnFocusTo.focus();
            activeSheet = null;
            returnFocusTo = null;
        }

        document.addEventListener('click', function(event) {
            const opener = event.target.closest('[data-open-private-request]');
            if (opener) {
                const sheet = document.getElementById(opener.dataset.openPrivateRequest);
                if (!sheet) return;
                returnFocusTo = opener;
                activeSheet = sheet;
                sheet.classList.add('is-open');
                sheet.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pm-private-request-open');
                requestAnimationFrame(() => sheet.querySelector('textarea')?.focus({ preventScroll: true }));
                return;
            }

            if (event.target.closest('[data-close-private-request]')) {
                closeSheet(event.target.closest('[data-private-request-sheet]'));
                return;
            }

            if (activeSheet && event.target === activeSheet) {
                closeSheet(activeSheet);
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && activeSheet) closeSheet(activeSheet);
        });
    });
    </script>
    @endpush
@endonce
