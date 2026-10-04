<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Group\MessageController;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\Group;
use App\Models\Message;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class GroupMessageController extends Controller
{
    public function store(Request $request, Group $group, MessageController $messages): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'file' => ['prohibited'],
            'voice_message' => ['prohibited'],
            'parent_id' => ['prohibited'],
            'client_message_id' => ['prohibited'],
            'media_id' => ['prohibited'],
        ]);
        $text = trim($validated['message']);
        if ($text === '') {
            throw ValidationException::withMessages(['message' => 'متن پیام را وارد کنید.']);
        }
        // A durable canonical message key also protects retries after an HTTP result is lost.
        // Include the content so reusing an expired HTTP key cannot acknowledge different text.
        $clientKey = 'native-'.hash('sha256', json_encode([
            (int) $request->user()->id, (int) $group->id,
            trim((string) $request->header('Idempotency-Key')), $text,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing = Message::where('group_id', $group->id)
            ->where('user_id', $request->user()->id)->where('client_message_id', $clientKey)->first();
        if ($existing) {
            return $this->projection((int) $existing->id, $group, $existing->message);
        }
        $request->replace(['group_id' => (int) $group->id, 'message' => $text, 'client_message_id' => $clientKey]);
        $response = $messages->store($request);
        $body = $response->getData(true);
        if ($response->getStatusCode() >= 400) {
            $status = $response->getStatusCode();
            $error = ApiResponse::error(
                $status === 429 ? 'rate_limited' : 'group_message_failed',
                $status === 429 ? 'کمی صبر کنید و دوباره پیام را ارسال کنید.' : 'امکان ارسال پیام وجود ندارد.',
                $status, $body['errors'] ?? null, $status === 429 || $status >= 500,
            );
            // Do not persist a temporary throttle result as a completed intent.
            throw new HttpResponseException($error);
        }
        return $this->projection((int) $body['message']['id'], $group, $body['message']['message']);
    }

    private function projection(int $id, Group $group, string $html): JsonResponse
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?\s*>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return ApiResponse::success(['id' => $id, 'group_id' => (int) $group->id, 'message' => $text], 201);
    }
}
