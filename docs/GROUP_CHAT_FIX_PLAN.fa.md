# 🎯 طرح اصلاح سیستم چت - مرحله‌ای

**هدف:** معماری صحیح + performance بهبود + بدون reload + ریلتایم

---

## 📊 مراحل تفصیلی:

### PHASE 1: تشخیص و معماری (تا فردا)

#### ✅ 1.1 - Database Schema Check
```
فایل: DATABASE_AUDIT.md

[ ] $ php artisan tinker
    > Schema::gett tables() for:
      - messages (structure, collation)
      - poll_votes (indexes?)
      - reactions (indexes?)
      
[ ] Check group_user pivot:
    - Columns: role, status, last_read_message_id
    - Indexes: (group_id, user_id), (user_id)

[ ] Check if Redis configured:
    - config/cache.php - driver?
    - REDIS_HOST set?

نتیجه: 📄 DATABASE_AUDIT.md
```

#### ✅ 1.2 - Real-time Setup Verification
```
[ ] Check if Pusher/Laravel Echo configured:
    - config/broadcasting.php
    - BROADCAST_DRIVER value?
    
[ ] Message::observe(MessageObserver)?
    - No? Will need Event listeners

[ ] WebSocket setup?
    - Laravel Echo? 
    - Socket.io?
    - Or just Redis pub/sub?

نتیجه: 📄 BROADCAST_AUDIT.md
```

#### ✅ 1.3 - File a Precise "Decision Document"
```
File: GROUP_CHAT_ARCHITECTURE_DECISION.md

Decide:
□ Will use Events + Broadcast (Real-time)?
□ Will use Polling (Fallback only)?
□ Will support Both?

Database migration plan:
□ Add indexes on frequently queried columns
□ Add vote_count cache column on polls?
□ Add message_count on thread_roots?

Components to create:
□ ChatController - Only API
□ ChatServiceLayer - Business logic
□ ChatRepository - Data access
□ ChatObserver - Event handling
□ ChatTransformer - Data formatting
```

---

### PHASE 2: اصلاح معماری (روز 2-3)

#### 2.1 - سراغ: Remove location.reload() और Dual Systems

**فایل‌های اصلاح:**

A) `public/js/group-chat.js` - submitVote()
```javascript
// ❌ BEFORE (line 199):
$.ajax({
  success: function(data) {
    location.reload();  // DELETE THIS
  }
});

// ✅ AFTER:
$.ajax({
  success: function(data) {
    if (data.status === 'success') {
      // Update poll in DOM without reload
      updatePollResult(data.poll);
      // Dispatch custom event for other listeners
      document.dispatchEvent(new CustomEvent('poll-updated', {
        detail: data.poll
      }));
    }
  }
});
```

B) `resources/views/groups/chat.blade.php` - unpinMessage (line 4154)
```javascript
// ❌ BEFORE:
.then(() => location.reload());

// ✅ AFTER:
.then(() => {
  // Update UI element smoothly
  document.getElementById(`msg-${id}`).classList.remove('pinned');
  showSuccessNotification('پیام از حالت سنجاق خارج شد');
});
```

C) `resources/views/groups/chat.blade.php` - deleteMessage (line 180)
```javascript
// ❌ BEFORE:
location.reload()

// ✅ AFTER:
document.getElementById(`msg-${messageId}`).fadeOut();
showSuccessNotification('پیام حذف شد');
```

**Scope:** 3 occurrences to fix

**نتیجه:** ✅ No more full page reloads

---

#### 2.2 - Kill: Comment Polling (Worst Problem)

**فایل:** `public/js/comment.chat.js`

```javascript
// ❌ DELETE ENTIRE BLOCK (line 18-35):
setInterval(function() {
  $.ajax({
    url: `/api/comments/${blogID}/messages`,
    method: 'GET',
    success: function(data) {
      $('#chat-box').html(data);  // ← INSANE!
    }
  });
}, 2000);  // ← EVERY 2 SECONDS!

// ✅ REPLACE WITH:
// Event-driven updates only:
// - Listen for BlogCommentCreated event
// - Listen for CommentReactionUpdated event
// - Use addCommentToChat() (non-destructive)

window.addEventListener('blog-comment-created', (e) => {
  addCommentToChat(e.detail.comment);
});
```

