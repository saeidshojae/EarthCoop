<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel Ø¨Ø±Ø§ÛŒ Ú†Øª Ù¾Ø´ØªÛŒØ¨Ø§Ù†ÛŒ
Broadcast::channel('support-chat.{chatId}', function ($user, $chatId) {
    $chat = \App\Models\SupportChat::find($chatId);
    
    if (!$chat) {
        return false;
    }
    
    // Ú©Ø§Ø±Ø¨Ø± ÛŒØ§ Ù¾Ø´ØªÛŒØ¨Ø§Ù† Ù…ÛŒâ€ŒØªÙˆØ§Ù†Ù†Ø¯ Ø¨Ù‡ channel Ú¯ÙˆØ´ Ø¯Ù‡Ù†Ø¯
    return $chat->user_id === $user->id || $chat->agent_id === $user->id;
});

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    $groupId = (int) $groupId;
    $userId = (int) $user->id;
    $cacheKey = "group-channel-auth:{$groupId}:{$userId}";
    $ttlSeconds = max(1, (int) config('group-chat.channel_auth_cache_ttl_seconds', 30));

    return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($groupId, $userId) {
        return \App\Models\GroupUser::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->exists();
    });
});

Broadcast::channel('private-chat.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\PrivateConversation::with('users')->find((int) $conversationId);

    if (!$conversation) {
        return false;
    }

    return $conversation->users->contains('id', $user->id);
});

