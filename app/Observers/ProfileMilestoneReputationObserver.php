<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ReputationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProfileMilestoneReputationObserver
{
    public function __construct(protected ReputationService $reputationService)
    {
    }

    public function updated(User $user): void
    {
        $milestones = [
            'profile_photo_uploaded' => [
                'field' => 'avatar',
                'earned' => $user->wasChanged('avatar') && filled($user->avatar),
            ],
            'social_links_added' => [
                'field' => 'social_networks',
                'earned' => $user->wasChanged('social_networks') && $this->hasMeaningfulValues($user->social_networks),
            ],
            'documents_uploaded' => [
                'field' => 'documents',
                'earned' => $user->wasChanged('documents') && $this->hasMeaningfulValues($user->documents),
            ],
            'bio_added' => [
                'field' => 'biografie',
                'earned' => $user->wasChanged('biografie') && filled(trim((string) $user->biografie)),
            ],
        ];

        foreach ($milestones as $action => $milestone) {
            if (! $milestone['earned']) {
                continue;
            }

            try {
                $this->reputationService->applyAction(
                    $user,
                    $action,
                    ['profile_field' => $milestone['field']],
                    $user->id,
                    'profile.milestone',
                    $action . ':user:' . $user->id
                );
            } catch (Throwable $e) {
                // Profile updates must not fail merely because reputation recording failed.
                Log::warning('Profile milestone reputation award failed', [
                    'user_id' => $user->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function hasMeaningfulValues(mixed $value): bool
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return filled(trim($value));
            }
        }

        if (! is_array($value)) {
            return filled($value);
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                if ($this->hasMeaningfulValues($item)) {
                    return true;
                }
                continue;
            }

            if (filled(is_string($item) ? trim($item) : $item)) {
                return true;
            }
        }

        return false;
    }
}
