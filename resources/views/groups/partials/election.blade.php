@php
    $systemicElectionEnabled = (bool) optional($groupSetting ?? null)->election_status;
    $openElection = $election ?? null;
    $latest = $latestElection ?? $openElection;
    $state = $latest?->lifecycle_status?->value ?? $latest?->lifecycle_status;
    $maySubmitBallot = ($canParticipateElection ?? false) && !in_array((int)($yourRole ?? 0), [0, 4], true);
    $stateLabels = [
        'scheduled' => 'زمان‌بندی‌شده',
        'open' => 'در حال دریافت رأی',
        'closed' => 'پنجره رأی متوقف شده',
        'tallying' => 'در حال شمارش قطعی',
        'awaiting_acceptance' => 'در انتظار پذیرش مسئولیت',
        'appointing' => 'در حال نصب مسئولان',
        'filled' => 'مسئولیت‌ها تکمیل شده',
        'exhausted' => 'فهرست جایگزین پایان یافته',
        'cancelled' => 'لغوشده',
    ];
@endphp

@if($systemicElectionEnabled)
<div class="election-card" id="electionRedirect" data-systemic-election-card="v1">
    <h4 class="d-flex align-items-center justify-content-between gap-2">
        <span>انتخابات سیستمی گروه</span>
        <img style="width:2rem" src="{{ asset('images/elections.png') }}" alt="">
    </h4>

    @if($openElection)
        <p class="mb-2 text-muted">
            چرخه {{ (int)($openElection->cycle_number ?? 1) }} — {{ $stateLabels['open'] }}
        </p>
        @if($maySubmitBallot)
            <button type="button" class="btn btn-primary w-100 mb-2" data-chat-page-action="open-election">
                مشاهده و ویرایش برگه رأی
            </button>
        @else
            <button type="button" class="btn btn-outline-secondary w-100 mb-2" disabled>
                شما در این گروه واجد امکان ثبت رأی نیستید
            </button>
        @endif
    @elseif($latest)
        <p class="mb-1"><strong>وضعیت آخرین چرخه:</strong> {{ $stateLabels[$state] ?? $state ?? 'نامشخص' }}</p>
        <p class="small text-muted">چرخه بعدی و پنجره دریافت رأی فقط توسط سامانه و سیاست انتخابات ایجاد می‌شود.</p>
    @else
        <p class="mb-1">هنوز چرخه انتخاباتی فعالی برای این گروه باز نشده است.</p>
        <p class="small text-muted">
            شروع انتخابات پس از تحقق حدنصاب سیاست گروه انجام می‌شود
            @if(optional($groupSetting ?? null)->max_for_election)
                (حدنصاب فعلی: {{ (int)$groupSetting->max_for_election }} عضو واجد شرایط).
            @endif
        </p>
    @endif

    <a href="{{ route('elections.portal', $group) }}" class="btn btn-outline-primary w-100">
        وضعیت، گزارش امن و بازبینی انتخابات
    </a>
</div>
@endif
