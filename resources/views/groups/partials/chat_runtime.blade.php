<script>
const groupId = @json((int) $group->id);
window.groupChatTransport = @json(config('group-chat.transport', 'auto'));
const authUserId = @json((int) auth()->id());
const yourRole = @json((int) ($yourRole ?? 0));
window.groupId = groupId;
window.authUserId = authUserId;
window.yourRole = yourRole;
window.GroupChatConfig = Object.freeze({
    groupId,
    authUserId,
    yourRole,
    syncCursor: @json((int) ($groupSyncCursor ?? 0)),
    syncUrl: @json(route('groups.sync', $group)),
    pollingIntervalMs: @json((int) config('group-chat.polling_interval_ms', 1800)),
    deltaSyncEnabled: @json((bool) config('group-chat.features.delta_sync_v1', false)),
    lastReadMessageId: @json($lastReadMessageId ?? null),
    updateLastReadUrl: @json(route('groups.messages.updateLastRead', $group->id)),
    sessionOpen: @json((bool) $group->is_open),
    sessionToggleUrl: @json(route('groups.session.toggle', $group)),
    canParticipate: @json(auth()->user()->can('participate', $group)),
    canManageSession: @json(auth()->user()->can('manageSession', $group)),
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
<script src="{{ asset('js/chat-features.js') }}" defer></script>
<script src="{{ asset('js/voice-recorder.js') }}" defer></script>
