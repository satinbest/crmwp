<?php

namespace App\Validators;

use Exception;

class StoreValidator
{
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // 1. Store Name
        if (!$isUpdate || array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));
            if (empty($name)) {
                $errors['name'] = 'نام فروشگاه نمی‌تواند خالی باشد.';
            } elseif (mb_strlen($name) > 150) {
                $errors['name'] = 'نام فروشگاه نمی‌تواند بیش از ۱۵۰ کاراکتر باشد.';
            }
        }

        // 2. Store URL
        if (!$isUpdate || array_key_exists('url', $data)) {
            $url = trim((string)($data['url'] ?? ''));
            if (empty($url)) {
                $errors['url'] = 'آدرس اینترنتی فروشگاه الزامی است.';
            } else {
                try {
                    $normalizedUrl = self::normalizeUrl($url);
                    $data['normalized_url'] = $normalizedUrl;
                } catch (Exception $e) {
                    $errors['url'] = $e->getMessage();
                }
            }
        }

        // 3. Consumer Key & Secret
        if (!$isUpdate) {
            $consumerKey = trim((string)($data['consumer_key'] ?? ''));
            $consumerSecret = trim((string)($data['consumer_secret'] ?? ''));

            if (empty($consumerKey)) {
                $errors['consumer_key'] = 'کلید دسترسی (Consumer Key) الزامی است.';
            }

            if (empty($consumerSecret)) {
                $errors['consumer_secret'] = 'رمز دسترسی (Consumer Secret) الزامی است.';
            }
        } else {
            // On update, credentials are optional (only validated if supplied)
            if (isset($data['consumer_key']) && empty(trim((string)$data['consumer_key']))) {
                $errors['consumer_key'] = 'کلید دسترسی نمی‌تواند خالی باشد.';
            }
            if (isset($data['consumer_secret']) && empty(trim((string)$data['consumer_secret']))) {
                $errors['consumer_secret'] = 'رمز دسترسی نمی‌تواند خالی باشد.';
            }
        }

        if (!empty($errors)) {
            throw new Exception(json_encode($errors, JSON_UNESCAPED_UNICODE), 422);
        }

        return $data;
    }

    public static function normalizeUrl(string $rawUrl): string
    {
        $url = trim($rawUrl);

        // Prepend https:// if no scheme provided
        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            throw new Exception('آدرس فروشگاه وارد شده نامعتبر است.');
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host']);
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';

        // Reject admin URLs and API URLs
        $lowerPath = strtolower($path);
        if (str_contains($lowerPath, 'wp-admin') || str_contains($lowerPath, 'wp-login.php')) {
            throw new Exception('آدرس ورودی نباید شامل مسیر بخش مدیریت (/wp-admin) باشد. لطفاً آدرس اصلی سایت را وارد کنید.');
        }

        if (str_contains($lowerPath, 'wp-json') || str_contains($lowerPath, 'wc/v3')) {
            throw new Exception('آدرس ورودی نباید شامل مسیر API (/wp-json) باشد. لطفاً آدرس ریشه سایت را وارد نمایید.');
        }

        // Clean up path and remove trailing slashes
        $cleanPath = rtrim($path, '/');

        return "{$scheme}://{$host}{$port}{$cleanPath}";
    }
}
