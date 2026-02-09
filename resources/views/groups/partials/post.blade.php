@php
    $groupUserPost = \App\Models\GroupUser::where('group_id', $item->group_id)
        ->where('user_id', $item->user_id)
        ->first();

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
    <div class="post-card {{ optional($groupUserPost)->role === 3 ? 'post-card--manager' : '' }}" id="blog-{{ $item->id }}">
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
                            onclick="replyToMessage('post-{{ $item->id }}', '', 'مقاله: {{ $item->title }}')">
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
                                onclick="deletePost({{ $item->id }})">
                            <i class="fas fa-trash"></i>
                            حذف
                        </button>
                    @else
                        <button type="button"
                                class="action-menu__item action-menu__item--danger"
                                onclick="reportMessage({{ $item->id }})">
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
                    <img src="{{ asset('images/blogs/' . $item->img) }}" alt="{{ $item->title }}">
                @elseif($type === 'video')
                    <video controls>
                        <source src="{{ asset('images/blogs/' . $item->img) }}" type="{{ $item->file_type }}">
                    </video>
                @elseif($type === 'audio')
                    <audio controls>
                        <source src="{{ asset('images/blogs/' . $item->img) }}" type="{{ $item->file_type }}">
                    </audio>
                @else
                    <a href="{{ asset('images/blogs/' . $item->img) }}"
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

        <div class="post-card__footer">
            <div class="reaction-buttons post-card__stats" data-post-id="{{ $item->id }}">
                <button type="button" class="btn-like">
                    <i class="fas fa-thumbs-up"></i>
                    <span class="like-count">{{ $item->reactions()->where('type','1')->count() }}</span>
                </button>
                <button type="button" class="btn-dislike">
                    <i class="fas fa-thumbs-down"></i>
                    <span class="dislike-count">{{ $item->reactions()->where('type','0')->count() }}</span>
                </button>
            </div>

            <a href="{{ route('groups.comment', $item) }}" class="post-card__comments">
                <i class="fas fa-comment-dots"></i>
                نظر دهید ({{ $item->comments->count() }})
            </a>
        </div>

        @if($isOwner)
            <div class="modal fade" id="editPostModal-{{ $item->id }}" tabindex="-1" aria-labelledby="editPostModalLabel-{{ $item->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form onsubmit="submitPostEdit(event, {{ $item->id }})">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editPostModalLabel-{{ $item->id }}">ویرایش پست</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">عنوان پست</label>
                                    <input type="text" class="form-control" id="edit-post-title-{{ $item->id }}" value="{{ $item->title }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">متن پست</label>
                                    <textarea class="form-control" rows="4" id="edit-post-content-{{ $item->id }}">{{ $item->content }}</textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">دسته‌بندی</label>
                                    <select class="form-control" id="edit-post-category-{{ $item->id }}">
                                        <option value="">انتخاب دسته‌بندی</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected($item->category_id == $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
