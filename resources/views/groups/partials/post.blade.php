@php

    // استفاده از map preloaded در controller به جای کوئری مستقیم
    $groupUserPost = isset($postGroupUsersMap) ? ($postGroupUsersMap[$item->user_id] ?? null) : null;
    // اگر postGroupUsersMap موجود نبود (fallback)
    if (!$groupUserPost && isset($item->group_id)) {
        $groupUserPost = \App\Models\GroupUser::where('group_id', $item->group_id)

        ->where('user_id', $item->user_id)

        ->first();
    }

    $ownerId = $item->user_id ?? optional($item->user)->id ?? 0;

    $hue = fmod($ownerId * 137.508, 360);

    $saturation = 70;

    $lightness = 85;

    $backgroundColor = "hsl({$hue}, {$saturation}%, {$lightness}%)";

    $textColor = "hsl({$hue}, {$saturation}%, 28%)";

    $fn = trim($item->user->first_name ?? '');

    $ln = trim($item->user->last_name ?? '');

    $inits = (mb_substr($fn, 0, 1) ?: '؟') . ' ' . (mb_substr($ln, 0, 1) ?: '؟');

    $isOwner = ($item->user_id ?? null) == auth()->id();

    $roleLabel = match(optional($groupUserPost)->role) {

        3 => 'مدیر گروه',

        2 => 'بازرس گروه',

        1 => 'عضو فعال',

        0 => 'ناظر',

        default => null,

    };

    $authorName = $item->user ? ($item->user->first_name . ' ' . $item->user->last_name) : 'حساب حذف شده';

    $profileUrl = $item->user ? route('profile.member.show', $item->user) : null;

@endphp

