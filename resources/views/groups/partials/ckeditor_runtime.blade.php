<script>
function initializeGroupChatCkeditorRuntime() {
    const lifecycle = window.GroupChatLifecycle;
    if (!lifecycle || lifecycle.destroyed || window.__groupChatCkeditorInitialized) return;
    window.__groupChatCkeditorInitialized = true;

    function installChatConfig() {
        const ckeditor = window.CKEDITOR;
        if (!ckeditor) return false;

        if (!ckeditor.__chatWarnFilterInstalled) {
            ckeditor.__chatWarnFilterInstalled = true;
            ckeditor.config.versionCheck = false;

            const currentRemove = (ckeditor.config.removePlugins || '')
                .split(',')
                .map(plugin => plugin.trim())
                .filter(Boolean);
            if (!currentRemove.includes('uploadimage')) currentRemove.push('uploadimage');
            ckeditor.config.removePlugins = currentRemove.join(',');

            const originalWarn = ckeditor.warn;
            ckeditor.warn = function(message, data) {
                const text = String(message || '');
                if (text.includes('clipboard-image-handling-disabled') || text.includes('version is not secure')) {
                    return;
                }
                return originalWarn.call(this, message, data);
            };
        }

        return true;
    }

    function initializePostEditor() {
        const ckeditor = window.CKEDITOR;
        const editor = document.getElementById('post_editor');
        if (!ckeditor || !editor || ckeditor.instances?.post_editor) return;

        ckeditor.replace('post_editor', {
            filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
            filebrowserUploadMethod: 'form',
            language: 'fa',
            height: 400,
            removePlugins: 'uploadimage',
            removeButtons: '',
            toolbarGroups: [
                { name: 'basicstyles', groups: ['basicstyles', 'cleanup'] },
                { name: 'paragraph', groups: ['list', 'indent', 'blocks', 'align'] },
                { name: 'styles' },
                { name: 'colors' },
                { name: 'insert' },
                { name: 'tools' },
                { name: 'editing' },
                { name: 'document', groups: ['mode', 'document'] },
                { name: 'clipboard', groups: ['clipboard', 'undo'] },
                { name: 'links' }
            ]
        });
    }

    function configureWhenReady() {
        if (!installChatConfig()) return false;
        initializePostEditor();
        return true;
    }

    if (!configureWhenReady()) {
        const ckeditorWait = lifecycle.interval(function() {
            if (configureWhenReady()) lifecycle.clearInterval(ckeditorWait);
        }, 50);
    }

    lifecycle.add(function() {
        const instance = window.CKEDITOR?.instances?.post_editor;
        if (instance && typeof instance.destroy === 'function') instance.destroy(true);
        window.__groupChatCkeditorInitialized = false;
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeGroupChatCkeditorRuntime, { once: true });
} else {
    initializeGroupChatCkeditorRuntime();
}
</script>
