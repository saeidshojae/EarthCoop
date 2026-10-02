<?php

namespace App\Services\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationPreference;
use App\Models\User;

class CommunicationPreferenceService
{
    public function allows(
        User $user,
        string $topicKey,
        CommunicationClassification $classification,
        string $channel = 'email',
    ): PreferenceDecision {
        if ($classification === CommunicationClassification::Required) {
            return new PreferenceDecision(true, 'required_bypass');
        }

        $preference = CommunicationPreference::query()
            ->where('user_id', $user->id)
            ->where('topic_key', $topicKey)
            ->where('channel', $channel)
            ->value('preference');

        if ($preference === 'on') {
            return new PreferenceDecision(true, 'user_on');
        }

        if ($preference === 'off') {
            return new PreferenceDecision(false, 'user_off');
        }

        if ($classification === CommunicationClassification::Operational) {
            return new PreferenceDecision(true, 'operational_default_on');
        }

        return new PreferenceDecision(false, 'optional_default_off');
    }

    public function decide(
        User $user,
        string $topicKey,
        CommunicationClassification $classification,
        string $channel = 'email',
    ): PreferenceDecision {
        return $this->allows($user, $topicKey, $classification, $channel);
    }
}
