<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationGovernance\CommunityAreaService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommunityAreaController extends Controller
{
    public function store(
        Location $location,
        Request $request,
        CommunityAreaService $communities,
    ): RedirectResponse {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);

        try {
            $communities->createFor($location, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors([
                'community' => 'برای این مکان امکان ایجاد اجتماع محلی وجود ندارد.',
            ]);
        }

        return back()->with('success', 'اجتماع محلی ایجاد یا بازیابی شد.');
    }
}
