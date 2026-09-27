<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NajmBaharTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource['id'],
            'tracking_number' => (string) $this->resource['tracking_number'],
            'type' => (string) $this->resource['type'],
            'status' => (string) $this->resource['status'],
            'amount_gol' => (int) $this->resource['amount_gol'],
            'balance_bucket' => (string) $this->resource['balance_bucket'],
            'direction' => (string) $this->resource['direction'],
            'counterparty' => $this->resource['counterparty'],
            'description' => $this->resource['description'],
            'created_at' => $this->resource['created_at'],
        ];
    }
}