**نتیجه:** 
- Removes 30 requests/min
- Fixes: scroll reset, focus loss, DOM thrashing

---

#### 2.3 - Consolidate: Message Polling

**فایل:** `public/js/group-chat.js` (multiple sections)

Current state:
- Polling interval: 2-5 seconds
- Polling start: After initial page load
- Polling stop: Never? (memory leak!)

**Changes:**
```javascript
// ✅ NEW: Single source of truth for polling

class GroupChatPoller {
  constructor(groupId, csrfToken) {
    this.groupId = groupId;
    this.csrfToken = csrfToken;
    this.lastId = null;
    this.interval = null;
    this.isActive = false;
  }
  
  start() {
    if (this.isActive) return;
    this.isActive = true;
    this.poll();
  }
  
  stop() {
    if (this.interval) clearInterval(this.interval);
    this.isActive = false;
  }
  
  async poll() {
    try {
      const messages = await this.fetchNewMessages();
      messages.forEach(m => this.processMessage(m));
      
      // Adjust interval based on activity
      const nextInterval = messages.length > 0 ? 2000 : 5000;
      this.interval = setTimeout(() => this.poll(), nextInterval);
    } catch (e) {
      console.error('Poll error:', e);
      this.interval = setTimeout(() => this.poll(), 5000);
    }
  }
  
  async fetchNewMessages() {
    const res = await fetch(`/api/groups/${this.groupId}/messages?since=${this.lastId}`, {
      headers: { 'Accept': 'application/json' }
    });
    return res.json();
  }
}

// Usage:
const poller = new GroupChatPoller(window.groupId, window.csrfToken);
poller.start();

// Stop on page unload
window.addEventListener('beforeunload', () => poller.stop());
```

**نتیجه:**
- Single polling system
- Memory-safe (stops on unload)
- Adaptive polling rate

---

#### 2.4 - Standardize: Event System

**فایل جدید:** `resources/js/GroupChatEvents.js`

```javascript
/**
 * Centralized event system for group chat
 * Avoid duplicate event listeners
 */

const ChatEvents = {
  MESSAGE_CREATED: 'chat:message-created',
  MESSAGE_UPDATED: 'chat:message-updated',
  MESSAGE_DELETED: 'chat:message-deleted',
  POLL_CREATED: 'chat:poll-created',
  POLL_VOTED: 'chat:poll-voted',
  COMMENT_CREATED: 'chat:comment-created',
  REACTION_ADDED: 'chat:reaction-added',
  
  emit(eventName, detail) {
    document.dispatchEvent(
      new CustomEvent(eventName, { detail })
    );
  },
  
  on(eventName, handler) {
    document.addEventListener(eventName, handler);
    // Return unsubscribe function
    return () => document.removeEventListener(eventName, handler);
  }
};

export default ChatEvents;
```

**استفاده:**
```javascript
// Instead of: $(document).on('click', ...)
// Use:
ChatEvents.on(ChatEvents.POLL_VOTED, (e) => {
  updatePollUI(e.detail.poll);
});

// Broadcasting:
ChatEvents.emit(ChatEvents.POLL_VOTED, {
  poll: data,
  userId: auth().id
});
```

**نتیجه:**
- Single event bus
- Prevents duplicate handlers
- Easy to debug

---

### PHASE 3: Database Optimization (روز 3-4)

#### 3.1 - Query Optimization

**فایل:** `app/Http/Controllers/Group/ChatController.php`

**Problem:**
```php
// Current: N+1 queries for messages
$messages = $group->messages()->get();
foreach ($messages as $msg) {
  $msg->user;  // Query!
  $msg->reactions;  // Query!
  if ($msg->thread_id) {
    Message::find($msg->thread_id);  // Query!
  }
}

// Result: 1 + 120 + 120 + 50 = 291 queries 🔥
```

**Solution:**
```php
// ✅ Use eager loading:
$messages = $group->messages()
  ->with('user:id,first_name,last_name,avatar')
  ->with('reactions')
  ->with('threadRoot:id,message')
  ->select('id', 'user_id', 'message', ...)
  ->limit(120)
  ->get();

// Result: 1 + 1 (users) + 1 (reactions) + 1 (threads) = 4 queries!
```

