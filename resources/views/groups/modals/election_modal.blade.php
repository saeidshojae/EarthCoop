@php
    $policy = $electionPolicy ?? $election?->policyVersion;
    $managerLimit = max(0, (int) ($policy?->manager_count ?? 0));
    $inspectorLimit = max(0, (int) ($policy?->inspector_count ?? 0));
    $cycleNumber = (int) ($election?->cycle_number ?? 1);
    $selectedManagers = array_map('intval', $selectedVotesManager ?? []);
    $selectedInspectors = array_map('intval', $selectedVotesInspector ?? []);
    $visibilityMap = $voteVisibilityByCandidate ?? [];
    $canVote = isset($election) && $election
        && ($election->lifecycle_status?->value ?? $election->lifecycle_status) === 'open'
        && isset($yourRole) && !in_array((int) $yourRole, [0, 4], true);
@endphp

<div class="election-box election-card" dir="rtl" data-election-systemic-ui="v1">
    <button type="button" class="election-close" aria-label="بستن فرم انتخابات" data-group-chat-action="close-election">
        <i class="fas fa-times" aria-hidden="true"></i>
    </button>

    <div class="election-modal-header">
        <div class="election-title-section">
            <div class="election-icon-wrapper"><i class="fas fa-vote-yea" aria-hidden="true"></i></div>
            <h2 class="election-title">انتخابات سیستمی پیوسته</h2>
            <p class="election-subtitle">چرخه {{ $cycleNumber }} — انتخاب مدیران و بازرسان از میان اعضای واجد شرایط گروه</p>
            <a href="{{ route('elections.guideline') }}" class="btn btn-sm btn-outline-success mt-2" aria-label="مشاهده شیوه‌نامه و راهنمای کامل انتخابات">
                <i class="fas fa-book-open ms-1" aria-hidden="true"></i> شیوه‌نامه انتخابات
            </a>
        </div>
    </div>

    <div class="election-modal-body">
        @if(!$canVote)
            <div class="election-not-allowed" role="status">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                <h3>این فرم اکنون در وضعیت دریافت رأی نیست</h3>
                <p>چرخه‌های انتخابات توسط سامانه باز و متوقف می‌شوند. مشاهده این صفحه هیچ تغییری در زمان‌بندی یا نتیجه ایجاد نمی‌کند.</p>
            </div>
        @else
            <div class="alert alert-info mb-3" role="status">
                <strong>رأی شما تا پایان این پنجره قابل تغییر یا پس‌گرفتن است.</strong>
                <div class="small mt-1">ظرفیت این چرخه از نسخه سیاست فریز‌شده خوانده می‌شود: {{ $managerLimit }} مدیر و {{ $inspectorLimit }} بازرس.</div>
            </div>

            @if($election->ends_at)
                <div class="mb-3" aria-live="polite">
                    <div id="countdownText" class="text-center fw-semibold" data-election-ends-at="{{ $election->ends_at->toIso8601String() }}"></div>
                    <div class="mt-2" style="background:rgba(236,253,245,.6);border-radius:999px;overflow:hidden;height:8px;">
                        <div id="progressBar" style="height:100%;width:0%;background:currentColor;opacity:.35"></div>
                    </div>
                </div>
            @endif

            <form action="{{ route('vote', $group) }}" method="POST" id="electionForm" data-systemic-election-ballot
                data-election-manager-limit="{{ $managerLimit }}"
                data-election-inspector-limit="{{ $inspectorLimit }}"
                data-election-starts-at="{{ $election?->starts_at?->toIso8601String() }}"
                data-election-ends-at="{{ $election?->ends_at?->toIso8601String() }}">
                @csrf

                <div class="mb-3">
                    <label for="electionMemberSearch" class="form-label fw-semibold">جست‌وجوی عضو</label>
                    <input id="electionMemberSearch" type="search" class="form-control" placeholder="نام عضو را جست‌وجو کنید…" autocomplete="off">
                    <div class="form-text">در EarthCoop نامزدی رسمی لازم نیست؛ هر عضو واجد شرایط گروه می‌تواند انتخاب شود.</div>
                </div>

                <div class="election-role-tabs" data-election-role-tabs role="tablist" aria-label="انتخاب نقش در برگه انتخابات">
                    <button type="button" class="election-role-tab is-active" role="tab" aria-selected="true" aria-controls="electionManagerPanel" data-election-role-tab="manager">
                        <span>مدیران</span>
                        <span class="election-role-tab__count" data-election-role-tab-count="manager">{{ count($selectedManagers) }}/{{ $managerLimit }}</span>
                    </button>
                    <button type="button" class="election-role-tab" role="tab" aria-selected="false" aria-controls="electionInspectorPanel" data-election-role-tab="inspector" tabindex="-1">
                        <span>بازرسان</span>
                        <span class="election-role-tab__count" data-election-role-tab-count="inspector">{{ count($selectedInspectors) }}/{{ $inspectorLimit }}</span>
                    </button>
                </div>

                <div class="row g-3 election-role-panels">
                    <div class="col-12 col-xl-6 is-active" id="electionManagerPanel" data-election-role-panel="manager" aria-hidden="false">
                        <section class="border rounded-3 p-3 h-100" aria-labelledby="managerSelectionTitle">
                            <h3 id="managerSelectionTitle" class="h6 fw-bold mb-2">مدیران <span class="badge bg-secondary" data-election-count="manager">{{ count($selectedManagers) }}/{{ $managerLimit }}</span></h3>
                            <p class="small text-muted">حداکثر {{ $managerLimit }} نفر.</p>
                            <div class="election-member-list" data-election-list="manager" style="max-height:42vh;overflow:auto;">
                                @foreach(($electionMembers ?? collect()) as $member)
                                    @php
                                        $memberId = (int) $member->id;
                                        $checked = in_array($memberId, $selectedManagers, true);
                                        $visibility = $visibilityMap[$memberId] ?? 'confidential';
                                        $memberName = trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: ('عضو #'.$memberId);
                                        $memberInitials = trim(mb_substr((string)($member->first_name ?? ''), 0, 1).mb_substr((string)($member->last_name ?? ''), 0, 1)) ?: mb_substr($memberName, 0, 2);
                                        $memberAvatar = !empty($member->avatar) ? asset('storage/'.ltrim((string)$member->avatar, '/')) : null;
                                    @endphp
                                    <div class="election-member-option border-bottom py-2" data-election-member data-member-name="{{ mb_strtolower($memberName) }}">
                                        <label class="d-flex gap-2 align-items-start mb-1">
                                            <input class="form-check-input mt-1" type="checkbox" name="manager[]" value="{{ $memberId }}" data-election-choice="manager" data-candidate-id="{{ $memberId }}" @checked($checked)>
                                            <span class="election-member-identity">
                                                <span class="election-member-avatar" aria-hidden="true">
                                                    @if($memberAvatar)
                                                        <img src="{{ $memberAvatar }}" alt="" loading="lazy">
                                                    @else
                                                        {{ $memberInitials }}
                                                    @endif
                                                </span>
                                                <span class="election-member-copy">
                                                    <strong>{{ $memberName }}</strong>
                                                    <a class="small ms-1" href="{{ route('profile.member.show', $memberId) }}" target="_blank" rel="noopener">پروفایل</a>
                                                </span>
                                            </span>
                                        </label>
                                        <label class="small d-block ms-4">
                                            سطح افشای این رأی
                                            <select class="form-select form-select-sm mt-1 election-privacy-select" name="vote_visibility[{{ $memberId }}]" data-vote-visibility-for="{{ $memberId }}" data-election-role="manager" @disabled(!$checked)>
                                                <option value="confidential" @selected($visibility === 'confidential')>محرمانه</option>
                                                <option value="all_members" @selected($visibility === 'all_members')>همه اعضا</option>
                                                <option value="elected_officials" @selected($visibility === 'elected_officials')>منتخبین</option>
                                            </select>
                                            <span class="election-privacy-help">محرمانه: هویت رأی‌دهنده در نمایش عادی پنهان می‌ماند.</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>

                    <div class="col-12 col-xl-6" id="electionInspectorPanel" data-election-role-panel="inspector" aria-hidden="true">
                        <section class="border rounded-3 p-3 h-100" aria-labelledby="inspectorSelectionTitle">
                            <h3 id="inspectorSelectionTitle" class="h6 fw-bold mb-2">بازرسان <span class="badge bg-secondary" data-election-count="inspector">{{ count($selectedInspectors) }}/{{ $inspectorLimit }}</span></h3>
                            <p class="small text-muted">حداکثر {{ $inspectorLimit }} نفر. یک عضو را نمی‌توان همزمان مدیر و بازرس انتخاب کرد.</p>
                            <div class="election-member-list" data-election-list="inspector" style="max-height:42vh;overflow:auto;">
                                @foreach(($electionMembers ?? collect()) as $member)
                                    @php
                                        $memberId = (int) $member->id;
                                        $checked = in_array($memberId, $selectedInspectors, true);
                                        $visibility = $visibilityMap[$memberId] ?? 'confidential';
                                        $memberName = trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: ('عضو #'.$memberId);
                                        $memberInitials = trim(mb_substr((string)($member->first_name ?? ''), 0, 1).mb_substr((string)($member->last_name ?? ''), 0, 1)) ?: mb_substr($memberName, 0, 2);
                                        $memberAvatar = !empty($member->avatar) ? asset('storage/'.ltrim((string)$member->avatar, '/')) : null;
                                    @endphp
                                    <div class="election-member-option border-bottom py-2" data-election-member data-member-name="{{ mb_strtolower($memberName) }}">
                                        <label class="d-flex gap-2 align-items-start mb-1">
                                            <input class="form-check-input mt-1" type="checkbox" name="inspector[]" value="{{ $memberId }}" data-election-choice="inspector" data-candidate-id="{{ $memberId }}" @checked($checked)>
                                            <span class="election-member-identity">
                                                <span class="election-member-avatar" aria-hidden="true">
                                                    @if($memberAvatar)
                                                        <img src="{{ $memberAvatar }}" alt="" loading="lazy">
                                                    @else
                                                        {{ $memberInitials }}
                                                    @endif
                                                </span>
                                                <span class="election-member-copy">
                                                    <strong>{{ $memberName }}</strong>
                                                    <a class="small ms-1" href="{{ route('profile.member.show', $memberId) }}" target="_blank" rel="noopener">پروفایل</a>
                                                </span>
                                            </span>
                                        </label>
                                        <label class="small d-block ms-4">
                                            سطح افشای این رأی
                                            <select class="form-select form-select-sm mt-1 election-privacy-select" name="vote_visibility[{{ $memberId }}]" data-vote-visibility-for="{{ $memberId }}" data-election-role="inspector" @disabled(!$checked)>
                                                <option value="confidential" @selected($visibility === 'confidential')>محرمانه</option>
                                                <option value="all_members" @selected($visibility === 'all_members')>همه اعضا</option>
                                                <option value="elected_officials" @selected($visibility === 'elected_officials')>منتخبین</option>
                                            </select>
                                            <span class="election-privacy-help">محرمانه: هویت رأی‌دهنده در نمایش عادی پنهان می‌ماند.</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>
                </div>

                <section class="border rounded-3 p-3 mt-3" aria-labelledby="ballotReasonTitle">
                    <h3 id="ballotReasonTitle" class="h6 fw-bold">دلیل اختیاری برای رأی، تغییر یا پس‌گرفتن رأی</h3>
                    <textarea name="comment" id="electionComment" class="form-control" maxlength="4000" rows="3" placeholder="در صورت تمایل دلیل خود را بنویسید…"></textarea>
                    <div class="row g-2 mt-2">
                        <div class="col-md-7">
                            <label for="electionCommentVisibility" class="form-label small">چه کسانی این توضیح را ببینند؟</label>
                            <select name="comment_visibility" id="electionCommentVisibility" class="form-select election-privacy-select">
                                <option value="all_members">همه اعضا</option>
                                <option value="elected_officials">منتخبین</option>
                                <option value="subject_only">فقط فرد مرتبط</option>
                            </select>
                            <div class="election-comment-visibility-help">همه اعضای مجاز گروه این توضیح را می‌بینند.</div>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <label class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="comment_anonymous" value="1">
                                <span class="form-check-label">توضیح به‌صورت ناشناس نمایش داده شود</span>
                            </label>
                        </div>
                    </div>
                    <p class="form-text mb-0">ناشناس بودن توضیح مستقل از دامنه نمایش است. هویت لازم برای audit حفاظت‌شده نگهداری می‌شود و در مسیر عادی نمایش داده نمی‌شود.</p>
                </section>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1" data-election-submit>ثبت / به‌روزرسانی برگه رأی</button>
                    <button type="button" class="btn btn-outline-secondary" data-election-clear>پس‌گرفتن همه انتخاب‌ها</button>
                    <button type="button" class="btn btn-outline-secondary" data-group-chat-action="close-election">بستن</button>
                </div>
                <p class="small text-muted mt-2 mb-0">پس‌گرفتن رأی نتیجه تاریخی را حذف نمی‌کند؛ رویداد تغییر/پس‌گرفتن در audit ثبت می‌شود.</p>
            </form>
        @endif
    </div>
</div>

<script type="module">
window.GroupElectionModal = {
    updateElectionSelect2() {},
    openCandidatesModal() {},
    openGuidelineModal() {},
    openTopVotesModal() {},
};
</script>