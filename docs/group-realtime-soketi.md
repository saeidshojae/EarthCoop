# Group Realtime With Soketi

This project currently runs on Laravel 9, so the short-term realtime path is a Pusher-compatible websocket server such as Soketi. The application is configured to use the Pusher protocol with local Soketi-compatible credentials.

## Required Runtime

Run the Laravel app, a queue/runtime process if production needs one, and one websocket server.

Local `.env` values expected by the app:

```env
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher
GROUP_CHAT_DEFER_BROADCASTS=true

REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=127.0.0.1
REVERB_PORT=6001
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

## Start Soketi

Docker example:

```bash
docker run --rm -p 6001:6001 \
  -e SOKETI_DEFAULT_APP_ID=local \
  -e SOKETI_DEFAULT_APP_KEY=local \
  -e SOKETI_DEFAULT_APP_SECRET=local \
  quay.io/soketi/soketi:latest-16-alpine
```

Node/npx example:

```bash
npx @soketi/soketi start \
  --host=0.0.0.0 \
  --port=6001 \
  --app-id=local \
  --app-key=local \
  --app-secret=local
```

## Rebuild And Clear Laravel Cache

After changing websocket env values:

```bash
php artisan optimize:clear
npm run build
```

## Smoke Test

1. Open the same group in two authenticated browsers.
2. Send a message in browser A; browser B should receive `.group.message.created` without refresh.
3. Edit, delete, react, create a post, vote in a poll, and confirm browser B updates without polling delay.
4. In browser devtools, confirm the websocket connects to `ws://127.0.0.1:6001`.
5. Stop Soketi; group chat should fall back to polling instead of going stale.

