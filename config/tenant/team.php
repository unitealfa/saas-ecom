<?php

return [
    'invitation_ttl_hours' => max(1, min(168, (int) env('TENANT_INVITATION_TTL_HOURS', 48))),
];
