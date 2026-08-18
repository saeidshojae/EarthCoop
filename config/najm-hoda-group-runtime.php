<?php

return [
    // Public group-chat mention/reply mode is legacy. The canonical interaction
    // surface is the private Najm Hoda widget; only action results are published
    // to the group. This flag allows a deliberate future opt-in if needed.
    'public_chat_enabled' => env('NAJM_HODA_GROUP_PUBLIC_CHAT_ENABLED', false),
];
