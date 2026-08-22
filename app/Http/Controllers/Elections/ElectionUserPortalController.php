<?php

namespace App\Http\Controllers\Elections;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionLifecycleTransition;
use App\Models\ElectionProcessReview;
use App\Models\ElectionRepresentationAssignment;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVoteSnapshotRun;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionCandidateReportService;
use App\Services\Elections\ElectionFeedbackTopicAggregationService;
use App\Services\Elections\ElectionFeedbackTopicResponseService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ElectionUserPortalController extends Controller
{
    public function show(
        Request $request,
        Group $group,
        ElectionCandidateReportService $reports,
        ElectionFeedbackTopicResponseService $responses,
        ElectionFeedbackTopicAggregationService $topics,
    ) {
        $member = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 1)
            ->where('role', '!=', 4)
            ->first();
        abort_unless($member && ! (bool) $request->user()->is_system, 403);

        $election = Election::query()->with('policyVersion')
            ->where('group_id', $group->id)
            ->when($request->filled('election_id'), fn ($query) => $query->whereKey((int) $request->input('election_id')))
            ->orderByDesc('cycle_number')->orderByDesc('id')->first();

        $cycles = Election::query()->where('group_id', $group->id)
            ->orderByDesc('cycle_number')->orderByDesc('id')->limit(20)->get();

        $members = User::query()
            ->join('group_user', 'group_user.user_id', '=', 'users.id')
            ->where('group_user.group_id', $group->id)
            ->where('group_user.status', 1)
            ->where('group_user.role', '!=', 4)
            ->where('users.is_system', false)
            ->select('users.id', 'users.first_name', 'users.last_name')
            ->orderBy('users.first_name')->orderBy('users.last_name')->get();

        $selectedSubjectId = $request->filled('subject_user_id') ? (int) $request->input('subject_user_id') : null;
        $selectedPosition = $request->input('position', 'manager') === 'inspector' ? 'inspector' : 'manager';
        $candidateReport = null;
        $candidateReportError = null;
        $topicResponses = collect();
        $publicTopics = null;
        $mayRespondToTopics = false;

        if ($election) {
            if ($selectedSubjectId) {
                $subjectIsMember = $members->contains(fn ($user) => (int) $user->id === $selectedSubjectId);
                abort_unless($subjectIsMember, 404);
                try {
                    $candidateReport = $reports->report($election, $selectedSubjectId, $selectedPosition);
                } catch (Throwable $exception) {
                    $candidateReportError = $exception->getMessage();
                }
            }

            try {
                $topicResponses = $responses->publicForMember($election, $request->user(), $selectedSubjectId);
            } catch (Throwable) {
                $topicResponses = collect();
            }

            $mayRespondToTopics = ElectionTallyResult::query()
                ->where('election_id', $election->id)->where('candidate_user_id', $request->user()->id)->exists()
                || ElectionAppointment::query()->where('election_id', $election->id)
                    ->where('user_id', $request->user()->id)->where('status', 'active')->exists();
            if ($mayRespondToTopics) {
                try {
                    $publicTopics = $topics->publicAggregate($election, (int) $request->user()->id, $request->user());
                } catch (Throwable) {
                    $publicTopics = null;
                }
            }
        }

        $ownReviews = $election
            ? ElectionProcessReview::query()->where('election_id', $election->id)
                ->where(function ($query) use ($request) {
                    $query->where('requester_user_id', $request->user()->id)
                        ->orWhere('subject_user_id', $request->user()->id);
                })->orderByDesc('id')->get()
            : collect();
        $supportableReviews = $election
            ? ElectionProcessReview::query()->where('election_id', $election->id)
                ->where('human_status', 'awaiting_support')->where('human_deadline_at', '>=', now())
                ->orderBy('human_deadline_at')->get(['id','ground','challenged_event','support_count','human_deadline_at'])
            : collect();

        $reviewEvents = $election ? $this->reviewEvents($election, $request->user()) : collect();
        $offers = $election
            ? ElectionResponsibilityOffer::query()->with('contractVersion')
                ->where('election_id', $election->id)->where('candidate_user_id', $request->user()->id)
                ->orderByDesc('id')->get()
            : collect();

        return view('elections.portal', compact(
            'group', 'election', 'cycles', 'members', 'selectedSubjectId', 'selectedPosition',
            'candidateReport', 'candidateReportError', 'topicResponses', 'publicTopics',
            'mayRespondToTopics', 'ownReviews', 'supportableReviews', 'reviewEvents', 'offers'
        ));
    }

    private function reviewEvents(Election $election, User $viewer)
    {
        $events = collect();
        $push = function (string $type, int $id, string $label, $occurredAt, ?int $subjectUserId = null, ?int $appointmentId = null) use (&$events): void {
            if (! $occurredAt) return;
            $events->push([
                'type' => $type,
                'id' => $id,
                'label' => $label,
                'occurred_at' => $occurredAt,
                'subject_user_id' => $subjectUserId,
                'appointment_id' => $appointmentId,
            ]);
        };

        ElectionEligibilitySnapshot::query()->where('election_id', $election->id)
            ->where('user_id', $viewer->id)->get()->each(fn ($row) =>
                $push('eligibility_snapshot', (int) $row->id, 'ثبت صلاحیت انتخاباتی من', $row->captured_at, (int) $viewer->id)
            );
        ElectionBallotEvent::query()->where('election_id', $election->id)
            ->where('voter_id', $viewer->id)->orderByDesc('occurred_at')->limit(30)->get()->each(fn ($row) =>
                $push('ballot_event', (int) $row->id, 'رویداد برگه رأی من: '.$row->event_type, $row->occurred_at,
                    $row->candidate_user_id ? (int) $row->candidate_user_id : ($row->previous_candidate_user_id ? (int) $row->previous_candidate_user_id : null))
            );
        ElectionVoteSnapshotRun::query()->where('election_id', $election->id)->get()->each(fn ($row) =>
            $push('vote_snapshot', (int) $row->id, 'توقف و snapshot رسمی رأی‌ها', $row->stopped_at)
        );
        ElectionTallyResult::query()->where('election_id', $election->id)->orderBy('position')->orderBy('rank')->limit(60)->get()->each(fn ($row) =>
            $push('tally_result', (int) $row->id, 'نتیجه رتبه‌بندی '.$row->position.' رتبه '.$row->rank, $row->tallied_at, (int) $row->candidate_user_id)
        );
        ElectionResponsibilityOffer::query()->where('election_id', $election->id)
            ->where('candidate_user_id', $viewer->id)->get()->each(fn ($row) =>
                $push('responsibility_offer', (int) $row->id, 'پیشنهاد مسئولیت من: '.$row->position, $row->responded_at ?? $row->offered_at, (int) $viewer->id)
            );
        ElectionAppointment::query()->where('election_id', $election->id)->get()->each(fn ($row) =>
            $push('appointment', (int) $row->id, 'انتصاب رسمی '.$row->position.' — عضو #'.$row->user_id, $row->appointed_at, (int) $row->user_id, (int) $row->id)
        );
        ElectionRepresentationAssignment::query()->whereHas('appointment', fn ($q) => $q->where('election_id', $election->id))
            ->with('appointment')->get()->each(fn ($row) =>
                $push('representation_assignment', (int) $row->id, 'فعال‌سازی نمایندگی — عضو #'.$row->user_id, $row->activated_at, (int) $row->user_id, (int) $row->appointment_id)
            );
        ElectionLifecycleTransition::query()->where('election_id', $election->id)->orderByDesc('transitioned_at')->get()->each(fn ($row) =>
            $push('lifecycle_transition', (int) $row->id, 'تغییر وضعیت '.($row->from_status?->value ?? $row->from_status).' ← '.($row->to_status?->value ?? $row->to_status), $row->transitioned_at)
        );

        return $events->sortByDesc('occurred_at')->values();
    }
}
