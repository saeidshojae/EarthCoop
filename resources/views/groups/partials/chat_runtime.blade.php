@php
$membershipParticipationService = app(\App\Services\MembershipParticipationEligibilityService::class);
$membershipParticipationStatus = $membershipParticipationService->status(auth()->user());
@endphp
<script>
const groupId = @json((int) $group->id);
window.groupChatTransport = @json(($realtimeConfig['transport'] ?? 'polling'));
const authUserId = @json((int) auth()->id());
const yourRole = @json((int) ($yourRole ?? 0));
window.EarthCoopRealtimeConfig = Object.freeze(@json($realtimeConfig ?? []));
window.groupId = groupId;
window.authUserId = authUserId;
window.yourRole = yourRole;
window.GroupChatConfig = Object.freeze({
    groupId,
    authUserId,
    yourRole,
    syncCursor: @json((int) ($groupSyncCursor ?? 0)),
    syncUrl: @json(route('groups.sync', $group)),
    pollingIntervalMs: @json((int) ($realtimeConfig['pollingIntervalMs'] ?? 1800)),
    enabled: window.EarthCoopRealtimeConfig.enabled !== false,
    transport: window.EarthCoopRealtimeConfig.transport || 'polling',
    fallbackToPolling: window.EarthCoopRealtimeConfig.fallbackToPolling !== false,
    deltaSyncEnabled: @json((bool) config('group-chat.features.delta_sync_v1', false)),
    lastReadMessageId: @json($lastReadMessageId ?? null),
    updateLastReadUrl: @json(route('groups.messages.updateLastRead', $group->id)),
    sessionOpen: @json((bool) $group->is_open),
    sessionToggleUrl: @json(route('groups.session.toggle', $group)),
    canParticipate: @json(auth()->user()->can('participate', $group)),
    canManageSession: @json(auth()->user()->can('manageSession', $group)),
    membershipParticipation: Object.freeze({
        status: @json($membershipParticipationStatus),
        eligible: @json($membershipParticipationStatus === \App\Services\MembershipParticipationEligibilityService::ELIGIBLE),
        agreementUrl: @json(route('najm-bahar.agreement')),
        dashboardUrl: @json(route('najm-bahar.dashboard')),
    }),
    participationRequestUrl: @json(route('groups.session-participation.request', $group)),
    participationStateUrl: @json(route('groups.session-participation.state', $group)),
    participationIndexUrl: @json(route('groups.session-participation.index', $group)),
    participationBulkUrl: @json(route('groups.session-participation.bulk', $group)),
    pinsUrl: @json(route('groups.pins.index', $group)),
});
// Database values may legitimately be NULL. Always emit valid JavaScript.
const manageCount = @json((int) ($groupSetting?->manager_count ?? 0));
const inspectorCount = @json((int) ($groupSetting?->inspector_count ?? 0));
</script>
<script src="{{ asset('js/membership-participation-gate.js') }}" defer></script>
<script src="{{ asset('js/chat-features.js') }}" defer></script>
<script src="{{ asset('js/voice-recorder.js') }}" defer></script>