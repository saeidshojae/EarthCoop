<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NajmHodaCapabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'action' => (string) ($this->resource['action'] ?? ''),
            'version' => (int) ($this->resource['version'] ?? 1),
            'enabled' => (bool) ($this->resource['enabled'] ?? false),
            'risk' => (string) ($this->resource['risk'] ?? 'low'),
            'default_mode' => (string) ($this->resource['default_mode'] ?? 'propose'),
            'human_approval_required' => (bool) ($this->resource['human_approval_required'] ?? false),
            'required_input' => array_values((array) ($this->resource['required_input'] ?? [])),
            'optional_input' => array_values((array) ($this->resource['optional_input'] ?? [])),
            'output' => (array) ($this->resource['output'] ?? []),
        ];
    }
}
