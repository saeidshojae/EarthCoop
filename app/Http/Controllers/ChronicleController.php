<?php

namespace App\Http\Controllers;

use App\Chronicle\EarthCoopEpoch;
use App\Chronicle\EarthCoopYearCalculator;
use App\Chronicle\ChronicleMilestone;
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

        $milestones = ChronicleMilestone::query()
            ->published()
            ->orderByDesc('occurred_on')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(function (ChronicleMilestone $milestone) use (
                $temporal,
                $jalaliContext,
                $gregorianContext,
                $earthCoopYears,
            ): array {
                $date = LocalDate::fromCanonical($milestone->occurred_on->format('Y-m-d'));

                return [
                    'id' => $milestone->id,
                    'title' => $milestone->translatedTitle(),
                    'description' => $milestone->translatedDescription(),
                    'jalali_date' => $temporal->date($date, $jalaliContext, 'medium'),
                    'gregorian_date' => $temporal->date($date, $gregorianContext, 'long'),
                    'earthcoop_year' => $earthCoopYears->yearFor($date),
                ];
            });

        return view('chronicle.index', [
            'jalaliToday' => $temporal->date($today, $jalaliContext, 'long'),
            'gregorianToday' => $temporal->date($today, $gregorianContext, 'long'),
            'earthCoopYear' => $earthCoopYears->yearFor($today),
            'epochJalali' => $temporal->date($epoch, $jalaliContext, 'medium'),
            'epochGregorian' => $temporal->date($epoch, $gregorianContext, 'long'),
            'milestones' => $milestones,
        ]);
    }
}
