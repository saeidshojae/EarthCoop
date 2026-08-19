<?php

return [
    'attachments' => [
        // Server-side hard limit. Keep this independent from UI/PHP upload limits.
        'max_bytes' => (int) env('SECRETARIAT_ATTACHMENT_MAX_BYTES', 25 * 1024 * 1024),

        // Deliberately excludes executable/script MIME types. Deployments may
        // narrow this list further without changing domain code.
        'allowed_mime_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SECRETARIAT_ATTACHMENT_ALLOWED_MIMES', implode(',', [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/zip',
            ])))
        ))),
    ],
];
