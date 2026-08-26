@extends('layouts.unified')

@section('title', 'پرتال انتخابات - ' . $group->name)

@section('content')
<div class="container py-4" dir="rtl" data-election-user-portal="v1">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">انتخابات سیستمی — {{ $group->name }}</h1>
            <p class="text-muted mb-0">وضعیت چرخه، بازخورد مجاز، گزارش امن، پاسخ‌های موضوعی و بازبینی رویه‌ای</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('elections.guideline') }}" class="btn btn-outline-success"><i class="fas fa-book-open ms-1"></i>شیوه‌نامه انتخابات</a>
            <a href="{{ route('groups.chat', $group) }}" class="btn btn-outline-primary">بازگشت به گفت‌وگوی گروه و برگه رأی</a>
        </div>
    </div>

    @if(!$election)
        <div class="alert alert-info">هنوز چرخه انتخاباتی ثبت‌شده‌ای برای این گروه وجود ندارد. ایجاد چرخه فقط توسط سامانه انجام می‌شود.</div>
    @else
        @php
            $state = $election->lifecycle_status?->value ?? $election->lifecycle_status;
            $policy = $election->policyVersion;
        @endphp

        <section class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label">چرخه</label>
                        <select class="form-select" onchange="location.href=this.value">
                            @foreach($cycles as $cycle)
                                <option value="{{ route('elections.portal', ['group'=>$group, 'election_id'=>$cycle->id]) }}" @selected($cycle->id === $election->id)>
                                    چرخه {{ (int)($cycle->cycle_number ?? 1) }} — {{ $cycle->lifecycle_status?->value ?? $cycle->lifecycle_status }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-8">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-primary">وضعیت: {{ $state }}</span>
                            <span class="badge bg-secondary">سیاست v{{ (int)($policy?->version ?? 0) }}</span>
                            <span class="badge bg-light text-dark">مدیر: {{ (int)($policy?->manager_count ?? 0) }}</span>
                            <span class="badge bg-light text-dark">بازرس: {{ (int)($policy?->inspector_count ?? 0) }}</span>
                            <span class="badge bg-light text-dark">مهلت پاسخ: {{ (int)($policy?->response_duration_days ?? 0) }} روز</span>
                        </div>
                    </div>
                </div>
                <div class="small text-muted mt-3">
                    زمان پنجره: {{ optional($election->starts_at)->format('Y-m-d H:i') }} تا {{ optional($election->ends_at)->format('Y-m-d H:i') }}.
                    تغییر سیاست بعدی، این نسخه فریز‌شده را بازنویسی نمی‌کند.
                </div>
            </div>
        </section>

        @if($offers->isNotEmpty())
            <section class="card mb-4">
                <div class="card-header fw-bold">پیشنهادهای مسئولیت من</div>
                <div class="card-body">
                    @foreach($offers as $offer)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <span>{{ $offer->position }} — رتبه {{ (int)$offer->ranking_position }}</span>
                                <span class="badge bg-secondary">{{ $offer->status?->value ?? $offer->status }}</span>
                            </div>
                            <div class="small text-muted mt-1">نسخه قرارداد: {{ (int)$offer->contract_version_id }} | مهلت: {{ optional($offer->expires_at)->format('Y-m-d H:i') }}</div>
                            @if(($offer->status?->value ?? $offer->status) === 'pending')
                                <a class="btn btn-sm btn-primary mt-2" href="{{ route('profile.accept.candidate', ['type'=>$offer->position]) }}">مطالعه قرارداد و پاسخ صریح</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="card mb-4" id="candidate-report">
            <div class="card-header fw-bold">گزارش محبوبیت و رضایت — privacy-safe</div>
            <div class="card-body">
                <form method="GET" action="{{ route('elections.portal', $group) }}" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="election_id" value="{{ $election->id }}">
                    <div class="col-md-6">
                        <label class="form-label">عضو</label>
                        <select name="subject_user_id" class="form-select" required>
                            <option value="">انتخاب کنید…</option>
                            @foreach($members as $member)
                                @php $name = trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: ('عضو #'.$member->id); @endphp
                                <option value="{{ $member->id }}" @selected((int)$selectedSubjectId === (int)$member->id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">نقش گزارش</label>
                        <select name="position" class="form-select">
                            <option value="manager" @selected($selectedPosition === 'manager')>مدیر</option>
                            <option value="inspector" @selected($selectedPosition === 'inspector')>بازرس</option>
                        </select>
                    </div>
                    <div class="col-md-3"><button class="btn btn-primary w-100">نمایش گزارش امن</button></div>
                </form>

                @if($candidateReportError)
                    <div class="alert alert-warning">{{ $candidateReportError }}</div>
                @elseif($candidateReport)
                    <div class="row g-2 mb-2">
                        <div class="col-sm-4"><div class="border rounded p-2">رأی فعلی: <strong>{{ $candidateReport['current_votes'] }}</strong></div></div>
                        <div class="col-sm-4"><div class="border rounded p-2">حد برش: <strong>{{ $candidateReport['selection_cutoff_votes'] ?? '—' }}</strong></div></div>
                        <div class="col-sm-4"><div class="border rounded p-2">فاصله تا برش: <strong>{{ $candidateReport['margin_to_selection_cutoff'] ?? '—' }}</strong></div></div>
                    </div>
                    @if($candidateReport['details_suppressed'] ?? false)
                        <div class="alert alert-secondary mb-0">جزئیات روند برای جلوگیری از بازشناسایی رأی‌دهندگان پنهان است. دلیل: {{ $candidateReport['suppression_reason'] }}</div>
                    @else
                        <div class="row g-2">
                            <div class="col-md-3"><div class="border rounded p-2">ورودی: {{ $candidateReport['inflow'] }}</div></div>
                            <div class="col-md-3"><div class="border rounded p-2">خروجی: {{ $candidateReport['outflow'] }}</div></div>
                            <div class="col-md-3"><div class="border rounded p-2">خالص: {{ $candidateReport['net_change'] }}</div></div>
                            <div class="col-md-3"><div class="border rounded p-2">ماندگاری: {{ $candidateReport['retention_rate'] !== null ? round($candidateReport['retention_rate']*100,1).'٪' : 'پنهان' }}</div></div>
                        </div>
                    @endif
                @endif
            </div>
        </section>

        <section class="card mb-4" id="visible-feedback">
            <div class="card-header fw-bold">نظرها و دلایل رأی که مجاز به دیدنشان هستید</div>
            <div class="card-body">
                <p class="small text-muted">این فهرست فقط از read-policy مرکزی عبور می‌کند. نظر ناشناس، هویت نویسنده را نشان نمی‌دهد و زمان دقیق رویداد رأی نیز نمایش داده نمی‌شود.</p>
                @forelse($visibleFeedback as $feedback)
                    <article class="border rounded p-3 mb-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>{{ $feedback['subject_name'] }} — {{ $feedback['event_type'] }}</strong>
                            <span class="badge bg-light text-dark">{{ $feedback['visibility'] }}</span>
                        </div>
                        <p class="mb-1 mt-2">{{ $feedback['body'] }}</p>
                        <div class="small text-muted">
                            @if($feedback['anonymous'])
                                نویسنده: ناشناس
                            @else
                                نویسنده: {{ $feedback['author_name'] ?? ('عضو #'.$feedback['author_user_id']) }}
                            @endif
                            @if($feedback['public_bucket_start'])
                                — بازه عمومی: {{ $feedback['public_bucket_start'] }}
                            @endif
                            @if(($feedback['moderation_status'] ?? 'approved') !== 'approved')
                                — وضعیت بررسی نظر شما: {{ $feedback['moderation_status'] }}
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="text-muted mb-0">در این چرخه نظر قابل نمایشی برای شما وجود ندارد.</p>
                @endforelse
            </div>
        </section>

        <section class="card mb-4" id="topic-responses">
            <div class="card-header fw-bold">پاسخ عمومی به موضوعات تجمیعی</div>
            <div class="card-body">
                @if($mayRespondToTopics)
                    @if($publicTopics && !($publicTopics['topics_suppressed'] ?? true) && !empty($publicTopics['topics']))
                        <form data-topic-response-form class="border rounded p-3 mb-3">
                            <label class="form-label">موضوع امن و تجمیعی</label>
                            <select name="topic" class="form-select mb-2" required>
                                @foreach($publicTopics['topics'] as $topic)
                                    <option value="{{ $topic['topic'] }}">{{ $topic['topic'] }} ({{ $topic['count'] }})</option>
                                @endforeach
                            </select>
                            <label class="form-label">پاسخ عمومی</label>
                            <textarea name="body" maxlength="5000" rows="3" class="form-control mb-2" required></textarea>
                            <button class="btn btn-primary">انتشار پاسخ موضوعی</button>
                            <div class="form-text">پاسخ فقط به موضوعی مجاز است که از آستانه privacy عبور کرده؛ هیچ feedback، نویسنده یا ballot event به پاسخ متصل نمی‌شود.</div>
                        </form>
                    @else
                        <div class="alert alert-secondary">در حال حاضر موضوع عمومی کافی برای پاسخ‌گویی وجود ندارد یا آستانه privacy برقرار نشده است.</div>
                    @endif
                @endif

                @forelse($topicResponses as $response)
                    <article class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between gap-2"><strong>{{ $response['topic'] }}</strong><span class="small text-muted">{{ $response['aggregate_count'] }} بازخورد تجمیعی</span></div>
                        <p class="mb-1 mt-2">{{ $response['body'] }}</p>
                        <small class="text-muted">عضو موضوع: {{ $memberNames[(int)$response['subject_user_id']] ?? ('عضو #'.$response['subject_user_id']) }} — {{ $response['published_at'] }}</small>
                    </article>
                @empty
                    <p class="text-muted mb-0">هنوز پاسخ موضوعی عمومی ثبت نشده است.</p>
                @endforelse
            </div>
        </section>

        <section class="card mb-4" id="process-review">
            <div class="card-header fw-bold">بازبینی رویه‌ای و بازشماری</div>
            <div class="card-body">
                <p class="small text-muted">مهلت ۷روزه از زمان event ثبت‌شده در audit محاسبه می‌شود؛ تاریخ دلخواه کاربر پذیرفته نمی‌شود.</p>
                <form data-process-review-form class="row g-2 mb-4">
                    <div class="col-lg-6">
                        <label class="form-label">رویداد مورد اعتراض</label>
                        <select name="event_ref" class="form-select" required>
                            <option value="">انتخاب کنید…</option>
                            @foreach($reviewEvents as $event)
                                <option value="{{ $event['type'] }}:{{ $event['id'] }}"
                                    data-subject="{{ $event['subject_user_id'] ?? '' }}"
                                    data-appointment="{{ $event['appointment_id'] ?? '' }}">
                                    {{ $event['label'] }} — {{ $event['occurred_at']->format('Y-m-d H:i') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">مبنای اعتراض</label>
                        <select name="ground" class="form-select" required>
                            @foreach(\App\Models\ElectionProcessReview::GROUNDS as $ground)
                                <option value="{{ $ground }}">{{ $ground }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">شرح اختیاری</label>
                        <textarea name="statement" maxlength="5000" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-12"><button class="btn btn-outline-primary">بررسی خودکار و ثبت درخواست</button></div>
                </form>

                <h3 class="h6">درخواست‌های مرتبط با من</h3>
                @forelse($ownReviews as $review)
                    <div class="border rounded p-3 mb-2" data-review-id="{{ $review->id }}">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>#{{ $review->id }} — {{ $review->ground }}</strong>
                            <span class="badge bg-secondary">خودکار: {{ $review->automatic_status }} | انسانی: {{ $review->human_status }}</span>
                        </div>
                        <div class="small mt-1">مهلت درخواست انسانی: {{ optional($review->human_deadline_at)->format('Y-m-d H:i') }}</div>
                        @if(in_array($review->human_status, ['not_requested','awaiting_support'], true) && optional($review->human_deadline_at)->isFuture())
                            <button class="btn btn-sm btn-outline-primary mt-2" data-request-human-review="{{ $review->id }}">درخواست رسیدگی انسانی</button>
                        @endif
                        @if($review->decision)
                            <div class="alert alert-light mt-2 mb-0"><strong>{{ $review->decision }}</strong> — {{ $review->decision_reason }} @if($review->remediation_reference) | اصلاح: {{ $review->remediation_reference }} @endif</div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted">درخواستی مرتبط با شما ثبت نشده است.</p>
                @endforelse

                @if($supportableReviews->isNotEmpty())
                    <h3 class="h6 mt-4">درخواست‌های در انتظار حمایت اعضا</h3>
                    @foreach($supportableReviews as $review)
                        <div class="border rounded p-2 mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span>#{{ $review->id }} — {{ $review->ground }} | حمایت: {{ $review->support_count }}/3</span>
                            <button class="btn btn-sm btn-outline-secondary" data-endorse-review="{{ $review->id }}">حمایت از رسیدگی انسانی</button>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
@if($election)
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const jsonPost = async (url, body = {}) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'درخواست ناموفق بود.');
        return payload;
    };

    document.querySelector('[data-topic-response-form]')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            await jsonPost(@json(route('elections.feedback-topic-responses.store', $election)), {
                topic: form.topic.value,
                body: form.body.value,
            });
            location.reload();
        } catch (error) { alert(error.message); }
    });

    document.querySelector('[data-process-review-form]')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const option = form.event_ref.selectedOptions[0];
        if (!option?.value) return;
        const [challenged_event, challenged_event_id] = option.value.split(':');
        try {
            const review = await jsonPost(@json(route('elections.process-reviews.store', $election)), {
                ground: form.ground.value,
                challenged_event,
                challenged_event_id: Number(challenged_event_id),
                subject_user_id: option.dataset.subject ? Number(option.dataset.subject) : null,
                appointment_id: option.dataset.appointment ? Number(option.dataset.appointment) : null,
                statement: form.statement.value || null,
            });
            alert(`بازبینی #${review.id} ثبت شد. نتیجه خودکار: ${review.automatic_status}`);
            location.reload();
        } catch (error) { alert(error.message); }
    });

    document.querySelectorAll('[data-request-human-review]').forEach(button => button.addEventListener('click', async () => {
        try {
            await jsonPost(@json(url('/elections/process-reviews')).replace(/\/$/, '') + '/' + button.dataset.requestHumanReview + '/human');
            location.reload();
        } catch (error) { alert(error.message); }
    }));

    document.querySelectorAll('[data-endorse-review]').forEach(button => button.addEventListener('click', async () => {
        try {
            await jsonPost(@json(url('/elections/process-reviews')).replace(/\/$/, '') + '/' + button.dataset.endorseReview + '/endorse');
            location.reload();
        } catch (error) { alert(error.message); }
    }));
})();
</script>
@endif
@endpush