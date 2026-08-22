<?php

namespace App\Http\Controllers\Elections;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionProcessReview;
use App\Services\Elections\ElectionProcessReviewService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ElectionProcessReviewController extends Controller
{
    public function __construct(private readonly ElectionProcessReviewService $reviews) {}

    public function store(Request $request, Election $election)
    {
        $validated = $request->validate([
            'ground' => 'required|in:'.implode(',', ElectionProcessReview::GROUNDS),
            'challenged_event' => 'required|string|max:64',
            'challenged_event_id' => 'nullable|integer|min:1',
            'event_occurred_at' => 'required|date',
            'subject_user_id' => 'nullable|integer|exists:users,id',
            'appointment_id' => 'nullable|integer|exists:election_appointments,id',
            'statement' => 'nullable|string|max:5000',
        ]);

        $review = $this->reviews->openAutomaticReview(
            $election,
            $request->user(),
            $validated['ground'],
            $validated['challenged_event'],
            Carbon::parse($validated['event_occurred_at']),
            $validated['challenged_event_id'] ?? null,
            $validated['subject_user_id'] ?? null,
            $validated['appointment_id'] ?? null,
            $validated['statement'] ?? null,
        );

        return response()->json($this->safePayload($review), 201);
    }

    public function show(Request $request, ElectionProcessReview $review)
    {
        abort_unless(in_array((int) $request->user()->id, [(int) $review->requester_user_id, (int) $review->subject_user_id], true), 403);
        return response()->json($this->safePayload($review));
    }

    public function requestHuman(Request $request, ElectionProcessReview $review)
    {
        return response()->json($this->safePayload($this->reviews->requestHumanReview($review, $request->user())));
    }

    public function endorse(Request $request, ElectionProcessReview $review)
    {
        return response()->json($this->safePayload($this->reviews->endorse($review, $request->user())));
    }

    public function stay(Request $request, ElectionProcessReview $review)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        return response()->json($this->safePayload($this->reviews->setInterimStay($review, $request->user(), $validated['reason'])));
    }

    public function decide(Request $request, ElectionProcessReview $review)
    {
        $validated = $request->validate([
            'decision' => 'required|in:upheld,corrected,dismissed',
            'reason' => 'required|string|max:5000',
            'remediation_reference' => 'nullable|string|max:255',
        ]);
        return response()->json($this->safePayload($this->reviews->decide(
            $review,
            $request->user(),
            $validated['decision'],
            $validated['reason'],
            $validated['remediation_reference'] ?? null,
        )));
    }

    private function safePayload(ElectionProcessReview $review): array
    {
        return [
            'id' => (int) $review->id,
            'election_id' => (int) $review->election_id,
            'ground' => $review->ground,
            'challenged_event' => $review->challenged_event,
            'automatic_status' => $review->automatic_status,
            'automatic_result' => $review->automatic_result,
            'human_status' => $review->human_status,
            'support_count' => (int) $review->support_count,
            'human_deadline_at' => optional($review->human_deadline_at)->toISOString(),
            'decision_due_at' => optional($review->decision_due_at)->toISOString(),
            'interim_state' => $review->interim_state,
            'decision' => $review->decision,
            'decision_reason' => $review->decision_reason,
            'remediation_reference' => $review->remediation_reference,
        ];
    }
}
