<script type="module">
function initializeGroupChatCkeditorRuntime() {
    const lifecycle = window.GroupChatLifecycle;
    if (!lifecycle || lifecycle.destroyed) return;

    let loaderPromise = null;

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
                if (text.includes('clipboard-image-handling-disabled') || text.includes('version is not secure')) return;
                return originalWarn.call(this, message, data);
            };
        }
        return true;
    }

    function initializePostEditor() {
        const ckeditor = window.CKEDITOR;
        const editor = document.getElementById('post_editor');
        const modal = document.getElementById('postFormBox');
        if (!ckeditor || !editor || !modal || getComputedStyle(modal).display === 'none') return false;
        if (ckeditor.instances?.post_editor) return true;

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
                { name: 'styles' }, { name: 'colors' }, { name: 'insert' }, { name: 'tools' },
                { name: 'editing' }, { name: 'document', groups: ['mode', 'document'] },
                { name: 'clipboard', groups: ['clipboard', 'undo'] }, { name: 'links' }
            ]
        });
        return true;
    }

    function loadPostEditor() {
        if (window.CKEDITOR) {
            installChatConfig();
            initializePostEditor();
            return Promise.resolve(window.CKEDITOR);
        }
        if (loaderPromise) return loaderPromise;

        loaderPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = @json(asset('vendor/ckeditor/ckeditor.js'));
            script.async = true;
            script.dataset.groupChatCkeditor = 'true';
            script.onload = () => {
                installChatConfig();
                initializePostEditor();
                resolve(window.CKEDITOR);
            };
            script.onerror = () => {
                loaderPromise = null;
                reject(new Error('CKEditor failed to load'));
            };
            document.head.appendChild(script);
        });
        return loaderPromise;
    }

    lifecycle.on(window, 'group-chat:post-modal-opened', () => {
        void loadPostEditor().catch(() => {
            window.GroupChatFeedback?.toast?.('ویرایشگر متن بارگذاری نشد؛ لطفاً دوباره تلاش کنید.', { type: 'error' });
        });
    });

    window.GroupChatPostEditor = Object.freeze({ loadPostEditor });

    lifecycle.add(function() {
        const instance = window.CKEDITOR?.instances?.post_editor;
        if (instance && typeof instance.destroy === 'function') instance.destroy(true);
        delete window.GroupChatPostEditor;
    });
}

initializeGroupChatCkeditorRuntime();
</script>