<div class="post-wrapper {{ $isOwner ? 'post-wrapper--self' : '' }}">

    <div class="post-card {{ optional($groupUserPost)->role === 3 ? 'post-card--manager' : '' }}" id="blog-{{ $item->id }}"
        data-feed-item="true" data-feed-type="post" data-feed-id="{{ $item->id }}"
        data-feed-author-id="{{ $ownerId }}" data-feed-unread="{{ !$isOwner && !$item->isReadBy((int) auth()->id()) ? '1' : '0' }}">

        <div class="post-card__header">

            <div class="post-card__author">

                @if($item->user && $item->user->avatar)

                    <a class="post-card__avatar" href="{{ $profileUrl }}">

                        <img src="{{ asset('/images/users/avatars/' . $item->user->avatar) }}" alt="{{ $authorName }}">

                    </a>

                @else

                    <a class="post-card__avatar" href="{{ $profileUrl ?? 'javascript:void(0)' }}" style="background-color: {{ $backgroundColor }}; color: {{ $textColor }};">

                        <span>{{ $inits }}</span>

                    </a>

                @endif

                <div class="post-card__author-info">

                    @if($profileUrl)

                        <a href="{{ $profileUrl }}" class="post-card__name">{{ $authorName }}</a>

                    @else

                        <span class="post-card__name">{{ $authorName }}</span>

                    @endif

                    @if($roleLabel)

                        <span class="post-card__role">{{ $roleLabel }}</span>

                    @endif

                </div>

            </div>

            <div class="action-menu" data-action-menu>

                <button type="button" class="action-menu__toggle" aria-expanded="false">

                    <i class="fas fa-ellipsis-v"></i>

                </button>

                <div class="action-menu__list">

                    <button type="button"

                            class="action-menu__item"

                            data-chat-page-action="reply-content" data-reply-target="post-{{ $item->id }}" data-reply-text="مقاله: {{ $item->title }}">

                        <i class="fas fa-reply"></i>

                        پاسخ

                    </button>

                    @if($isOwner)

                        <button type="button"

                                class="action-menu__item"

                                data-bs-toggle="modal"

                                data-bs-target="#editPostModal-{{ $item->id }}">

                            <i class="fas fa-edit"></i>

                            ویرایش

                        </button>

                        <button type="button"

                                class="action-menu__item action-menu__item--danger"

                                data-chat-page-action="delete-post" data-post-id="{{ $item->id }}">

                            <i class="fas fa-trash"></i>

                            حذف

                        </button>

                    @else

                        <button type="button"

                                class="action-menu__item action-menu__item--danger"

                                data-chat-page-action="report-message" data-message-id="{{ $item->id }}">

                            <i class="fas fa-flag"></i>

                            گزارش

                        </button>

                    @endif

                </div>

            </div>

        </div>

        @if($item->title)

            <h3 class="post-card__title">{{ $item->title }}</h3>

        @endif

        <div class="post-card__meta">

            <span class="post-card__timestamp">

                {{ verta($item->created_at)->format('Y/m/d H:i') }}

            </span>

            <span class="post-card__category">

                <i class="fas fa-folder-open"></i>

                @if($item->category)

                    <a href="javascript:void(0)"

                       class="open-category-blogs"

                       data-url="{{ url('/categories/'.$item->category->id.'/blogs') }}"

                       data-group-id="{{ $item->group_id }}">

                        {{ $item->category->name }}

                    </a>

                @else

                    بدون دسته‌بندی

                @endif

            </span>

        </div>

        @if(!empty($item->img))

            @php

                $type = $item->file_type ? explode('/', $item->file_type)[0] : '';

            @endphp

            <div class="post-card__media">

                @if($type === 'image')

                    <img src="{{ $item->media_url }}" alt="{{ $item->title }}">

                @elseif($type === 'video')

                    <video controls>

                        <source src="{{ $item->media_url }}" type="{{ $item->file_type }}">

                    </video>

                @elseif($type === 'audio')

                    <audio controls>

                        <source src="{{ $item->media_url }}" type="{{ $item->file_type }}">

                    </audio>

                @else

                    <a href="{{ $item->media_url }}"

                       class="post-card__comments"

                       target="_blank">

                        <i class="fas fa-file-arrow-down"></i>

                        دانلود {{ $item->file_type ? (explode('/', $item->file_type)[1] ?? 'فایل') : 'فایل' }}

                    </a>

                @endif

            </div>

        @endif

        <div class="post-card__content">

            {!! $item->content !!}

        </div>

        <a href="{{ route('groups.comment', $item) }}" class="post-card__comments post-card__comments--cta">
            <i class="fas fa-comment-dots"></i>
            نظر دهید ({{ $item->comments_count ?? 0 }})
        </a>

        <div class="post-card__footer content-meta-line">

            @php
                $readBy = null;
                if ($isOwner && isset($item->read_by)) {
                    if (is_string($item->read_by)) {
                        $readBy = json_decode($item->read_by, true);
                    } else {
                        $readBy = $item->read_by;
                    }
                }
                $readCount = is_array($readBy) ? count($readBy) : 0;
            @endphp

            <span class="content-meta-time">{{ verta($item->created_at)->format('H:i') }}</span>
            @if($item->edited_at)
            <span class="content-edit-status" title="ویرایش شده در {{ verta($item->edited_at)->format('Y/m/d H:i:s') }}">(ویرایش شده)</span>
            @endif

            <div class="reaction-buttons post-card__stats" data-post-id="{{ $item->id }}">
                @php
                    $currentUserReaction = $item->reactions->where('user_id', auth()->id())->first();
                    $userLiked    = $currentUserReaction && $currentUserReaction->type == '1';
                    $userDisliked = $currentUserReaction && $currentUserReaction->type == '0';
                @endphp
                <button type="button" class="btn-like{{ $userLiked ? ' active' : '' }}">

                    <i class="fas fa-thumbs-up"></i>

                    <span class="like-count">{{ $item->reactions->where('type','1')->count() }}</span>

                </button>

                <button type="button" class="btn-dislike{{ $userDisliked ? ' active' : '' }}">

                    <i class="fas fa-thumbs-down"></i>

                    <span class="dislike-count">{{ $item->reactions->where('type','0')->count() }}</span>

                </button>

            </div>

            @if($isOwner)
            <div class="post-read-receipt content-read-receipt" style="font-size: 11px; color: #6b7280;">
                @if($readCount > 0)
                <span style="color: #10b981;"><i class="fas fa-check-double"></i> {{ $readCount }} نفر دیده‌اند</span>
                @else
                <span style="color: #9ca3af;"><i class="fas fa-check"></i> ارسال شده</span>
                @endif
            </div>
            @endif

        </div>

        @if($isOwner)

            <div class="modal fade" id="editPostModal-{{ $item->id }}" tabindex="-1" aria-labelledby="editPostModalLabel-{{ $item->id }}" aria-hidden="true">

                <div class="modal-dialog modal-lg">

                    <div class="modal-content">

                        <form data-post-edit-form data-post-id="{{ $item->id }}">

                            <div class="modal-header post-edit-modal__header">

                                <h5 class="modal-title" id="editPostModalLabel-{{ $item->id }}">ویرایش پست</h5>

                                <button type="button" class="btn-close post-edit-modal__close" data-bs-dismiss="modal" aria-label="بستن" title="بستن">×</button>

                            </div>

                            <div class="modal-body">

                                <div class="mb-3">

                                    <label class="form-label">عنوان پست</label>

                                    <input type="text" class="form-control" id="edit-post-title-{{ $item->id }}" data-post-edit-title value="{{ $item->title }}">

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">متن پست</label>

                                    @php
                                        $editablePostContent = html_entity_decode((string) $item->content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        $editablePostContent = preg_replace('/<br\s*\/?>/iu', "\n", $editablePostContent);
                                        $editablePostContent = preg_replace('/<\/p\s*>/iu', "\n", $editablePostContent);
                                        $editablePostContent = trim(strip_tags($editablePostContent));
                                    @endphp
                                    <textarea class="form-control" rows="4" id="edit-post-content-{{ $item->id }}" data-post-edit-content>{{ $editablePostContent }}</textarea>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">دسته‌بندی</label>

                                    <select class="form-control" id="edit-post-category-{{ $item->id }}" data-post-edit-category>

                                        <option value="">انتخاب دسته‌بندی</option>

                                        @foreach ($categories as $category)

                                            <option value="{{ $category->id }}" @selected($item->category_id == $category->id)>{{ $category->name }}</option>

                                        @endforeach

                                    </select>

                                </div>

                            </div>

                            <div class="modal-footer">

                                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>

                                <button type="button" class="btn btn-secondary post-edit-modal__dismiss" data-bs-dismiss="modal">بستن</button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        @endif

    </div>

</div>

