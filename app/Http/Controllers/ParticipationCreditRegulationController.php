<?php

namespace App\Http\Controllers;

use App\Services\ParticipationCreditRegulationService;

class ParticipationCreditRegulationController extends Controller
{
    public function __invoke(ParticipationCreditRegulationService $regulationService)
    {
        return view('participation.credit-regulation', $regulationService->snapshot());
    }
}
