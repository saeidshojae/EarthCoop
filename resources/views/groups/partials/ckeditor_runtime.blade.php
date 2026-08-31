<script type="module">
let lifecycleWaitFrames = 0;
let ckeditorRuntimeInitialized = false;

function initializeGroupChatCkeditorRuntime() {
    const lifecycle = arguments[0];
    if (!lifecycle || lifecycle.destroyed || ckeditorRuntimeInitialized) return;
    ckeditorRuntimeInitialized = true;

    let loaderPromise = null;
    let ckeditorWait = null;
    let idleWarmHandle = null;

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

        const instance = ckeditor.replace('post_editor', {
            filebrowserUploadUrl: "{{ route('admin.pages.upload') }}?_token={{ csrf_token() }}",
            filebrowserUploadMethod: 'form',
            language: 'fa',
            height: 180,
            resize_enabled: false,
            removePlugins: 'uploadimage,elementspath',
            toolbar: [
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'paragraph', items: ['BulletedList', 'NumberedList'] },
                { name: 'clipboard', items: ['Undo', 'Redo'] }
            ]
        });

        if (instance && typeof instance.on === 'function') {
            instance.on('instanceReady', function() {
                instance.resize('100%', 180);
            });
        }
        return Boolean(instance);
    }

    function stopEditorWait() {
        if (ckeditorWait === null) return;
        lifecycle.clearInterval(ckeditorWait);
        ckeditorWait = null;
    }

    function waitForEditorReady() {
        stopEditorWait();
        let attempts = 0;
        ckeditorWait = lifecycle.interval(function() {
            attempts += 1;
            if (initializePostEditor() || attempts >= 40) stopEditorWait();
        }, 50);
    }

    function ensureCkeditorLibrary() {
        if (window.CKEDITOR) {
            installChatConfig();
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

    function loadPostEditor() {
        return ensureCkeditorLibrary().then((ckeditor) => {
            initializePostEditor();
            waitForEditorReady();
            return ckeditor;
        });
    }

    function warmPostEditorLibrary() {
        if (window.CKEDITOR || loaderPromise) return;
        void ensureCkeditorLibrary().catch(() => {});
    }

    function scheduleEditorWarmup() {
        const warm = () => {
            idleWarmHandle = null;
            warmPostEditorLibrary();
        };

        if ('requestIdleCallback' in window) {
            idleWarmHandle = window.requestIdleCallback(warm, { timeout: 2500 });
        } else {
            idleWarmHandle = window.setTimeout(warm, 1400);
        }
    }

    lifecycle.on(window, 'group-chat:post-modal-opened', () => {
        void loadPostEditor().catch(() => {
            window.GroupChatFeedback?.toast?.('ویرایشگر متن بارگذاری نشد؛ لطفاً دوباره تلاش کنید.', { type: 'error' });
        });
    });

    window.GroupChatPostEditor = Object.freeze({ loadPostEditor, warmPostEditorLibrary });

    const modal = document.getElementById('postFormBox');
    if (modal && getComputedStyle(modal).display !== 'none') {
        void loadPostEditor().catch(() => {});
    } else {
        scheduleEditorWarmup();
    }

    lifecycle.add(function() {
        stopEditorWait();
        if (idleWarmHandle !== null) {
            if ('cancelIdleCallback' in window) window.cancelIdleCallback(idleWarmHandle);
            else window.clearTimeout(idleWarmHandle);
            idleWarmHandle = null;
        }
        const instance = window.CKEDITOR?.instances?.post_editor;
        if (instance && typeof instance.destroy === 'function') instance.destroy(true);
        delete window.GroupChatPostEditor;
    });
}

function waitForGroupChatLifecycle() {
    const lifecycle = window.GroupChatLifecycle;
    if (lifecycle && !lifecycle.destroyed) {
        initializeGroupChatCkeditorRuntime(lifecycle);
        return;
    }

    lifecycleWaitFrames += 1;
    if (lifecycleWaitFrames < 180) {
        window.requestAnimationFrame(waitForGroupChatLifecycle);
    }
}

waitForGroupChatLifecycle();
</script>
