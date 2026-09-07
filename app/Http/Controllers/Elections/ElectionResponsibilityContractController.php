<?php

namespace App\Http\Controllers\Elections;

use App\Http\Controllers\Controller;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use Illuminate\Http\Request;

class ElectionResponsibilityContractController extends Controller
{
    public function download(Request $request, ElectionResponsibilityContractVersion $contract)
    {
        $allowed = ElectionResponsibilityOffer::query()
            ->where('contract_version_id', $contract->id)
            ->where('candidate_user_id', $request->user()->id)
            ->exists();
        abort_unless($allowed, 403);
        $filename = "election-{$contract->position}-contract-v{$contract->version}.txt";
        return response($contract->body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
