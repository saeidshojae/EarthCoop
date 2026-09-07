<?php

use App\Http\Controllers\Group\MessageController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureMembershipParticipation;
use Illuminate\Support\Facades\Route;

// Loaded after routes/web.php. This shadows only the group message store route
// and preserves the existing controller plus all existing chat middleware.
Route::post('/messages/send', [MessageController::class, 'store'])
    ->middleware([
        Authenticate::class,
        EnsureMembershipParticipation::class,
        'group.session.writable',
        'group.chat.timing',
        'throttle:group-message',
        'group.chat.idempotency',
        'group.chat.context',
    ])
    ->name('groups.messages.store');
