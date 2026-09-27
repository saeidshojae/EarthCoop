<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\Pagination;
use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $page = Pagination::page($request, 20, 50);
        $query = $request->user()->notifications()->latest();

        $status = $request->query('filter.status');
        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($status !== null) {
            abort(422, 'Unsupported notification status filter.');
        }

        $items = $query->forPage($page->number, $page->size)->get()
            ->map(fn (DatabaseNotification $notification) => $this->serialize($notification))
            ->values()->all();

        return response()->json($items);
    }

    public function unread(Request $request)
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        if ($item->read_at === null) {
            $item->markAsRead();
        }

        return response()->json($this->serialize($item->fresh()));
    }

    public function preferences(Request $request)
    {
        return response()->json($this->settingsPayload(NotificationSetting::forUser((int) $request->user()->id)));
    }

    public function updatePreferences(Request $request)
    {
        $settings = NotificationSetting::forUser((int) $request->user()->id);
        $booleanFields = array_values(array_diff($settings->getFillable(), [
            'user_id',
            'najm_bahar_low_balance_threshold',
            'najm_bahar_large_transaction_threshold',
        ]));

        $rules = [];
        foreach ($booleanFields as $field) {
            $rules[$field] = ['sometimes', 'boolean'];
        }
        $rules['najm_bahar_low_balance_threshold'] = ['sometimes', 'integer', 'min:0'];
        $rules['najm_bahar_large_transaction_threshold'] = ['sometimes', 'integer', 'min:0'];

        $validated = $request->validate($rules);
        $settings->fill($validated)->save();

        return response()->json($this->settingsPayload($settings->fresh()));
    }

    private function serialize(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => (string) $notification->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'url' => $data['url'] ?? null,
            'context' => $data['context'] ?? [],
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }

    private function settingsPayload(NotificationSetting $settings): array
    {
        return collect($settings->getFillable())
            ->reject(fn (string $field) => $field === 'user_id')
            ->mapWithKeys(fn (string $field) => [$field => $settings->getAttribute($field)])
            ->all();
    }
}
