export function createReconciler({ initialSequence = 0, maxSeen = 1000 } = {}) {
    let lastSequence = Number(initialSequence) || 0;
    const seen = new Set();

    return {
        inspect(event, { commit = true } = {}) {
            if (!event) return { action: 'ignore', reason: 'empty' };
            if (event.event_id && seen.has(event.event_id)) return { action: 'ignore', reason: 'duplicate' };
            const sequence = Number(event.sequence || 0);
            if (sequence && sequence > lastSequence + 1) {
                return { action: 'sync', afterSequence: lastSequence };
            }
            if (commit && event.event_id) {
                seen.add(event.event_id);
                if (seen.size > maxSeen) seen.delete(seen.values().next().value);
            }
            if (commit) lastSequence = Math.max(lastSequence, sequence);
            return { action: 'apply', sequence: Math.max(lastSequence, sequence) };
        },
        advance(sequence) {
            lastSequence = Math.max(lastSequence, Number(sequence) || 0);
            return lastSequence;
        },
        get sequence() { return lastSequence; },
    };
}
