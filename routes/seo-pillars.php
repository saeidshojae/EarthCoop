<?php

use App\Http\Controllers\Seo\PillarController;
use Illuminate\Support\Facades\Route;

Route::get('/economy', PillarController::class)
    ->defaults('pillar', 'economy')
    ->name('seo.pillars.economy');

Route::get('/economy/glass', PillarController::class)
    ->defaults('pillar', 'economy-glass')
    ->name('seo.pillars.economy-glass');

Route::get('/governance', PillarController::class)
    ->defaults('pillar', 'governance')
    ->name('seo.pillars.governance');

Route::get('/governance/elections', PillarController::class)
    ->defaults('pillar', 'governance-elections')
    ->name('seo.pillars.governance-elections');

Route::get('/justice', PillarController::class)
    ->defaults('pillar', 'justice')
    ->name('seo.pillars.justice');

Route::get('/commons', PillarController::class)
    ->defaults('pillar', 'commons')
    ->name('seo.pillars.commons');
