<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\StoreMediaRequest;
use App\Http\Resources\API\V1\MediaResource;
use App\Services\Media\MediaService;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request, MediaService $service)
    {
        $media = $service->store(
            $request->user(),
            $request->file('file'),
            (string) $request->validated('purpose'),
        );

        return response()->json((new MediaResource($media))->resolve($request), 201);
    }
}
