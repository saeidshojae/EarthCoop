# Realtime، outbox و fallback واحد — فاز ۴

## قرارداد event

event واحد `.group.realtime.event` روی channel خصوصی `group.{id}` منتشر می‌شود:

```json
{
  "version": 1,
  "event_id": "uuid",
  "group_id": 10,
  "sequence": 42,
  "type": "feed.message.created",
  "actor_id": 7,
  "occurred_at": "2026-08-05T12:00:00Z",
  "payload": {}
}
```

write اصلی و outbox در یک transaction ثبت می‌شوند. هیچ خطای broadcaster نتیجهٔ HTTP را تغییر نمی‌دهد. فرمان `group-chat:dispatch-outbox` رکوردهای pending را به queue `group-chat-realtime` می‌فرستد و job با backoff و حداکثر پنج تلاش منتشر می‌کند.

## Delta و بازیابی gap

`GET /api/groups/{group}/feed/delta?after_sequence=N&limit=100` snapshotهای canonical را به ترتیب sequence بازمی‌گرداند. کلاینت:

- `event_id` را deduplicate می‌کند؛
- آخرین sequence را برای هر گروه نگه می‌دارد؛
- هنگام gap یا reconnect ابتدا delta sync می‌کند؛
- پس از سلامت WebSocket، polling legacy را غیرفعال نگه می‌دارد؛
- هنگام offline یا hidden بودن tab polling نمی‌کند؛
- retry delta را با backoff تصاعدی، jitter و سقف ۳۰ ثانیه انجام می‌دهد.

وضعیت `آنلاین/در حال اتصال/آفلاین` با `aria-live` در UI نمایش داده می‌شود.

## Rollout

1. migration outbox اجرا و queue worker برای queue جدید آماده شود.
2. فاز ۳ و dual-write feed فعال و سالم باشد.
3. `GROUP_CHAT_FEATURE_TRANSACTIONAL_OUTBOX_V1=true` فعال شود و backlog پایش شود.
4. `GROUP_CHAT_FEATURE_DELTA_SYNC_V1=true` برای گروه آزمایشی فعال شود.
5. `GROUP_CHAT_FEATURE_REALTIME_ENVELOPE_V1=true` و scheduler/worker فعال شوند.
6. پس از تأیید gap/reconnect، eventهای legacy به‌تدریج خاموش شوند.

rollback با خاموش‌کردن سه flag بالا انجام می‌شود؛ polling و eventهای legacy دست‌نخورده باقی مانده‌اند.

## عملیات

- worker پیشنهادی: `php artisan queue:work --queue=group-chat-realtime --tries=5`
- dispatch دستی: `php artisan group-chat:dispatch-outbox --limit=500`
- سنجه‌های ضروری: pending backlog، oldest pending age، attempts، publish failures، delta gap count و fallback activation.
