<?php

return [
    /*
     | System issuer identity for invitation codes
     |
     | null is a valid value because invitation_codes.user_id is nullable. This
     | avoids coupling system-issued codes to a historical user id. Deployments
     | that want a visible issuer may set INVITATION_SYSTEM_ISSUER_USER_ID to an
     | existing user id; the resolver validates it before use.
     */
    'system_issuer_user_id' => env('INVITATION_SYSTEM_ISSUER_USER_ID'),
];
