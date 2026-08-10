# Feed ترتیبی و unread مبتنی بر cursor — فاز ۳

## مدل داده

- `group_feed_sequences`: شمارندهٔ قفل‌شونده و افزایشی هر گروه.
- `group_feed_items`: نگاشت یکتای content به `group_id + sequence` با type، actor، version و occurred_at.
- `group_user.last_read_feed_sequence`: cursor افزایشی و اتمیک هر عضویت.

انواع فعلی feed عبارت‌اند از `message`, `file`, `voice`, `post`, `poll`, `comment`. محتوای خود کاربر هنگام محاسبه unread حذف می‌شود و query فقط روی cursor و indexهای feed اجرا می‌شود.

## ترتیب rollout

1. migrationهای فازهای ۱ تا ۳ پس از backup اجرا شوند.
2. flagهای `feed_sequence_v1` و `feed_unread_v1` در ابتدا خاموش بمانند.
3. فرمان `php artisan group-chat:backfill-feed --dry-run` بررسی شود.
4. backfill واقعی با `php artisan group-chat:backfill-feed --initialize-cursors` اجرا شود.
5. تطابق تعداد legacy و feed برای گروه‌های آزمایشی بررسی شود.
6. ابتدا `GROUP_CHAT_FEATURE_FEED_SEQUENCE_V1=true` برای dual-write و سپس `GROUP_CHAT_FEATURE_FEED_UNREAD_V1=true` فعال شود.

برای rollout محدود می‌توان `--group=<id>` را استفاده کرد. اجرای مجدد backfill امن است؛ constraint یکتای `type + content_id` مانع duplicate می‌شود.

## خوانده‌شدن

- endpoint اتمیک `POST /api/groups/{group}/mark-all-read` cursor را فقط رو به جلو حرکت می‌دهد.
- پارامتر اختیاری `through_sequence` اجازه می‌دهد فقط تا آیتم واقعاً دیده‌شده علامت‌گذاری شود.
- mark-read پیام legacy هم‌زمان cursor جدید را در دورهٔ سازگاری جلو می‌برد.
- پاسخ unread شامل `first_unread_sequence` و شمارنده‌های فرعی `mentions` و `replies` است تا divider و فیلترهای UI بدون اسکن JSON ساخته شوند.
- JSONهای `read_by` فعلاً برای رسید جزئی و rollback حفظ شده‌اند و پس از دورهٔ dual-read حذف می‌شوند.

## Rollback

خاموش‌کردن `feed_unread_v1` محاسبه را فوراً به JSON legacy بازمی‌گرداند. خاموش‌کردن `feed_sequence_v1` dual-write را متوقف می‌کند، بدون اینکه داده‌های feed یا cursor حذف شوند.
