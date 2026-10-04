<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Group\MessageController;
use App\Models\Group;
use App\Models\Message;
use App\Services\Group\Api\CanonicalGroupQueryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GroupAttachmentController extends Controller
{
    public function download(Request $request, Group $group, Message $message,
        CanonicalGroupQueryService $groups, MessageController $messages): StreamedResponse
    {
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        $groups->findFor($request->user(), $group);
        abort_if($message->lifecycle_state === 'deleted' || $message->deleted_at !== null, 404);

        // Reuse the canonical message policy and private/legacy disk selection.
        $response = $messages->file($request, $message);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
