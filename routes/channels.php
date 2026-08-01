<?php

use Illuminate\Support\Facades\Broadcast;

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

// کانال برای چت پشتیبانی
Broadcast::channel('support-chat.{chatId}', function ($user, $chatId) {
    $chat = \App\Models\SupportChat::find($chatId);
    
    if (!$chat) {
        return false;
    }
    
    // کاربر یا پشتیبان می‌توانند به channel گوش دهند
    return $chat->user_id === $user->id || $chat->agent_id === $user->id;
});

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    return \App\Models\GroupUser::where('group_id', (int) $groupId)
        ->where('user_id', (int) $user->id)
        ->where('status', 1)
        ->exists();
});

Broadcast::channel('private-chat.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\PrivateConversation::with('users')->find((int) $conversationId);

    if (!$conversation) {
        return false;
    }

    return $conversation->users->contains('id', $user->id);
});