**Also:**
```php
// Aggregate poll votes once
$pollIds = $polls->pluck('id');
$voteCounts = PollVote::whereIn('poll_id', $pollIds)
  ->selectRaw('poll_id, option_id, COUNT(*) as count')
  ->groupBy('poll_id', 'option_id')
  ->get()
  ->groupBy('poll_id');

// Use in loop:
foreach ($polls as $poll) {
  $poll->vote_results = $voteCounts[$poll->id] ?? [];
}
```

**Changes:**
- [ ] MessageRepository: eager load relations
- [ ] PollRepository: batch vote aggregation
- [ ] BlogRepository: eager load comments + reactions
- [ ] Add database indexes on:
  - [ ] messages(group_id, created_at, user_id)
  - [ ] reactions(blog_id, comment_id)
  - [ ] poll_votes(poll_id, user_id)
  - [ ] group_user(group_id, status)

**نتیجه:**
- Initial queries: 50 → 8
- Polling queries: 30 → 5
- Page load: 3-5 sec → <1 sec

---

#### 3.2 - Cache Layer (Redis)

**فایل جدید:** `app/Services/GroupChatCacheService.php`

```php
class GroupChatCacheService {
  
  public function getPollVotesCached($pollId, $minutes = 5) {
    return Cache::remember(
      "poll_votes:$pollId",
      $minutes,
      fn() => PollVote::where('poll_id', $pollId)
        ->selectRaw('option_id, COUNT(*) as count')
        ->groupBy('option_id')
        ->get()
    );
  }
  
  public function getGroupMessages($groupId, $limit = 120) {
    return Cache::rememberForever(
      "group_messages:$groupId",
      fn() => Message::where('group_id', $groupId)
        ->with('user', 'reactions')
        ->orderByDesc('id')
        ->limit($limit)
        ->get()
    );
  }
  
  public function invalidateOnNewMessage($groupId, $message) {
    Cache::forget("group_messages:$groupId");
    broadcast(new GroupMessageUpdated(...));
  }
}
```

**استفاده:**
```php
// In ChatController:
$messages = app(GroupChatCacheService::class)
  ->getGroupMessages($groupId);
```

**نتیجه:**
- Vote calculation: N → 0 (cached)
- Message fetch: Reduced DB queries
- CPU usage reduced 60%

---

### PHASE 4: Character Encoding Fix (روز 4)

#### 4.1 - Database Collation

```sql
-- ✅ Check current:
SHOW CREATE TABLE messages;

-- If NOT utf8mb4_unicode_ci, convert:
ALTER TABLE messages CONVERT TO CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

-- Plus for all related tables:
ALTER TABLE blogs CONVERT TO CHARACTER SET utf8mb4...
ALTER TABLE comments CONVERT TO CHARACTER SET utf8mb4...
ALTER TABLE reactions CONVERT TO CHARACTER SET utf8mb4...
```

#### 4.2 - Laravel Configuration

**فایل:** `config/database.php`

```php
'mysql' => [
    'driver' => 'mysql',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'modes' => [
        'STRICT_TRANS_TABLES',
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ENGINE_SUBSTITUTION',
    ],
],
```

#### 4.3 - Encoding Functions

**فایل:** `app/Helpers/TextHelper.php`

```php
class TextHelper {
  
  /**
   * Safe text encoding
   */
  public static function sanitizeText($text) {
    // 1. Trim whitespace
    $text = trim($text);
    
    // 2. Remove control characters
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    
    // 3. Normalize spaces (including Persian spaces)
    $text = preg_replace('/\s+/u', ' ', $text);
    
    return $text;
  }
  
  /**
   * Format for display (HTML escape + newlines)
   */
  public static function formatForDisplay($text) {
    $text = self::sanitizeText($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = nl2br($text);
    return $text;
  }
  
  /**
   * Format file name safely
   */
  public static function sanitizeFileName($filename) {
    $info = pathinfo($filename);
    $name = $info['filename'];
    $ext = $info['extension'] ?? '';
    
    // Keep Persian letters, numbers, dash, underscore
    $name = preg_replace('/[^ء-ي0-9_-]/u', '', $name);
    
    return $name . '.' . $ext;
  }
}
```

