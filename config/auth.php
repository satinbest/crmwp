<?php

return [
    'session_name' => 'crmwp_session',
    'session_lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 7200),
    'secure' => filter_var(getenv('SESSION_SECURE') ?: false, FILTER_VALIDATE_BOOLEAN),
    'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',
    'rate_limit' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],
    'password' => [
        'algo' => PASSWORD_BCRYPT,
        'options' => ['cost' => 12],
    ],
];
