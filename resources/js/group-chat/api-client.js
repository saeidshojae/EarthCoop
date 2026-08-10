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
    constructor({ fetchImpl = globalThis.fetch, timeoutMs = 15000, retries = 1, csrfToken = null } = {}) {
        this.fetchImpl = fetchImpl;
        this.timeoutMs = timeoutMs;
        this.retries = retries;
        this.csrfToken = csrfToken;
    }

    id(prefix = 'req') {
        const value = globalThis.crypto?.randomUUID?.()
            || `${Date.now()}_${Math.random().toString(16).slice(2)}`;
        return `${prefix}_${value}`;
    }

    async request(input, init = {}) {
        const options = { ...init };
        const method = String(options.method || 'GET').toUpperCase();
        const headers = new Headers(options.headers || {});
        headers.set('Accept', headers.get('Accept') || 'application/json');
        headers.set('X-Request-ID', headers.get('X-Request-ID') || this.id());

        if (!safeMethods.has(method)) {
            const csrf = this.csrfToken || globalThis.document?.querySelector('meta[name="csrf-token"]')?.content;
            if (csrf && !headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', csrf);
            if (!headers.has('Idempotency-Key')) headers.set('Idempotency-Key', this.id('idem'));
        }
        options.headers = headers;

        let lastError;
        for (let attempt = 0; attempt <= this.retries; attempt += 1) {
            const controller = new AbortController();
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
                if (controller.signal.aborted || attempt >= this.retries) throw error;
                await this.delay(250 * (attempt + 1));
            } finally {
                globalThis.clearTimeout(timer);
                options.signal?.removeEventListener?.('abort', abortUpstream);
            }
        }
        throw lastError || new ApiError('Request failed');
    }

    async json(input, init = {}) {
        const response = await this.request(input, init);
        const payload = await response.json().catch(() => null);
        if (!response.ok) {
            const error = payload?.error || payload || {};
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
