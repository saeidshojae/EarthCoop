<?php

namespace App\Http\Controllers\Elections;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionProcessReview;
use App\Services\Elections\ElectionProcessReviewService;
use App\Services\Elections\ElectionReviewEventResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ElectionProcessReviewController extends Controller
{
    public function __construct(
        private readonly ElectionProcessReviewService $reviews,
        private readonly ElectionReviewEventResolver $events,
    ) {}

    public function store(Request $request, Election $election)
    {
        $validated = $request->validate([
            'ground' => 'required|in:'.implode(',', ElectionProcessReview::GROUNDS),
            'challenged_event' => ['required', Rule::in(ElectionReviewEventResolver::TYPES)],
            'challenged_event_id' => 'required|integer|min:1',
            'subject_user_id' => 'nullable|integer|exists:users,id',
            'appointment_id' => 'nullable|integer|exists:election_appointments,id',
            'statement' => 'nullable|string|max:5000',
        ]);

        // User-supplied timestamps are deliberately not accepted. The seven-day
        // review deadline is anchored to immutable election evidence.
        try {
            $evidence = $this->events->resolve(
                $election,
                $validated['challenged_event'],
                (int) $validated['challenged_event_id'],
            );
        } catch (InvalidArgumentException|ModelNotFoundException $exception) {
            throw ValidationException::withMessages([
                'challenged_event_id' => 'رویداد انتخاباتی مرجع معتبر نیست یا به این چرخه تعلق ندارد.',
            ]);
        }

        $subjectUserId = $evidence['subject_user_id'] ?? ($validated['subject_user_id'] ?? null);
        $appointmentId = $evidence['appointment_id'] ?? ($validated['appointment_id'] ?? null);

        $review = $this->reviews->openAutomaticReview(
            $election,
            $request->user(),
            $validated['ground'],
            $validated['challenged_event'],
            $evidence['occurred_at'],
            (int) $validated['challenged_event_id'],
            $subjectUserId,
            $appointmentId,
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
            'challenged_event_id' => $review->challenged_event_id ? (int) $review->challenged_event_id : null,
            'event_occurred_at' => optional($review->event_occurred_at)->toISOString(),
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
