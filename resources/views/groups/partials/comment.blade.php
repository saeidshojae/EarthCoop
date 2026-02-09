@php
  $isMine = $item->user_id == auth()->id();
  $sender = $item->user;
  $initials = $sender ? Str::upper(Str::substr($sender->first_name ?? '', 0, 1) . Str::substr($sender->last_name ?? '', 0, 1)) : '؟';
  $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'حساب حذف شده';
@endphp

<div class="comment-item {{ $isMine ? 'you' : 'other' }}"
     id="msg-{{ $item->id }}"
     data-update-url="{{ route('comments.update', $item->id) }}"
     data-delete-url="{{ route('comments.destroy', $item->id) }}"
     data-message-text="{{ trim(strip_tags($item->message)) }}"
     data-report-url="{{ route('messages.report', $item->id) }}">
  
  <!-- Avatar -->
  @if(!$isMine && $sender)
    <a href="{{ route('profile.member.show', $item->user_id) }}" class="comment-item__avatar-link">
      <div class="comment-item__avatar">
        @if($sender->avatar)
          <img src="{{ asset('images/users/avatars/' . $sender->avatar) }}" alt="{{ $senderName }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
        @else
          <span>{{ $initials }}</span>
        @endif
      </div>
    </a>
  @else
    <div class="comment-item__avatar" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
      <span>{{ $isMine ? 'شما' : ($initials ?: '؟') }}</span>
    </div>
    @endif

  <!-- Content -->
  <div class="comment-item__content">
    <!-- Header -->
    <div class="comment-item__header">
      <div class="comment-item__author">
        @if($isMine)
          <span>شما</span>
        @elseif($sender)
          <a href="{{ route('profile.member.show', $item->user_id) }}">{{ $senderName }}</a>
        @else
          <span style="color: #9ca3af;">حساب حذف شده</span>
        @endif
      </div>
      
      <div class="comment-item__menu">
        <button type="button" class="comment-item__menu-btn" onclick="openGlobalMenu(event, {{ $item->id }})" aria-label="گزینه‌ها">
          <i class="fas fa-ellipsis-v"></i>
        </button>
      </div>
    </div>

    <!-- Reply Preview -->
    @if($item->parent)
      <div class="comment-item__reply">
        <div class="comment-item__reply-author">
          @if($item->parent->user)
            {{ $item->parent->user->first_name }} {{ $item->parent->user->last_name }}
          @else
            حساب حذف شده
          @endif
        </div>
        <div class="comment-item__reply-text">
        @php
    $text = $item->parent->message;
    $textWithLinks = preg_replace_callback(
        '/(https?:\/\/[^\s]+)/',
        function($matches) {
                $url = e($matches[0]);
                return '<a href="'.$url.'" target="_blank" style="color: #3b82f6; text-decoration: underline;">'.$url.'</a>';
        },
        $text
    );
@endphp
          {!! Str::limit($textWithLinks, 100) !!}
        </div>
      </div>
    @endif

    <!-- Comment Text -->
    <div class="comment-item__text">
    @php
    $text = $item->message;
    $textWithLinks = preg_replace_callback(
        '/(https?:\/\/[^\s]+)/',
        function($matches) {
            $url = e($matches[0]);
            return '<a href="'.$url.'" target="_blank" style="color: #3b82f6; text-decoration: underline;">'.$url.'</a>';
        },
        $text
    );
@endphp
      {!! $textWithLinks !!}
    </div>

    <!-- Footer -->
    <div class="comment-item__footer">
      <span class="comment-item__time">
        <i class="far fa-clock"></i>
        {{ verta($item->created_at)->format('Y/m/d H:i') }}
      </span>
      
      <div class="comment-item__reactions">
        <button type="button" 
                class="comment-reaction-btn like-btn" 
                id="like-btn-{{ $item->id }}"
                onclick="reactToComment('like', {{ $item->id }})">
          <i class="fas fa-thumbs-up"></i>
          <span id="like-count-{{ $item->id }}">{{ $item->likes->count() }}</span>
        </button>
        <button type="button" 
                class="comment-reaction-btn dislike-btn" 
                id="dislike-btn-{{ $item->id }}"
                onclick="reactToComment('dislike', {{ $item->id }})">
          <i class="fas fa-thumbs-down"></i>
          <span id="dislike-count-{{ $item->id }}">{{ $item->dislikes->count() }}</span>
</button>
  </div>
    </div>
  </div>
</div>