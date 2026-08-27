import "./group-chat/index.js";

const importFeature = (loader, label) => {
    void loader().catch((error) => {
        console.warn(`EarthCoop ${label} runtime could not be loaded:`, error);
    });
};

// Keep the core chat runtime on the critical path, but defer management-only
// enhancements until the browser has had a chance to paint the conversation.
const loadChatEnhancements = () => {
    importFeature(() => import("./group-comment-form-fallback.js"), "group comment fallback");

    const loadManagementRuntime = () => {
        importFeature(async () => {
            await Promise.all([
                import("./najm-hoda-management-console-v2.js"),
                import("./najm-hoda-management-content-tools.js"),
                import("./najm-hoda-management-native-tools.js"),
                import("./najm-hoda-management-live-attention.js"),
            ]);
        }, "group management");
    };

    if (typeof window.requestIdleCallback === "function") {
        window.requestIdleCallback(loadManagementRuntime, { timeout: 1200 });
    } else {
        window.setTimeout(loadManagementRuntime, 150);
    }
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", loadChatEnhancements, { once: true });
} else {
    loadChatEnhancements();
}
