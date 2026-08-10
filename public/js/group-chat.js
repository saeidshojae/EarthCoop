// Add styles for chat features (inject once)
const existingRuntimeStyle = document.getElementById('group-chat-runtime-style');
const style = existingRuntimeStyle || document.createElement('style');
if (!existingRuntimeStyle) {
    style.id = 'group-chat-runtime-style';
}
style.textContent = `
    .chat-search-box {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        width: 80%;
        max-width: 500px;
    }

    .search-header {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }

    .search-header input {
        flex: 1;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .search-header button {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }

    .search-results {
        max-height: 300px;
        overflow-y: auto;
    }

    .report-box {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        width: 80%;
        max-width: 500px;
    }

    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .report-header button {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }

    .report-content {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .report-content select,
    .report-content textarea {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .report-content textarea {
        min-height: 100px;
        resize: vertical;
    }

    .report-content button {
        background: #007bff;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 4px;
        cursor: pointer;
    }

    .report-content button:hover {
        background: #0056b3;
    }
`;
if (!existingRuntimeStyle) {
    document.head.appendChild(style);
}

const legacyLifecycle = window.GroupChatLifecycle;
const groupChatDebug = Boolean(
    window.__groupChatDebug ||
    window.__chatPollingDebug ||
    (typeof window !== 'undefined' && window.localStorage && (
        window.localStorage.getItem('__groupChatDebug') === '1' ||
        window.localStorage.getItem('__chatPollingDebug') === '1'
    ))
);
const debugLog = (...args) => {
    if (groupChatDebug) {
        console.log(...args);
    }
};
const debugWarn = (...args) => {
    if (groupChatDebug) {
        console.warn(...args);
    }
};
// ========== FORCE LOG - همیشه نمایش بده ==========
// استفاده از alert برای اطمینان از نمایش
if (groupChatDebug && typeof window !== 'undefined') {
    debugLog('🔍🔍🔍 SCRIPT LOADED - VERSION 2024-12-19-v4 🔍🔍🔍');
    debugLog('🔍 window.groupId:', typeof window.groupId !== 'undefined' ? window.groupId : 'NOT DEFINED YET');
    debugLog('🔍 Current time:', new Date().toISOString());
    
    // تست: اگر بعد از 3 ثانیه console.log ها نمایش داده نشدند، alert نمایش بده
    legacyLifecycle.timeout(function() {
        if (typeof window.groupId !== 'undefined') {
            debugLog('✅✅✅ POLLING TEST: window.groupId is defined:', window.groupId);
        } else {
            debugWarn('❌❌❌ POLLING TEST: window.groupId is NOT defined!');
            // نمایش alert فقط برای debugging
            // Debug output is intentionally console-only.
        }
    }, 3000);
}
// ========== END FORCE LOG ==========

window.GroupChat.installLegacyRenderers({
    updateLastPostCursor: id => window.GroupChat.realtimeRuntime?.advancePost(id),
});
const realtimeRuntime = window.GroupChat.installRealtime({ debug: groupChatDebug });
window.GroupChat.composer.initializeSubmission({ feed: window.GroupChat.feed, realtime: realtimeRuntime });
legacyLifecycle.timeout(() => {
    realtimeRuntime.initialize();
    realtimeRuntime.startPolling();
}, 2000);

