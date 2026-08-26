@extends('layouts.unified')

@section('title', 'تاریخچه انتخابات سیستمی - ' . config('app.name', 'EarthCoop'))

@section('content')
<div class="container py-4" dir="rtl" data-election-history-v2>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">تاریخچه انتخابات سیستمی من</h1>
            <p class="text-muted mb-0">چرخه‌ها بر اساس lifecycle واقعی نمایش داده می‌شوند؛ شمارش، پذیرش مسئولیت و انتصاب از نتیجه رأی تفکیک شده‌اند.</p>
        </div>
        <a href="{{ route('history.index') }}" class="btn btn-outline-secondary">بازگشت به تاریخچه</a>
    </div>

    @forelse($currentElections as $election)
        @php
            $state = $election->lifecycle_status?->value ?? $election->lifecycle_status;
            $policy = $election->policyVersion;
            $managerVotes = $election->yourVotes->filter(fn($vote) => in_array((string)$vote->position, ['1','manager'], true));
            $inspectorVotes = $election->yourVotes->filter(fn($vote) => in_array((string)$vote->position, ['0','inspector'], true));
        @endphp
        <article class="card shadow-sm mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>{{ $election->group->name ?? 'گروه' }}</strong>
                    <span class="text-muted">— چرخه {{ (int)($election->cycle_number ?? 1) }}</span>
                </div>
                <span class="badge bg-primary">{{ $state }}</span>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-sm-6 col-lg-3"><div class="border rounded p-2">نسخه سیاست: <strong>v{{ (int)($policy?->version ?? 0) }}</strong></div></div>
                    <div class="col-sm-6 col-lg-3"><div class="border rounded p-2">ظرفیت مدیر: <strong>{{ (int)($policy?->manager_count ?? 0) }}</strong></div></div>
                    <div class="col-sm-6 col-lg-3"><div class="border rounded p-2">ظرفیت بازرس: <strong>{{ (int)($policy?->inspector_count ?? 0) }}</strong></div></div>
                    <div class="col-sm-6 col-lg-3"><div class="border rounded p-2">مهلت پاسخ: <strong>{{ (int)($policy?->response_duration_days ?? 0) }} روز</strong></div></div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <h2 class="h6">برگه رأی فعلی من در این چرخه</h2>
                        <div class="border rounded p-3">
                            <strong>مدیران</strong>
                            <ul class="mb-2 mt-1">
                                @forelse($managerVotes as $vote)
                                    <li>
                                        {{ trim(($vote->candidateUser->first_name ?? '').' '.($vote->candidateUser->last_name ?? '')) ?: ('عضو #'.$vote->candidate_user_id) }}
                                        <span class="text-muted small">— افشا: {{ $vote->vote_visibility?->value ?? $vote->vote_visibility ?? 'confidential' }}</span>
                                    </li>
                                @empty
                                    <li class="text-muted">انتخابی ثبت نشده است.</li>
                                @endforelse
                            </ul>
                            <strong>بازرسان</strong>
                            <ul class="mb-0 mt-1">
                                @forelse($inspectorVotes as $vote)
                                    <li>
                                        {{ trim(($vote->candidateUser->first_name ?? '').' '.($vote->candidateUser->last_name ?? '')) ?: ('عضو #'.$vote->candidate_user_id) }}
                                        <span class="text-muted small">— افشا: {{ $vote->vote_visibility?->value ?? $vote->vote_visibility ?? 'confidential' }}</span>
                                    </li>
                                @empty
                                    <li class="text-muted">انتخابی ثبت نشده است.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <h2 class="h6">مسئولیت و انتصاب من</h2>
                        <div class="border rounded p-3 h-100">
                            @forelse($election->responsibilityOffers as $offer)
                                <div class="mb-2">
                                    پیشنهاد {{ $offer->position }} — رتبه {{ (int)$offer->ranking_position }} —
                                    <strong>{{ $offer->status?->value ?? $offer->status }}</strong>
                                    @if(($offer->status?->value ?? $offer->status) === 'pending')
                                        <a class="btn btn-sm btn-outline-primary ms-1" href="{{ route('profile.accept.candidate', ['type'=>$offer->position]) }}">مشاهده قرارداد و پاسخ</a>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted mb-2">پیشنهاد مسئولیتی برای شما در این چرخه ثبت نشده است.</p>
                            @endforelse

                            @foreach($election->appointments as $appointment)
                                <div class="alert alert-light py-2 mb-1">
                                    انتصاب {{ $appointment->position }} — وضعیت: <strong>{{ $appointment->status }}</strong>
                                    @if($appointment->review_state && $appointment->review_state !== 'clear')
                                        — بازبینی: {{ $appointment->review_state }}
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-primary" href="{{ route('elections.portal', ['group'=>$election->group, 'election_id'=>$election->id]) }}">جزئیات، گزارش امن و بازبینی این چرخه</a>
                    @if($state === 'open')
                        <a class="btn btn-outline-primary" href="{{ route('groups.chat', $election->group) }}">رفتن به برگه رأی</a>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="alert alert-info">هنوز چرخه انتخاباتی مرتبط با گروه‌های شما ثبت نشده است.</div>
    @endforelse
</div>
@endsection
