const Pusher = require('pusher-js');

const key = process.argv[2] || 'app-key';
const host = process.argv[3] || '127.0.0.1';
const port = Number(process.argv[4] || 6001);
const timeoutMs = Number(process.argv[5] || 10000);

const client = new Pusher(key, {
    wsHost: host,
    wsPort: port,
    forceTLS: false,
    enabledTransports: ['ws'],
    cluster: 'mt1',
});

let settled = false;
const finish = (code, message) => {
    if (settled) return;
    settled = true;
    console.log(message);
    client.disconnect();
    process.exit(code);
};

client.connection.bind('connected', () => finish(0, 'PUSHER_CONNECTED'));
client.connection.bind('error', error => finish(1, `PUSHER_ERROR ${JSON.stringify(error)}`));
setTimeout(() => finish(2, 'PUSHER_TIMEOUT'), timeoutMs);
