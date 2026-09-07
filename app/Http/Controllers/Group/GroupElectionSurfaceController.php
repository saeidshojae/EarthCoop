<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;

class GroupElectionSurfaceController extends Controller
{
    public function stats(Group $group)
    {
        $this->authorize('view', $group);

        $systemicElectionsQuery = $group->elections();
        $systemic = [
            'total' => (clone $systemicElectionsQuery)->count(),
            'active' => (clone $systemicElectionsQuery)
                ->where('is_closed', false)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->count(),
            'closed' => (clone $systemicElectionsQuery)
                ->where(function ($query) {
                    $query->where('is_closed', true)
                        ->orWhere('ends_at', '<', now());
                })
                ->count(),
        ];

        $internalElectionsQuery = $group->polls()->where('main_type', 0);
        $internal = [
            'total' => (clone $internalElectionsQuery)->count(),
            'active' => (clone $internalElectionsQuery)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->count(),
            'closed' => (clone $internalElectionsQuery)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count(),
        ];

        return response()->json([
            'status' => 'success',
            'elections' => [
                'total' => $systemic['total'] + $internal['total'],
                'active' => $systemic['active'] + $internal['active'],
                'closed' => $systemic['closed'] + $internal['closed'],
                'systemic' => $systemic,
                'internal' => $internal,
            ],
        ]);
    }
}
