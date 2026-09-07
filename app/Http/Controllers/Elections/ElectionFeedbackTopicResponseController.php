<?php

namespace App\Http\Controllers\Elections;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\Elections\ElectionFeedbackTopicResponseService;
use Illuminate\Http\Request;

class ElectionFeedbackTopicResponseController extends Controller
{
    public function __construct(private readonly ElectionFeedbackTopicResponseService $responses) {}

    public function store(Request $request, Election $election)
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:64',
            'body' => 'required|string|max:5000',
        ]);
        $response = $this->responses->publish($election, $request->user(), $validated['topic'], $validated['body']);
        return response()->json([
            'id' => (int) $response->id,
            'topic' => $response->topic_key,
            'body' => $response->body,
            'aggregate_count' => (int) $response->aggregate_count,
            'published_at' => $response->published_at->toISOString(),
        ], 201);
    }

    public function index(Request $request, Election $election)
    {
        $subject = $request->filled('subject_user_id') ? (int) $request->input('subject_user_id') : null;
        return response()->json(['data' => $this->responses->publicForMember($election, $request->user(), $subject)->values()]);
    }
}
