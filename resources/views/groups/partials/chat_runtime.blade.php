<script>
const groupId = {{ $group->id }};
window.groupChatTransport = @json(config('group-chat.transport', 'auto'));
window.__groupChatModularFrontend = @json(config('group-chat.features.modular_frontend_v1', false));
const authUserId = {{ auth()->id() }};
const yourRole = {{ $yourRole ?? 0 }};
window.groupId = groupId;
window.authUserId = authUserId;
window.yourRole = yourRole;
const manageCount = {{ $groupSetting ? $groupSetting->manager_count : 0 }};
const inspectorCount = {{ $groupSetting ? $groupSetting->inspector_count : 0 }};
</script>
<script src="{{ asset('js/chat-features.js') }}" defer></script>
<script src="{{ asset('js/voice-recorder.js') }}" defer></script>
