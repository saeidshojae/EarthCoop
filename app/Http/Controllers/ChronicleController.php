<?php

namespace App\Http\Controllers;

use App\Chronicle\EarthCoopEpoch;
use App\Chronicle\EarthCoopYearCalculator;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\View\View;

final class ChronicleController extends Controller
{
    public function __invoke(
        TemporalService $temporal,
        TemporalContextResolver $contexts,
        EarthCoopYearCalculator $earthCoopYears,
    ): View {
        $viewerContext = $contexts->defaultContext();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $viewerNow = $now->setTimezone(new DateTimeZone($viewerContext->timezone()));
        $today = LocalDate::fromCanonical($viewerNow->format('Y-m-d'));

        $jalaliContext = $contexts->forLocale('fa', $viewerContext->timezone());
        $gregorianContext = $contexts->forLocale('en', $viewerContext->timezone());
        $epoch = LocalDate::fromCanonical(EarthCoopEpoch::canonicalDate());

        return view('chronicle.index', [
            'jalaliToday' => $temporal->date($today, $jalaliContext, 'long'),
            'gregorianToday' => $temporal->date($today, $gregorianContext, 'long'),
            'earthCoopYear' => $earthCoopYears->yearFor($today),
            'epochJalali' => $temporal->date($epoch, $jalaliContext, 'medium'),
            'epochGregorian' => $temporal->date($epoch, $gregorianContext, 'long'),
        ]);
    }
}
