<?php

if (!defined('CRM_APP_VERSION')) {
    define('CRM_APP_VERSION', '1.0.0');
}

return [
    'name' => getenv('APP_NAME') ?: 'CRMWP - سامانه مدیریت ووکامرس و CRM',
    'version' => CRM_APP_VERSION,
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'secret' => getenv('APP_SECRET') ?: 'default-crmwp-insecure-secret-key-change-me',
    'timezone' => 'Asia/Tehran',
    'locale' => 'fa',
    'developer' => [
        'name' => getenv('DEVELOPER_NAME') ?: 'تیم توسعه CRMWP',
        'website' => getenv('DEVELOPER_WEBSITE') ?: '',
        'email' => getenv('DEVELOPER_EMAIL') ?: '',
    ],
];

