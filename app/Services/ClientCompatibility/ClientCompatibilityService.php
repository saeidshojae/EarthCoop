<?php

namespace App\Services\ClientCompatibility;

use Illuminate\Validation\ValidationException;

class ClientCompatibilityService
{
    public function evaluate(string $platform, string $version): array
    {
        $platform = strtolower(trim($platform));
        $version = trim($version);

        if (! in_array($platform, ['android', 'ios'], true)) {
            throw ValidationException::withMessages([
                'platform' => ['Unsupported client platform.'],
            ]);
        }

        if (! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw ValidationException::withMessages([
                'version' => ['Invalid semantic version.'],
            ]);
        }

        $minimum = (string) config("client-compatibility.{$platform}.minimum_version");
        $latest = (string) config("client-compatibility.{$platform}.latest_version");
        $policy = new ClientVersionPolicy($platform, $minimum, $latest);

        return [
            'policy' => $policy,
            'update_required' => version_compare($version, $minimum, '<'),
            'update_recommended' => version_compare($version, $latest, '<'),
        ];
    }
}
