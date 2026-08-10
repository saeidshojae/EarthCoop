<script>
const groupId = {{ $group->id }};
window.groupChatTransport = @json(config('group-chat.transport', 'auto'));
const authUserId = {{ auth()->id() }};
const yourRole = {{ $yourRole ?? 0 }};
window.groupId = groupId;
window.authUserId = authUserId;
window.yourRole = yourRole;
window.GroupChatConfig = Object.freeze({
    groupId,
    authUserId,
    yourRole,
    deltaSyncEnabled: @json((bool) config('group-chat.features.delta_sync_v1', false)),
    lastReadMessageId: @json($lastReadMessageId ?? null),
    updateLastReadUrl: @json(route('groups.messages.updateLastRead', $group->id)),
});
const manageCount = {{ $groupSetting ? $groupSetting->manager_count : 0 }};
const inspectorCount = {{ $groupSetting ? $groupSetting->inspector_count : 0 }};
</script>
<script src="{{ asset('js/chat-features.js') }}" defer></script>
<script src="{{ asset('js/voice-recorder.js') }}" defer></script>
