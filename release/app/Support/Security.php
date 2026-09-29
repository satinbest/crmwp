<?php

namespace App\Support;

class Security
{
    private const CIPHER = 'aes-256-cbc';

    public static function hashPassword(string $password): string
    {
        $algo = Config::get('auth.password.algo', PASSWORD_BCRYPT);
        $options = Config::get('auth.password.options', ['cost' => 12]);
        return password_hash($password, $algo, $options);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function encrypt(string $plainText): string
    {
        if (empty($plainText)) {
            return '';
        }

        $appSecret = Config::get('app.secret', 'default-crmwp-secret-key-32chars!');
        $key = hash('sha256', $appSecret, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $cipherText = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $iv . $cipherText, $key, true);

        return base64_encode($iv . $hmac . $cipherText);
    }

    public static function decrypt(string $cipherData): ?string
    {
        if (empty($cipherData)) {
            return null;
        }

        $data = base64_decode($cipherData, true);
        if ($data === false) {
            return null;
        }

        $appSecret = Config::get('app.secret', 'default-crmwp-secret-key-32chars!');
        $key = hash('sha256', $appSecret, true);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $hmacLength = 32;

        if (strlen($data) < ($ivLength + $hmacLength)) {
            return null;
        }

        $iv = substr($data, 0, $ivLength);
        $hmac = substr($data, $ivLength, $hmacLength);
        $cipherText = substr($data, $ivLength + $hmacLength);

        $calculatedHmac = hash_hmac('sha256', $iv . $cipherText, $key, true);
        if (!hash_equals($hmac, $calculatedHmac)) {
            return null;
        }

        $decrypted = openssl_decrypt($cipherText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted === false ? null : $decrypted;
    }

    public static function maskConsumerKey(string $key): string
    {
        if (empty($key)) {
            return '';
        }
        if (str_starts_with($key, 'ck_')) {
            return 'ck_' . str_repeat('*', max(12, strlen($key) - 3));
        }
        return substr($key, 0, 3) . str_repeat('*', max(10, strlen($key) - 3));
    }

    public static function maskSecret(string $secret): string
    {
        if (empty($secret)) {
            return '';
        }
        return '••••••••••••••••';
    }

    /**
     * Prevent CSV Formula Injection by prepending ' to values starting with =, +, -, @, \t, \r
     */
    public static function escapeCsvFormula(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $dangerousFirstChars = ['=', '+', '-', '@', "\t", "\r"];
        if (in_array($value[0], $dangerousFirstChars, true)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Sanitize output to prevent XSS in HTML contexts
     */
    public static function sanitizeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
