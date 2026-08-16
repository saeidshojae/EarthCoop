export class ApiError extends Error {
    constructor(message, { status = 0, code = 'request_failed', requestId = null, details = null } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.requestId = requestId;
        this.details = details;
    }
}

const safeMethods = new Set(['GET', 'HEAD', 'OPTIONS']);

export class ApiClient {
    constructor({ fetchImpl = null, timeoutMs = 15000, retries = 1, csrfToken = null } = {}) {
        this.fetchImpl = fetchImpl || globalThis.fetch?.bind(globalThis);
        if (!this.fetchImpl) throw new TypeError('Fetch API is unavailable');
        this.timeoutMs = timeoutMs;
        this.retries = retries;
        this.csrfToken = csrfToken;
        this.activeMutationCount = 0;
        this.activeSafeControllers = new Set();
        this.mutationWaiters = new Set();
    }

    id(prefix = 'req') {
        const value = globalThis.crypto?.randomUUID?.()
            || `${Date.now()}_${Math.random().toString(16).slice(2)}`;
        return `${prefix}_${value}`;
    }

    async waitForMutations() {
        if (this.activeMutationCount === 0) return;
        await new Promise(resolve => this.mutationWaiters.add(resolve));
    }

    beginMutation() {
        this.activeMutationCount += 1;
        // Background reads are disposable/retriable. Give an explicit user
        // mutation priority so polling cannot starve it on constrained/local
        // PHP servers.
        this.activeSafeControllers.forEach(controller => controller.abort('mutation-priority'));
        this.activeSafeControllers.clear();
    }

    endMutation() {
        this.activeMutationCount = Math.max(0, this.activeMutationCount - 1);
        if (this.activeMutationCount !== 0) return;
        const waiters = Array.from(this.mutationWaiters);
        this.mutationWaiters.clear();
        waiters.forEach(resolve => resolve());
    }

    async request(input, init = {}) {
        const options = { ...init };
        const method = String(options.method || 'GET').toUpperCase();
        const isSafe = safeMethods.has(method);
        const headers = new Headers(options.headers || {});
        headers.set('Accept', headers.get('Accept') || 'application/json');
        headers.set('X-Request-ID', headers.get('X-Request-ID') || this.id());

        if (!isSafe) {
            const csrf = this.csrfToken || globalThis.document?.querySelector('meta[name="csrf-token"]')?.content;
            if (csrf && !headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', csrf);
            if (!headers.has('Idempotency-Key')) headers.set('Idempotency-Key', this.id('idem'));
        }
        options.headers = headers;

        if (!isSafe) this.beginMutation();

        let lastError;
        try {
            for (let attempt = 0; attempt <= this.retries; attempt += 1) {
                if (isSafe) await this.waitForMutations();

                const controller = new AbortController();
                if (isSafe) this.activeSafeControllers.add(controller);
                const abortUpstream = () => controller.abort(options.signal?.reason);
                options.signal?.addEventListener?.('abort', abortUpstream, { once: true });
                const timer = globalThis.setTimeout(() => controller.abort('timeout'), this.timeoutMs);
                try {
                    const response = await this.fetchImpl(input, { ...options, signal: controller.signal });
                    if (attempt < this.retries && response.status >= 500) {
                        await this.delay(250 * (attempt + 1));
                        continue;
                    }
                    return response;
                } catch (error) {
                    lastError = error;
                    if (controller.signal.aborted && controller.signal.reason !== 'mutation-priority') {
                        throw error;
                    }
                    if (attempt >= this.retries) throw error;
                    await this.delay(250 * (attempt + 1));
                } finally {
                    globalThis.clearTimeout(timer);
                    if (isSafe) this.activeSafeControllers.delete(controller);
                    options.signal?.removeEventListener?.('abort', abortUpstream);
                }
            }
            throw lastError || new ApiError('Request failed');
        } finally {
            if (!isSafe) this.endMutation();
        }
    }

    async json(input, init = {}) {
        const response = await this.request(input, init);
        const payload = await response.json().catch(() => null);
        if (!response.ok) {
            const error = payload?.error || payload || {};
            if (error.code === 'group_session_closed') {
                globalThis.dispatchEvent?.(new CustomEvent('group-chat:session-closed', { detail: error }));
            }
            throw new ApiError(error.message || `Request failed (${response.status})`, {
                status: response.status,
                code: error.code,
                requestId: response.headers.get('X-Request-ID') || payload?.meta?.request_id,
                details: error.details,
            });
        }
        return payload?.data ?? payload;
    }

    delay(ms) {
        return new Promise(resolve => globalThis.setTimeout(resolve, ms));
    }
}
