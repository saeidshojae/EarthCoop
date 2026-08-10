import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiClient, ApiError } from '../../../resources/js/group-chat/api-client.js';

test('api client attaches request and idempotency headers to writes', async () => {
    let request;
    const api = new ApiClient({
        retries: 0,
        csrfToken: 'csrf-test',
        fetchImpl: async (input, init) => {
            request = { input, init };
            return new Response(JSON.stringify({ data: { ok: true } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        },
    });
    const data = await api.json('/write', { method: 'POST' });
    assert.deepEqual(data, { ok: true });
    assert.equal(request.init.headers.get('X-CSRF-TOKEN'), 'csrf-test');
    assert.match(request.init.headers.get('X-Request-ID'), /^req_/);
    assert.match(request.init.headers.get('Idempotency-Key'), /^idem_/);
});

test('api client maps non-success JSON to ApiError', async () => {
    const api = new ApiClient({
        retries: 0,
        fetchImpl: async () => new Response(JSON.stringify({ error: { code: 'forbidden', message: 'Denied' } }), { status: 403 }),
    });
    await assert.rejects(() => api.json('/denied'), error => {
        assert.equal(error instanceof ApiError, true);
        assert.equal(error.status, 403);
        assert.equal(error.code, 'forbidden');
        return true;
    });
});
