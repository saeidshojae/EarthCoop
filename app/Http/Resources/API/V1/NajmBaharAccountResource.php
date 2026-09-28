<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NajmBaharAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource['account'];

        return [
            'id' => (int) $account->id,
            'account_number' => (string) $account->account_number,
            'name' => (string) $account->name,
            'type' => (string) $account->type,
            'status' => (int) $account->status,
            'balance' => (array) $this->resource['balance'],
        ];
    }
}