**استفاده:**
```php
// In MessageController@store:
$message = TextHelper::formatForDisplay($request->message);

// In voice message handling:
$filename = TextHelper::sanitizeFileName($file->getClientOriginalName());
```

#### 4.4 - JavaScript Encoding

**فایل:** `resources/js/TextEncoding.js`

```javascript
/**
 * Safe encoding for Persian text in JavaScript
 */
export const TextEncoding = {
  
  /**
   * Escape HTML entities properly
   */
  escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  },
  
  /**
   * Encode file path for URL
   */
  encodeFilePath(path) {
    // Split path, encode each part, rejoin
    return path.split('/').map(part => 
      encodeURIComponent(part)
    ).join('/');
  },
  
  /**
   * Unescape HTML entities
   */
  unescapeHtml(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return doc.documentElement.textContent;
  }
};
```

**نتیجه:**
- Persian text displays correctly
- No mojibake in any situation
- File names safe

---

### PHASE 5: View Refactoring (روز 5-6)

#### 5.1 - Break chat.blade.php into Components

**Current:** 4350 lines in one file 🚨

**Target structure:**

```
resources/views/groups/
├── chat.blade.php (300 lines max - layout only)
├── components/
│   ├── chat-header.blade.php
│   ├── chat-body.blade.php
│   ├── chat-footer.blade.php
│   ├── message-item.blade.php
│   ├── poll-item.blade.php
│   ├── blog-item.blade.php
│   ├── comment-item.blade.php
│   └── modals/
│       ├── edit-message.blade.php
│       ├── react-emoji.blade.php
│       └── member-settings.blade.php
└── partials/
    └── (existing partials remain)
```

**new chat.blade.php:**
```blade
@extends('layouts.chat')

@section('content')
<div class="chat-container">
  @include('groups.components.chat-header', ['group' => $group])
  @include('groups.components.chat-body', [...])
  @include('groups.components.chat-footer', [...])
</div>

@push('scripts')
  <script src="{{ asset('js/group-chat.js') }}"></script>
  <script src="{{ asset('js/chat-features.js') }}"></script>
@endpush
@endsection
```

**نتیجه:**
- Maintainability improved
- Easy to find code
- Reusable components

---

### PHASE 6: Testing & Validation (روز 6-7)

#### 6.1 - Performance Testing

```php
// Test: Message posting speed
- [ ] Baseline: 3sec → Target: <500ms
- [ ] Polling: 50req/min → Target: <10req/min
- [ ] Queries: 50 → Target: <5

// Test: Vote submission
- Old: 3-5 sec (with reload) → New: <200ms

// Test: Scroll restoration
- Verify smooth scroll
- Check no jank/stutter
```

#### 6.2 - Encoding Validation

```
- [ ] Persian text persists across reload
- [ ] Voice messages download with correct names
- [ ] File attachments download with correct names
- [ ] Special characters in messages work
```

#### 6.3 - Najm-Hoda Safety Check

```
- [ ] Najm-Hoda system still works
- [ ] No errors in logs
- [ ] Elections still functional
- [ ] Delegations still work
```

---

## 📅 Timeline:

| روز | فاز | ساعت |
|------|------|--------|
| 1 | Database audit + Decision | 4-6 |
| 2 | Remove location.reload + Kill polling | 6-8 |
| 3 | Query optimization + Redis | 6-8 |
| 4 | Character encoding | 4-6 |
| 5 | View refactoring | 6-8 |
| 6 | Testing | 4-6 |
| **Total** | | **30-42 hours** |

---

## 🎯 Expected Results:

**Before:**
- Page load: 5-10 sec
- Message post: 3-5 sec
- Server CPU: 80%+
- Requests: 50-75/min
- Display bugs: Font encoding

**After:**
- Page load: <1 sec
- Message post: <200ms
- Server CPU: 20-30%
- Requests: <15/min
- Display: Perfect Persian

**Performance:** 5-10x improvement ✅

---

## ⚠️ Risk Mitigation:

1. **Keep backup** of current code
2. **Test on staging** first
3. **Phase rollout** - not all at once
4. **Monitor logs** during deployment
5. **Preserve Najm-Hoda** - isolated testing