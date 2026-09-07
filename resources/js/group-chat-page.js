import "./group-chat/index.js";
import { createGroupChatHeaderController } from "./group-chat-header-controller.js";

const importFeature = (loader, label) => {
    void loader().catch((error) => {
        console.warn(`EarthCoop ${label} runtime could not be loaded:`, error);
    });
};

let chatHeaderController = null;

const bootChatHeaderController = () => {
    if (chatHeaderController) return;
    const header = document.querySelector('header.site-header-unified[data-header-context="chat"]');
    const content = document.querySelector('.chat-content-wrapper');
    if (!header || !content) return;

    chatHeaderController = createGroupChatHeaderController({ header, content });

    const lifecycle = window.GroupChatLifecycle;
    if (lifecycle && !lifecycle.destroyed && typeof lifecycle.add === 'function') {
        lifecycle.add(() => {
            chatHeaderController?.destroy();
            chatHeaderController = null;
        });
    }
};

// Keep the core chat runtime on the critical path, but defer management-only
// enhancements until the browser has had a chance to paint the conversation.
const loadChatEnhancements = () => {
    bootChatHeaderController();
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
