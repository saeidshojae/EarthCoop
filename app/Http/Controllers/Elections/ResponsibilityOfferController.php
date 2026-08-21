<?php

namespace App\Http\Controllers\Elections;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\ElectionResponsibilityOffer;
use App\Services\Elections\ElectionResponsibilityOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResponsibilityOfferController extends Controller
{
    public function __construct(
        private readonly ElectionResponsibilityOfferService $offers,
    ) {}

    /**
     * Compatibility bridge for the legacy profile link.
     * GET never mutates election state; it only resolves the canonical offer
     * and presents a CSRF-protected confirmation form.
     */
    public function legacyConfirmation(Request $request, string $type): View
    {
        abort_unless(in_array($type, ['accept', 'reject'], true), 404);

        $candidateId = (int) $request->query('id', 0);
        $candidate = Candidate::query()
            ->whereKey($candidateId)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if ($candidate === null) {
            throw new NotFoundHttpException();
        }

        $offer = ElectionResponsibilityOffer::query()
            ->with(['election.group', 'contractVersion'])
            ->where('election_id', $candidate->election_id)
            ->where('candidate_user_id', (int) $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if ($offer === null) {
            abort(409, 'برای این انتخاب، دعوت فعال و قابل پاسخ‌گویی وجود ندارد.');
        }

        return view('elections.responsibility-offer-confirm', [
            'offer' => $offer,
            'decision' => $type === 'accept' ? 'accept' : 'decline',
        ]);
    }

    public function respond(
        Request $request,
        ElectionResponsibilityOffer $offer,
        string $decision,
    ): RedirectResponse {
        abort_unless(in_array($decision, ['accept', 'decline'], true), 404);

        $userId = (int) $request->user()->id;
        if ($decision === 'accept') {
            $this->offers->accept($offer, $userId);

            return redirect()->route('profile.show')
                ->with('success', 'پذیرش مسئولیت ثبت شد. نصب سمت پس از تکمیل فرایند انتخاباتی انجام می‌شود.');
        }

        $this->offers->decline($offer, $userId);

        return redirect()->route('profile.show')
            ->with('success', 'عدم پذیرش مسئولیت ثبت شد و نفر بعدی صف بررسی می‌شود.');
    }
}
