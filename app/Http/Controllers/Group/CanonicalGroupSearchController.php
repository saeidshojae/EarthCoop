<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupUser;
use App\Services\LocationGovernance\GroupGovernanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CanonicalGroupSearchController extends Controller
{
    public function __invoke(Request $request, GroupGovernanceContext $governance): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $searchText = $request->query('q');
        $searchType = $request->query('type', 'name');

        if (empty($searchText)) {
            return response()->json(['groups' => []]);
        }

        $query = Group::query()
            ->whereHas('groupUser', function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->where('status', 1);
            })
            ->with([
                'governanceArea',
                'groupUser' => function ($query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->where('status', 1);
                },
            ])
            ->withCount('users');

        if ($searchType === 'content') {
            $query->where(function ($query) use ($searchText): void {
                $query->whereHas('messages', function ($query) use ($searchText): void {
                    $query->where('message', 'like', "%{$searchText}%");
                })->orWhereHas('blogs', function ($query) use ($searchText): void {
                    $query->where('title', 'like', "%{$searchText}%");
                })->orWhereHas('polls', function ($query) use ($searchText): void {
                    $query->where('question', 'like', "%{$searchText}%");
                });
            });
        } else {
            $query->where('name', 'like', "%{$searchText}%");
        }

        $groups = $query->get()->map(function (Group $group) use ($searchText, $governance): array {
            /** @var GroupUser|null $membership */
            $membership = $group->groupUser->first();
            if ($membership === null) {
                return [];
            }

            $matchingMessages = $group->messages()
                ->where('message', 'like', "%{$searchText}%")
                ->with('user')
                ->get();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'avatar' => $group->avatar ? asset('images/groups/'.$group->avatar) : null,
                'members_count' => (int) $group->users_count,
                'location_level' => $governance->legacyCompatibleLevel($group),
                'is_approved' => 1,
                'status' => 1,
                'role' => $this->roleLabel((int) $membership->role),
                'matching_messages' => $matchingMessages->map(function ($message): array {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'created_at' => $message->created_at,
                        'user' => [
                            'id' => $message->user->id,
                            'name' => $message->user->name,
                        ],
                    ];
                }),
            ];
        })->filter()->values();

        return response()->json(['groups' => $groups]);
    }

    private function roleLabel(int $role): string
    {
        return match ($role) {
            0 => 'ناظر',
            1 => 'فعال',
            2 => 'بازرس',
            3 => 'مدیر',
            4 => 'مهمان',
            5 => 'فعال موقت',
        };
    }
}
