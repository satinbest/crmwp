<?php

if (!defined('CRM_APP_VERSION')) {
    define('CRM_APP_VERSION', '1.1.0');
}

return [
    'name' => getenv('APP_NAME') ?: 'CRMWP - سامانه مدیریت ووکامرس و CRM',
    'version' => getenv('APP_VERSION') ?: CRM_APP_VERSION,
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
    'donate' => [
        'recipient_name' => getenv('DONATE_RECIPIENT_NAME') ?: 'حسین محمدپور',
        'card_number' => getenv('DONATE_CARD_NUMBER') ?: '6219861931965403',
        'email' => getenv('DONATE_EMAIL') ?: 'info@hosseinmohammadpour.ir',
        'github' => getenv('DONATE_GITHUB') ?: 'satinbest/crmwp',
    ],
];

