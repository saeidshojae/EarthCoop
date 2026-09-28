<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\ApiResponse;
use App\Http\Support\Api\V1\Pagination;
use App\Models\NotificationSetting;
use App\Services\Notifications\NotificationCursor;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationCursor $cursor)
    {
        $pageInput = $request->input('page', []);
        if (is_array($pageInput) && (array_key_exists('cursor', $pageInput) || array_key_exists('limit', $pageInput))) {
            return $this->cursorIndex($request, $cursor, $pageInput);
        }

        $page = Pagination::page($request, 20, 50);
        $query = $this->notificationQuery($request);

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

    private function cursorIndex(Request $request, NotificationCursor $cursor, array $pageInput)
    {
        $limit = min(max((int) ($pageInput['limit'] ?? 20), 1), 50);
        $query = $this->notificationQuery($request)
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $rawCursor = trim((string) ($pageInput['cursor'] ?? ''));
        if ($rawCursor !== '') {
            $decoded = $cursor->decode($rawCursor);
            $createdAt = $decoded['created_at'];
            $id = $decoded['id'];

            $query->where(function ($nested) use ($createdAt, $id): void {
                $nested->where('created_at', '<', $createdAt)
                    ->orWhere(function ($sameTime) use ($createdAt, $id): void {
                        $sameTime->where('created_at', '=', $createdAt)
                            ->where('id', '<', $id);
                    });
            });
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $visible = $rows->take($limit)->values();
        $last = $visible->last();

        return ApiResponse::success(
            $visible->map(fn (DatabaseNotification $notification) => $this->serialize($notification))->all(),
            200,
            [
                'pagination' => [
                    'next_cursor' => $hasMore && $last
                        ? $cursor->encode($last->created_at, (string) $last->id)
                        : null,
                    'has_more' => $hasMore,
                ],
            ],
        );
    }

    private function notificationQuery(Request $request)
    {
        $query = $request->user()->notifications()->latest();
        $filter = $request->input('filter', []);
        $status = is_array($filter) ? ($filter['status'] ?? null) : null;

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($status !== null) {
            abort(422, 'Unsupported notification status filter.');
        }

        return $query;
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
            'link' => $data['link'] ?? null,
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
