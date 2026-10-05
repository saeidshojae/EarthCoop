<?php

use App\Http\Controllers\Seo\PillarController;
use Illuminate\Support\Facades\Route;

Route::get('/economy', PillarController::class)
    ->defaults('pillar', 'economy')
    ->name('seo.pillars.economy');

Route::get('/economy/glass', PillarController::class)
    ->defaults('pillar', 'economy-glass')
    ->name('seo.pillars.economy-glass');

Route::get('/economy/ownership', PillarController::class)
    ->defaults('pillar', 'economy-ownership')
    ->name('seo.pillars.economy-ownership');

Route::get('/governance', PillarController::class)
    ->defaults('pillar', 'governance')
    ->name('seo.pillars.governance');

Route::get('/governance/elections', PillarController::class)
    ->defaults('pillar', 'governance-elections')
    ->name('seo.pillars.governance-elections');

Route::get('/governance/local-to-global', PillarController::class)
    ->defaults('pillar', 'governance-local-to-global')
    ->name('seo.pillars.governance-local-to-global');

Route::get('/cooperative', PillarController::class)
    ->defaults('pillar', 'cooperative')
    ->name('seo.pillars.cooperative');

Route::get('/cooperative/global', PillarController::class)
    ->defaults('pillar', 'cooperative-global')
    ->name('seo.pillars.cooperative-global');

Route::get('/technology', PillarController::class)
    ->defaults('pillar', 'technology')
    ->name('seo.pillars.technology');

Route::get('/justice', PillarController::class)
    ->defaults('pillar', 'justice')
    ->name('seo.pillars.justice');

Route::get('/commons', PillarController::class)
    ->defaults('pillar', 'commons')
    ->name('seo.pillars.commons');
