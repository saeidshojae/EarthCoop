<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NajmHodaConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'title' => (string) ($this->title ?? 'بدون عنوان'),
            'agent_type' => (string) ($this->agent_type ?? 'auto'),
            'status' => (string) ($this->status ?? 'active'),
            'last_message' => $this->whenLoaded('lastMessage', fn () => $this->lastMessage?->content),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message) => [
                'id' => (int) $message->id,
                'role' => (string) $message->role,
                'content' => (string) $message->content,
                'created_at' => optional($message->created_at)->utc()->toIso8601String(),
            ])->values()->all()),
            'created_at' => optional($this->created_at)->utc()->toIso8601String(),
            'updated_at' => optional($this->updated_at)->utc()->toIso8601String(),
        ];
    }
}
