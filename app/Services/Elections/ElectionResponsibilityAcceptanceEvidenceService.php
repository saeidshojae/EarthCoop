<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use App\Models\ElectionResponsibilityOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectionResponsibilityAcceptanceEvidenceService
{
    public const CONFIRMATION_TEXT = 'I have read and knowingly accept this exact responsibility contract version.';

    public function confirm(ElectionResponsibilityOffer $offer, User $user, int $contractVersionId): ElectionResponsibilityOffer
    {
        return DB::transaction(function () use ($offer, $user, $contractVersionId): ElectionResponsibilityOffer {
            $locked = ElectionResponsibilityOffer::query()->with('contractVersion')->lockForUpdate()->findOrFail($offer->id);
            if ((int) $locked->candidate_user_id !== (int) $user->id) {
                throw ValidationException::withMessages(['offer' => 'این دعوت متعلق به حساب شما نیست.']);
            }
            if ($locked->status !== ElectionResponsibilityOfferStatus::Pending) {
                throw ValidationException::withMessages(['offer' => 'این دعوت دیگر قابل پذیرش نیست.']);
            }
            if ((int) $locked->contract_version_id !== $contractVersionId) {
                throw ValidationException::withMessages(['contract_version_id' => 'نسخه تأییدشده با نسخه دعوت یکسان نیست.']);
            }
            $contract = $locked->contractVersion;
            if (! $contract || ! $contract->e0_compliant || ! $contract->hasCompleteE0Manifest() || $contract->published_at === null) {
                throw ValidationException::withMessages(['offer' => 'نسخه قرارداد دعوت، الزامات ساختاری E0 را ندارد.']);
            }
            $metadata = $locked->response_metadata ?? [];
            $metadata['acceptance_evidence'] = [
                'candidate_user_id' => (int) $user->id,
                'contract_version_id' => (int) $contract->id,
                'confirmed_at' => now()->toISOString(),
                'confirmation_text_hash' => hash('sha256', self::CONFIRMATION_TEXT),
            ];
            $locked->forceFill(['response_metadata' => $metadata])->save();
            return $locked->refresh();
        }, 3);
    }
}
